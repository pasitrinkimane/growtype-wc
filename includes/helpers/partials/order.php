<?php

/**
 * @return int[]|WP_Post[]
 */
function growtype_wc_get_user_orders($user_id = null, $args = [])
{
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    // Default query parameters
    $default_args = [
        'customer_id' => $user_id,
        'limit' => -1, // get all
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => array_keys(wc_get_order_statuses()),
        'return' => 'objects', // or 'ids' for IDs only
    ];

    // Allow custom overrides
    $query_args = wp_parse_args($args, $default_args);

    return wc_get_orders($query_args);
}

/**
 * @return bool|WC_Order|WC_Order_Refund
 */
function growtype_wc_get_user_first_order()
{
    $order = isset(growtype_wc_get_user_orders()[0]) ? growtype_wc_get_user_orders()[0] : false;

    return $order;
}

/***
 * @param $order
 * @return mixed|null
 * @throws WC_Data_Exception
 */
function growtype_wc_order_update_subscriptions($order_id, $params = [])
{
    $existing_subscriptions = growtype_wc_get_subscriptions([
        'order_id' => $order_id,
    ]);

    if (!empty($existing_subscriptions)) {
        foreach ($existing_subscriptions as $subscription) {
            foreach ($params as $param_key => $param_value) {
                update_post_meta($subscription->ID, $param_key, $param_value);
            }
        }
    }

    return $existing_subscriptions;
}



function growtype_wc_get_order_checkout_url($order_id)
{
    $order = wc_get_order($order_id);

    if (empty($order)) {
        return null;
    }

    $checkout_url = wc_get_checkout_url();

    $payment_provider_checkout_url = $order->get_meta('payment_provider_checkout_url');

    if (!empty($payment_provider_checkout_url)) {
        $checkout_url = $payment_provider_checkout_url;
    }

    $checkout_url = apply_filters('growtype_wc_get_order_checkout_url', $checkout_url, $order_id, $payment_provider_checkout_url);

    return $checkout_url;
}
