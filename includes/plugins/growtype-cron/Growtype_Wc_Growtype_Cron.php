<?php

class Growtype_Wc_Cron
{
    const GROWTYPE_WC_PROCESS_SUBSCRIPTIONS_HOOK = 'growtype_cron_growtype_wc_process_subscriptions';

    public function __construct()
    {
        add_filter('growtype_cron_load_jobs', [$this, 'growtype_cron_load_jobs'], 10);

        add_action(self::GROWTYPE_WC_PROCESS_SUBSCRIPTIONS_HOOK, array ($this, 'generate_jobs'));

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
            ]
        ]);

        return $jobs;
    }

    function generate_jobs()
    {
        $subscriptions = growtype_wc_get_subscriptions(Growtype_Wc_Subscription::STATUS_ACTIVE);

        error_log(sprintf('growtype_wc_subs_check. valid subscriptions found: %s', count($subscriptions)));

        $delay = 0;
        foreach ($subscriptions as $subscription) {
            growtype_cron_init_job('process-subscription', json_encode(['subscription_id' => $subscription->ID]), $delay);
            $delay += 20;
        }
    }
}
