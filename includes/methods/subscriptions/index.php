<?php

class Growtype_Wc_Subscription
{
    const META_KEY = '_growtype_wc_subscription';
    const STATUS_ACTIVE = 'active';
    const STATUS_CANCELLED = 'cancelled';

    public function __construct()
    {
        add_action('init', array ($this, 'growtype_wc_register_subscription_post_type'));

        $this->load_partials();
    }

    function load_partials()
    {
        add_action('init', function () {
            if (class_exists('woocommerce')) {
                include_once 'partials/Growtype_Wc_Subscription_Order.php';
                new Growtype_Wc_Subscription_Order();
            }
        });
    }

    function growtype_wc_register_subscription_post_type()
    {
        register_post_type('growtype_wc_subs', array (
            'labels' => array (
                'name' => 'Subscriptions',
                'singular_name' => 'Subscription',
            ),
            'public' => true,
            'has_archive' => false,
            'menu_icon' => 'dashicons-cart',
            'supports' => array ('title'),
            'show_in_menu' => 'woocommerce'
        ));
    }

    public static function create_order_object($args = array ())
    {
        $now = gmdate('Y-m-d H:i:s');
        $order = (isset($args['order_id'])) ? wc_get_order($args['order_id']) : null;

        $product = (isset($args['product_id'])) ? wc_get_product($args['product_id']) : null;

        if (empty($product) && !empty($order)) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
            }
        }

        $default_args = array (
            'status' => '',
            'order_id' => 0,
            'customer_note' => null,
            'customer_id' => null,
            'start_date' => $args['date_created'] ?? $now,
            'date_created' => $now,
            'created_via' => '',
            'currency' => get_woocommerce_currency(),
            'prices_include_tax' => get_option('woocommerce_prices_include_tax'),
            'product_id' => isset($args['product_id']) ? $args['product_id'] : $product->get_id(),
        );

        // If we are creating a subscription from an order, we use some of the order's data as defaults.
        if ($order instanceof \WC_Order) {
            $default_args['customer_id'] = $order->get_user_id();
            $default_args['created_via'] = $order->get_created_via('edit');
            $default_args['currency'] = $order->get_currency('edit');
            $default_args['prices_include_tax'] = $order->get_prices_include_tax('edit') ? 'yes' : 'no';
            $default_args['date_created'] = growtype_wc_sub_get_datetime_utc_string($order->get_date_created('edit'));
        }

        $args = wp_parse_args($args, $default_args);

        if (!isset($args['billing_period'])) {
            $args['billing_period'] = growtype_wc_get_subscription_period($product->get_id());
        }

        if (!isset($args['billing_interval'])) {
            $args['billing_interval'] = growtype_wc_get_subscription_duration($product->get_id());
        }

        if (!isset($args['billing_price'])) {
            $args['billing_price'] = get_post_meta($product->get_id(), '_growtype_wc_subscription_price', true);
        }

        if (!isset($args['customer_note'])) {
            $args['customer_note'] = $order->get_customer_note();
        }

        if (!isset($args['title'])) {
            $args['title'] = $product->get_title();
        }

        /**
         * Check data
         */
        if (empty($args['status']) || !empty($args['status']) && !array_key_exists($args['status'], growtype_wc_get_subscription_statuses())) {
            return new WP_Error('woocommerce_invalid_subscription_status', __('Invalid subscription status given.', 'woocommerce-subscriptions'));
        }

        if (!is_string($args['date_created']) || false === growtype_wc_sub_datetime_mysql_format($args['date_created'])) {
            return new WP_Error('woocommerce_subscription_invalid_date_created_format', _x('Invalid created date. The date must be a string and of the format: "Y-m-d H:i:s".', 'Error message while creating a subscription', 'woocommerce-subscriptions'));
        }

        if (growtype_wc_sub_date_to_time($args['date_created']) > time()) {
            return new WP_Error('woocommerce_subscription_invalid_date_created', _x('Subscription created date must be before current day.', 'Error message while creating a subscription', 'woocommerce-subscriptions'));
        }

        if (!is_string($args['start_date']) || false === growtype_wc_sub_datetime_mysql_format($args['start_date'])) {
            return new WP_Error('woocommerce_subscription_invalid_start_date_format', _x('Invalid date. The date must be a string and of the format: "Y-m-d H:i:s".', 'Error message while creating a subscription', 'woocommerce-subscriptions'));
        }

        if (empty($args['billing_period']) || !array_key_exists(strtolower($args['billing_period']), growtype_wc_sub_get_subscription_period_strings())) {
            return new WP_Error('woocommerce_subscription_invalid_billing_period', __('Invalid subscription billing period given.', 'woocommerce-subscriptions'));
        }

        if (empty($args['billing_interval']) || !is_numeric($args['billing_interval']) || absint($args['billing_interval']) <= 0) {
            return new WP_Error('woocommerce_subscription_invalid_billing_interval', __('Invalid subscription billing interval given. Must be an integer greater than 0.', 'woocommerce-subscriptions'));
        }

        $subscription = new \Growtype_Wc_Subscription_Order();

        $subscription->set_status($args['status']);

        $subscription->set_title($args['title'] ?? sprintf('Order id: %s', $order->get_id()));
        $subscription->set_billing_price($args['billing_price']);
        $subscription->set_customer_note($args['customer_note']);
        $subscription->set_customer_id($args['customer_id']);
        $subscription->set_date_created($args['date_created']);
        $subscription->set_created_via($args['created_via']);
        $subscription->set_currency($args['currency']);
        $subscription->set_prices_include_tax('no' !== $args['prices_include_tax']);
        $subscription->set_billing_period($args['billing_period']);
        $subscription->set_billing_interval(absint($args['billing_interval']));
        $subscription->set_start_date($args['start_date']);
        $subscription->set_product_id($args['product_id']);

        if ($args['order_id'] > 0) {
            $subscription->set_parent_id($args['order_id']);
        }

        $subscription = apply_filters('growtype_wc_created_subscription', $subscription);

        do_action('growtype_wc_create_subscription_order_object', $subscription);

        return $subscription;
    }

    public static function growtype_wc_order_get_subscription_order($order_id)
    {
        $order = wc_get_order($order_id);

        if ($order && !empty($order->get_items())) {
            foreach ($order->get_items() as $product) {
                $product_id = $product->get_product_id();
                $is_subscription = growtype_wc_product_is_subscription($product_id);
                if ($is_subscription) {
                    $subscription_order = Growtype_Wc_Subscription::create_order_object([
                        'status' => 'active',
                        'order_id' => $order->get_id(),
                        'product_id' => $product_id
                    ]);

                    if (is_wp_error($subscription_order)) {
                        error_log(sprintf('Product id: %s. Error:%s', $product->get_product_id(), $subscription_order->get_error_message()));
                        return null;
                    }

                    return $subscription_order;
                }
            }
        }

        return null;
    }

    /**
     * @param $order_id
     * @return bool
     * @throws WC_Data_Exception
     */
    public static function contains_subscription_order($order_id)
    {
        $subscription = Growtype_Wc_Subscription::growtype_wc_order_get_subscription_order($order_id);

        return !empty($subscription);
    }

    /**
     * @param $subscription
     * @return mixed|null
     */
    public static function is_subscription_order($order_id)
    {
        $order = wc_get_order($order_id);

        if (Growtype_Wc_Subscription::contains_subscription_order($order_id) || (is_object($order) && is_a($order, 'WC_Subscription'))) {
            $is_subscription = true;
        } else {
            $is_subscription = false;
        }

        return apply_filters('growtype_wc_is_subscription', $is_subscription, $order);
    }

    public static function active_orders($order_id)
    {
        return growtype_wc_get_subscriptions([
            'status' => Growtype_Wc_Subscription::STATUS_ACTIVE,
            'order_id' => $order_id,
            'limit' => 5 // Usually should be only 1
        ]);
    }

    public static function change_status($sub_id, $status)
    {
        update_post_meta($sub_id, '_status', $status);

        $user_id = get_post_meta($sub_id, '_user_id', true);
        if ($user_id) {
            delete_transient('growtype_wc_user_has_active_sub_' . $user_id);
        }
    }

    public static function status($sub_id)
    {
        return get_post_meta($sub_id, '_status', true);
    }

    public static function manage_url($sub_id)
    {
        return growtype_wc_get_account_subpage_url('subscriptions') . '?action=manage&subscription=' . $sub_id;
    }
}
