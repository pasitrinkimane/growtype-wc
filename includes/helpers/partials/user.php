<?php

/**
 * @return void
 */
function growtype_wc_get_user_subscriptions($user_id = null): array
{
    $user_id = !empty($user_id) ? $user_id : get_current_user_id();

    return growtype_wc_get_subscriptions([
        'user_id' => $user_id
    ]);
}

function growtype_wc_user_has_active_subscription($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) return false;

    // Prevent redundant DB hits in the same request
    static $subscription_check_cache = [];

    if (isset($subscription_check_cache[$user_id])) {
        return $subscription_check_cache[$user_id];
    }

    // Lightweight DB call (ensure growtype_wc_get_subscriptions is optimized)
    $subscriptions = growtype_wc_get_subscriptions([
        'status' => 'active',
        'user_id' => $user_id,
    ]);

    $has_active = !empty($subscriptions);

    $subscription_check_cache[$user_id] = $has_active;
    return $has_active;
}

function growtype_wc_user_has_purchased_product($product_id, $user_id = null)
{
    $user_id = !empty($user_id) ? $user_id : get_current_user_id();

    if (!$user_id || !$product_id) {
        return false;
    }

    // Fetch ALL paid/processing orders for that user
    $orders = wc_get_orders(array (
        'customer' => $user_id,
        'limit' => -1,
        'status' => wc_get_is_paid_statuses(),
        'return' => 'ids',
    ));

    if (empty($orders)) {
        return false;
    }

    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            continue;
        }
        /** @var WC_Order_Item_Product $item */
        foreach ($order->get_items() as $item) {
            if ((int)$item->get_product_id() === (int)$product_id) {
                return true;
            }
        }
    }

    return false;
}
