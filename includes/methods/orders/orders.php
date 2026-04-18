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
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();

        /**
         * 1. Subscriptions logic
         */
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
            update_post_meta($post_id, '_user_id', $user_id);
            update_post_meta($post_id, '_start_date', wp_date('Y-m-d H:i:s'));
            update_post_meta($post_id, '_end_date', wp_date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' + ' . $subscription->get_data_key('billing_interval') . ' ' . $subscription->get_data_key('billing_period'))));
            update_post_meta($post_id, '_next_charge_date', wp_date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' + ' . $subscription->get_data_key('billing_interval') . ' ' . $subscription->get_data_key('billing_period'))));

            // Clear active subscription cache for this user
            delete_transient('growtype_wc_user_has_active_sub_' . $user_id);
        }

        /**
         * 3. Role management
         */
        if ($user_id) {
            $current_user = get_user_by('id', $user_id);
            if ($current_user) {
                $current_user->remove_role('lead');
                $current_user->add_role('customer');
            }
        }
    }

    private static $user_last_order_cache = [];

    /**
     * Check if the user has an unpaid, abandoned cart order
     */
    public static function get_abandoned_cart_order($user_email, $min_age_in_minutes = 10, $orders_period_in_minutes = 7200)
    {
        if (!array_key_exists($user_email, self::$user_last_order_cache)) {
            $current_time = current_time('timestamp');
            $period_start_time = $current_time - ($orders_period_in_minutes * MINUTE_IN_SECONDS);
            $order_info = null;

            try {
                $orders = wc_get_orders([
                    'customer' => $user_email,
                    'limit' => 1,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'date_query' => [
                        'after' => date('Y-m-d H:i:s', $period_start_time),
                    ],
                ]);

                if ($orders) {
                    $order = $orders[0];
                    $order_info = [
                        'id' => $order->get_id(),
                        'status' => $order->get_status(),
                        'is_paid' => $order->is_paid(),
                        'timestamp' => $order->get_date_created()->getOffsetTimestamp(),
                        'created_str' => $order->get_date_created()->date('Y-m-d H:i:s')
                    ];
                }
            } catch (Exception $e) {
                error_log('Growtype Mail Error: Failed to fetch order - ' . $e->getMessage());
            }

            self::$user_last_order_cache[$user_email] = $order_info;
        }

        $order_info = self::$user_last_order_cache[$user_email];
        
        if (!$order_info) {
            return null;
        }

        $current_time = current_time('timestamp');
        $min_time_threshold = $current_time - ($min_age_in_minutes * MINUTE_IN_SECONDS);

        if (!$order_info['is_paid'] && $order_info['timestamp'] < $min_time_threshold) {
             return $order_info['id'];
        }

        return null;
    }

    public static function get_abandoned_cart_purchase_url($user_email, $min_age_in_minutes = 10, $orders_period_in_minutes = 7200)
    {
        $order_id = self::get_abandoned_cart_order($user_email, $min_age_in_minutes, $orders_period_in_minutes);

        if (empty($order_id)) {
            return null;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return null;
        }

        return $order->get_checkout_payment_url();
    }

    /**
     * Efficiently find all paid descendant orders (children, grandchildren, etc.)
     * of a given root order ID.
     *
     * @return WC_Order[]
     */
    public static function get_associated_descendants(WC_Order $order): array
    {
        $root_id     = $order->get_id();
        $customer_id = $order->get_customer_id();
        $email       = $order->get_billing_email();

        $args = [
            'status'       => wc_get_is_paid_statuses(),
            'limit'        => -1,
            'meta_key'     => 'parent_order_id',
            'meta_compare' => 'EXISTS',
            'return'       => 'objects',
        ];

        if ($customer_id) {
            $args['customer_id'] = $customer_id;
        } elseif ($email) {
            $args['billing_email'] = $email;
        }

        $candidate_orders = wc_get_orders($args);
        if (empty($candidate_orders)) {
            return [];
        }

        $relationship_map = [];
        foreach ($candidate_orders as $candidate) {
            $parent_id = (int) $candidate->get_meta('parent_order_id', true);
            if ($parent_id) {
                $relationship_map[$parent_id][] = $candidate;
            }
        }

        $descendants   = [];
        $stack         = [$root_id];
        $processed_ids = [];

        while (!empty($stack)) {
            $current_id = array_pop($stack);
            if (isset($processed_ids[$current_id])) {
                continue;
            }
            $processed_ids[$current_id] = true;

            if (isset($relationship_map[$current_id])) {
                foreach ($relationship_map[$current_id] as $child_order) {
                    $descendants[] = $child_order;
                    $stack[]       = $child_order->get_id();
                }
            }
        }

        return $descendants;
    }

    public static function growtype_wc_get_items_with_upsells($order, $types = 'line_item')
    {
        $items       = $order->get_items($types);
        $descendants = self::get_associated_descendants($order);

        foreach ($descendants as $child_order) {
            foreach ($child_order->get_items($types) as $child_item) {
                $items[] = $child_item;
            }
        }

        return $items;
    }

    public static function growtype_wc_get_order_totals_with_upsells(WC_Order $order)
    {
        $subtotal    = (float) $order->get_subtotal();
        $total       = (float) $order->get_total();
        $descendants = self::get_associated_descendants($order);

        foreach ($descendants as $child_order) {
            $subtotal += (float) $child_order->get_subtotal();
            $total    += (float) $child_order->get_total();
        }

        return [
            'cart_subtotal' => [
                'label' => __('Subtotal:'),
                'value' => wc_price($subtotal, ['currency' => $order->get_currency()]),
            ],
            'order_total'   => [
                'label' => __('Total:'),
                'value' => wc_price($total, ['currency' => $order->get_currency()]),
            ],
        ];
    }

    public static function get_user_last_thank_you_url($user_id = null)
    {
        $user_id = !empty($user_id) ? $user_id : get_current_user_id();

        if (!$user_id) {
            return null;
        }

        $order = wc_get_customer_last_order($user_id);

        if (!$order) {
            return null;
        }

        return $order->get_checkout_order_received_url();
    }
}
