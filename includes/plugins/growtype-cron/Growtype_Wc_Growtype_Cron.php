<?php

class Growtype_Wc_Cron
{
    const GROWTYPE_WC_PROCESS_SUBSCRIPTIONS_HOOK = 'growtype_cron_growtype_wc_process_subscriptions';
    const GROWTYPE_WC_RECONCILE_PAYPAL_SUBSCRIPTIONS_HOOK = 'growtype_cron_growtype_wc_reconcile_paypal_subscriptions';

    public function __construct()
    {
        add_filter('growtype_cron_load_jobs', [$this, 'growtype_cron_load_jobs'], 10);

        add_action(self::GROWTYPE_WC_PROCESS_SUBSCRIPTIONS_HOOK, array ($this, 'generate_jobs'));

        add_action(
            self::GROWTYPE_WC_RECONCILE_PAYPAL_SUBSCRIPTIONS_HOOK,
            array ($this, 'generate_paypal_reconciliation_jobs')
        );

        add_action('wp_loaded', array (
            $this,
            'cron_activation'
        ));
    }

    function cron_activation()
    {
        if (!wp_next_scheduled(self::GROWTYPE_WC_PROCESS_SUBSCRIPTIONS_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::GROWTYPE_WC_PROCESS_SUBSCRIPTIONS_HOOK);
        }

        if (!wp_next_scheduled(self::GROWTYPE_WC_RECONCILE_PAYPAL_SUBSCRIPTIONS_HOOK)) {
            wp_schedule_event(
                time() + (5 * MINUTE_IN_SECONDS),
                'daily',
                self::GROWTYPE_WC_RECONCILE_PAYPAL_SUBSCRIPTIONS_HOOK
            );
        }
    }

    function growtype_cron_load_jobs($jobs)
    {
        $jobs = array_merge($jobs, [
            'growtype-wc-generate-product' => [
                'classname' => 'Growtype_Cron_Generate_Product',
                'path' => GROWTYPE_WC_PATH . 'includes/plugins/growtype-cron/jobs/Growtype_Cron_Generate_Product.php',
            ],
            'growtype-wc-update-product' => [
                'classname' => 'Growtype_Cron_Update_Product',
                'path' => GROWTYPE_WC_PATH . 'includes/plugins/growtype-cron/jobs/Growtype_Cron_Update_Product.php',
            ],
            'process-subscription' => [
                'classname' => 'Process_Subscription_Job',
                'path' => GROWTYPE_WC_PATH . 'includes/plugins/growtype-cron/jobs/Process_Subscription_Job.php',
            ],
            'reconcile-paypal-subscription' => [
                'classname' => 'Reconcile_Paypal_Subscription_Job',
                'path' => GROWTYPE_WC_PATH . 'includes/plugins/growtype-cron/jobs/Reconcile_Paypal_Subscription_Job.php',
            ]
        ]);

        return $jobs;
    }

    function generate_jobs()
    {
        $subscriptions = growtype_wc_get_subscriptions([
            'status' => Growtype_Wc_Subscription::STATUS_ACTIVE,
            'limit' => -1,
        ]);

        error_log(sprintf('growtype_wc_subs_check. valid subscriptions found: %s', count($subscriptions)));

        foreach ($subscriptions as $subscription) {
            Growtype_Cron_Jobs::create_if_not_exists(
                'process-subscription',
                wp_json_encode(['subscription_id' => $subscription->ID]),
                0
            );
        }
    }

    public function generate_paypal_reconciliation_jobs(): void
    {
        $this->cleanup_paypal_webhook_claims();

        $batch_size = (int) apply_filters('growtype_wc_paypal_reconciliation_batch_size', 200);
        $batch_size = max(25, min(500, $batch_size));
        $page = 1;

        do {
            $query = new WP_Query([
                'post_type' => 'growtype_wc_subs',
                'post_status' => 'any',
                'posts_per_page' => $batch_size,
                'paged' => $page,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_query' => [[
                    'key' => '_status',
                    'value' => [Growtype_Wc_Subscription::STATUS_ACTIVE, 'on-hold'],
                    'compare' => 'IN',
                ]],
            ]);

            foreach ($query->posts as $subscription_id) {
                $subscription_id = (int) $subscription_id;
                $order_id = (int) get_post_meta($subscription_id, '_order_id', true);
                $order = $order_id > 0 ? wc_get_order($order_id) : false;

                if (
                    !$order instanceof WC_Order
                    || $order->get_payment_method() !== Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID
                    || empty($order->get_meta('paypal_subscription_id'))
                ) {
                    continue;
                }

                Growtype_Cron_Jobs::create_if_not_exists(
                    'reconcile-paypal-subscription',
                    wp_json_encode(['subscription_id' => $subscription_id]),
                    0
                );
            }

            $page++;
        } while ($page <= (int) $query->max_num_pages);

        wp_reset_postdata();
    }

    private function cleanup_paypal_webhook_claims(): void
    {
        global $wpdb;

        // PayPal documents finite webhook retry windows. Retaining event claims
        // for 45 days prevents replay while keeping wp_options bounded.
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %s",
            $wpdb->esc_like('gwc_pp_webhook_') . '%',
            gmdate('Y-m-d H:i:s', time() - (45 * DAY_IN_SECONDS))
        ));
    }
}
