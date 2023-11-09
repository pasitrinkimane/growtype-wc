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
        $order = wc_get_order($order_id);
        $subscription = growtype_wc_order_get_subscription_order($order);

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
        }

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $current_user->remove_role('lead');
            $current_user->add_role('customer');
        }
    }
}

new Growtype_Wc_Order();
