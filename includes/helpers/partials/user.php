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
    if (growtype_child_all_premium_features_unlocked()) return true;

    $user_id = $user_id ?: get_current_user_id();

    if (!$user_id) return false;

    // 1. Static request-level cache
    static $subscription_check_cache = [];
    if (isset($subscription_check_cache[$user_id])) {
        return $subscription_check_cache[$user_id];
    }

    // 2. Persistent transient cache (5 minutes)
    $transient_name = 'growtype_wc_user_has_active_sub_' . $user_id;
    $cached_status = get_transient($transient_name);

    if ($cached_status !== false) {
        $has_active = $cached_status === 'yes';
        $subscription_check_cache[$user_id] = $has_active;
        return $has_active;
    }

    // 3. Lightweight DB call with LIMIT 1
    $subscriptions = growtype_wc_get_subscriptions([
        'status' => 'active',
        'user_id' => $user_id,
        'limit' => 1,
    ]);

    $has_active = !empty($subscriptions);

    // Save to both caches
    set_transient($transient_name, $has_active ? 'yes' : 'no', 5 * MINUTE_IN_SECONDS);
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
