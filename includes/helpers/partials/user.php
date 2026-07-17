<?php

/**
 * @return void
 */
function growtype_wc_get_user_subscriptions($user_id = null): array
{
    $user_id = !empty($user_id) ? $user_id : get_current_user_id();

    return growtype_wc_get_subscriptions([
        "user_id" => $user_id,
    ]);
}

function growtype_wc_user_has_active_subscription($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();

    if (!$user_id) {
        return false;
    }

    // 1. Static request-level cache
    static $subscription_check_cache = [];
    if (isset($subscription_check_cache[$user_id])) {
        return $subscription_check_cache[$user_id];
    }

    // 2. Persistent transient cache (5 minutes)
    $transient_name = "growtype_wc_user_has_active_sub_" . $user_id;
    $cached_status = get_transient($transient_name);

    if ($cached_status !== false) {
        $has_active = $cached_status === "yes";
        $has_active = apply_filters(
            "growtype_wc_user_has_active_subscription",
            $has_active,
            $user_id,
        );
        $subscription_check_cache[$user_id] = $has_active;
        return $has_active;
    }

    // 3. Lightweight DB call with LIMIT 1
    $subscriptions = growtype_wc_get_subscriptions([
        "status" => "active",
        "user_id" => $user_id,
        "limit" => 1,
    ]);

    $has_active = !empty($subscriptions);
    $has_active = apply_filters(
        "growtype_wc_user_has_active_subscription",
        $has_active,
        $user_id,
    );

    // Save to both caches
    set_transient(
        $transient_name,
        $has_active ? "yes" : "no",
        1 * MINUTE_IN_SECONDS,
    );

    $subscription_check_cache[$user_id] = $has_active;

    return $has_active;
}

/**
 * Get user portrait URLs from local cache or remote fallback.
 *
 * @param array $args {
 *     @type string $gender  'men'|'women'|'mix' (default: 'mix')
 *     @type int    $count   Number of portraits to return (default: 1)
 *     @type bool   $shuffle Randomize order (default: true)
 *     @type int    $size    Image size in px (default: 128)
 * }
 * @return array Array of portrait URLs
 */
function growtype_wc_get_user_portraits($args = [])
{
    $gender = strtolower($args["gender"] ?? "mix");
    $count = max(1, intval($args["count"] ?? 1));
    $shuffle = ($args["shuffle"] ?? true) !== false;
    $size = intval($args["size"] ?? 128);

    $plugin_url = GROWTYPE_WC_URL_PUBLIC . "images/users/";
    $plugin_path = GROWTYPE_WC_PATH . "public/images/users/";

    $genders =
        $gender === "mix"
            ? ["men", "women"]
            : [$gender === "women" ? "women" : "men"];

    $pool = [];

    foreach ($genders as $g) {
        $dir = $plugin_path . $g . "/";

        if (is_dir($dir)) {
            $files = array_values(
                array_filter(scandir($dir), function ($f) {
                    return $f !== "." &&
                        $f !== ".." &&
                        preg_match('/\.(jpg|jpeg|png|webp)$/i', $f);
                }),
            );

            foreach ($files as $file) {
                $pool[] = $plugin_url . $g . "/" . $file;
            }
        }
    }

    // Fallback to remote if no local portraits found
    if (empty($pool)) {
        for ($i = 0; $i < $count; $i++) {
            $g =
                $gender === "mix"
                    ? ($i % 2 === 0
                        ? "men"
                        : "women")
                    : ($gender === "women"
                        ? "women"
                        : "men");
            $idx = rand(0, 99);
            $pool[] = "https://randomuser.me/api/portraits/{$g}/{$idx}.jpg";
        }
    }

    if ($shuffle) {
        shuffle($pool);
    } elseif ($gender === 'mix' && !empty($pool)) {
        // Interleave men and women so both genders appear without shuffling
        $men = array_values(array_filter($pool, function ($url) {
            return strpos($url, '/men/') !== false;
        }));
        $women = array_values(array_filter($pool, function ($url) {
            return strpos($url, '/women/') !== false;
        }));

        $interleaved = [];
        $max = max(count($men), count($women));
        for ($i = 0; $i < $max; $i++) {
            if (isset($women[$i])) {
                $interleaved[] = $women[$i];
            }
            if (isset($men[$i])) {
                $interleaved[] = $men[$i];
            }
        }
        $pool = $interleaved;
    }

    return array_slice($pool, 0, $count);
}

function growtype_wc_user_has_purchased_product($product_id, $user_id = null)
{
    $user_id = !empty($user_id) ? $user_id : get_current_user_id();

    if (!$user_id || !$product_id) {
        return false;
    }

    // Fetch ALL paid/processing orders for that user
    $orders = wc_get_orders([
        "customer" => $user_id,
        "limit" => -1,
        "status" => wc_get_is_paid_statuses(),
        "return" => "ids",
    ]);

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
            if ((int) $item->get_product_id() === (int) $product_id) {
                return true;
            }
        }
    }

    return false;
}
