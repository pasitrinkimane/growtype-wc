<?php

/**
 * @return int[]|WP_Post[]
 */
function growtype_wc_get_user_orders()
{
    $customer_orders = get_posts(array (
        'numberposts' => -1,
        'order' => 'ASC',
        'meta_key' => '_customer_user',
        'meta_value' => get_current_user_id(),
        'post_type' => wc_get_order_types(),
        'post_status' => array_keys(wc_get_order_statuses()),
    ));

    return $customer_orders;
}

/**
 * @return bool|WC_Order|WC_Order_Refund
 */
function growtype_wc_get_user_first_order()
{
    $order = isset(growtype_wc_get_user_orders()[0]) ? wc_get_order(growtype_wc_get_user_orders()[0]->ID) : false;

    return $order;
}

/***
 * @param $order
 * @return mixed|null
 * @throws WC_Data_Exception
 */
function growtype_wc_order_get_subscription_order($order_id)
{
    $order = wc_get_order($order_id);

    if ($order && !empty($order->get_items())) {
        foreach ($order->get_items() as $item_id => $item) {
            $is_subscription = growtype_wc_product_is_subscription($item->get_product_id());
            if ($is_subscription) {
                $subscription_order = growtype_wc_create_subscription_order_object([
                    'status' => Growtype_Wc_Subscription::STATUS_ACTIVE,
                    'order_id' => $order->get_id(),
                    'product_id' => $item->get_product_id()
                ]);

                if (is_wp_error($subscription_order)) {
                    error_log(sprintf('Product id: %s. Error:%s', $item->get_product_id(), $subscription_order->get_error_message()));
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
function growtype_wc_order_contains_subscription_order($order_id)
{
    $subscription = growtype_wc_order_get_subscription_order($order_id);

    return !empty($subscription);
}

/**
 * @param $subscription
 * @return mixed|null
 */
function growtype_wc_order_is_subscription_order($order_id)
{
    $order = wc_get_order($order_id);

    if (growtype_wc_order_contains_subscription_order($order_id) || (is_object($order) && is_a($order, 'WC_Subscription'))) {
        $is_subscription = true;
    } else {
        $is_subscription = false;
    }

    return apply_filters('growtype_wc_is_subscription', $is_subscription, $order);
}

function growtype_wc_get_order_active_subscriptions($order_id)
{
    $posts = growtype_wc_get_subscriptions(Growtype_Wc_Subscription::STATUS_ACTIVE);

    $subscriptions = [];
    foreach ($posts as $post) {
        if ($post->order_id === $order_id) {
            array_push($subscriptions, $post);
        }
    }

    return $subscriptions;
}

function growtype_wc_get_order_checkout_url($order_id)
{
    $order = wc_get_order($order_id);

    $checkout_url = wc_get_checkout_url();

    $payment_provider_checkout_url = $order->get_meta('stripe_checkout_url');

    if (!empty($payment_provider_checkout_url)) {
        $checkout_url = $payment_provider_checkout_url;
    }

    $checkout_url = apply_filters('growtype_wc_get_order_checkout_url', $checkout_url, $order_id, $payment_provider_checkout_url);

    return $checkout_url;
}
