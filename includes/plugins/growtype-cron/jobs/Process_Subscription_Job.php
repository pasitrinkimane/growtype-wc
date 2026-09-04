<?php

class Process_Subscription_Job
{
    public function run($job)
    {
        $payload = json_decode($job['payload'] ?? '', true);
        $subscription_id = (int) ($payload['subscription_id'] ?? 0);

        if ($subscription_id <= 0) {
            throw new InvalidArgumentException('A valid subscription_id is required.');
        }

        if (
            get_post_type($subscription_id) !== 'growtype_wc_subs'
            || Growtype_Wc_Subscription::status($subscription_id) !== Growtype_Wc_Subscription::STATUS_ACTIVE
        ) {
            return;
        }

        $order_id = (int) get_post_meta($subscription_id, '_order_id', true);
        $order = $order_id > 0 ? wc_get_order($order_id) : false;

        // Ensure payment gateways have registered provider-specific guards.
        WC()->payment_gateways()->payment_gateways();

        $allowed = apply_filters(
            'growtype_wc_can_process_subscription_benefits',
            true,
            $subscription_id,
            $order
        );
        if (is_wp_error($allowed) || $allowed !== true) {
            return;
        }

        /**
         * Product-specific recurring benefits should subscribe to this action.
         * Payment renewal remains the responsibility of the payment gateway.
         */
        do_action('growtype_wc_process_subscription', $subscription_id);
    }
}
