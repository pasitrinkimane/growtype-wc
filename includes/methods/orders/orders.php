<?php

class Growtype_Wc_Order
{

    public function __construct()
    {
        add_action('woocommerce_new_order', array ($this, 'growtype_wc_woocommerce_new_order'), 10, 3);
        add_action('woocommerce_payment_complete', array ($this, 'growtype_wc_woocommerce_payment_complete'), 10, 2);
    }

    /**
     * Extending new order creation process
     */
    function growtype_wc_woocommerce_new_order($order_id, $order)
    {
        /**
         * Add extra meta data
         */
        update_post_meta($order->get_id(), '_customer_full_name', $order->get_billing_last_name() . ' ' . $order->get_billing_first_name());
    }

    /**
     * Extending new order creation process
     */
    function growtype_wc_woocommerce_payment_complete($order_id, $transaction_id)
    {
        $subscription = Growtype_Wc_Subscription::growtype_wc_order_get_subscription_order($order_id);

        if (!empty($subscription)) {
            $post_id = wp_insert_post([
                'post_title' => $subscription->get_data_key('title'),
                'post_type' => 'growtype_wc_subs',
                'post_status' => 'private'
            ]);

            update_post_meta($post_id, '_order_id', $order_id);
            update_post_meta($post_id, '_status', Growtype_Wc_Subscription::STATUS_ACTIVE);
            update_post_meta($post_id, '_duration', $subscription->get_data_key('billing_interval'));
            update_post_meta($post_id, '_price', $subscription->get_data_key('billing_price'));
            update_post_meta($post_id, '_period', $subscription->get_data_key('billing_period'));
            update_post_meta($post_id, '_user_id', get_current_user_id());
            update_post_meta($post_id, '_start_date', wp_date('Y-m-d H:i:s'));
            update_post_meta($post_id, '_end_date', wp_date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' + ' . $subscription->get_data_key('billing_interval') . ' ' . $subscription->get_data_key('billing_period'))));
            update_post_meta($post_id, '_next_charge_date', wp_date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' + ' . $subscription->get_data_key('billing_interval') . ' ' . $subscription->get_data_key('billing_period'))));
        }

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $current_user->remove_role('lead');
            $current_user->add_role('customer');
        }
    }

    /**
     * Check if the user has an unpaid, abandoned cart order
     */
    public static function get_abandoned_cart_order($user_email, $min_age_in_minutes = 10, $orders_period_in_minutes = 7200)
    {
        $current_time = current_time('timestamp');
        $min_time_threshold = $current_time - ($min_age_in_minutes * MINUTE_IN_SECONDS);
        $period_start_time = $current_time - ($orders_period_in_minutes * MINUTE_IN_SECONDS);

        $orders = wc_get_orders([
            'customer' => $user_email,
            'limit' => 1, // Check the last order only
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => [
                'after' => date('Y-m-d H:i:s', $period_start_time), // Search orders created within the last 60 minutes
            ],
        ]);

        if ($orders) {
            $last_order = $orders[0];

            $order_timestamp = $last_order->get_date_created()->getOffsetTimestamp(); // Get order time in site's timezone

            if (!$last_order->is_paid() && $order_timestamp < $min_time_threshold) {
                return $last_order->get_id();
            }
        }

        return null;
    }
}
