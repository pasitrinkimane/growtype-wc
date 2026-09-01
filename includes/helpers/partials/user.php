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
 * Determine whether a user has at least one paid order.
 *
 * WooCommerce's cached customer order count avoids an order query for users
 * with no orders. The paid-order result is then cached for the request so
 * repeated UI components do not query WooCommerce again.
 */
function growtype_wc_user_has_paid_orders($user_id = null): bool
{
    $user_id = !empty($user_id) ? absint($user_id) : get_current_user_id();

    if ($user_id <= 0 || !function_exists('wc_get_orders') || !function_exists('wc_get_is_paid_statuses')) {
        return false;
    }

    static $has_paid_orders_cache = [];

    if (array_key_exists($user_id, $has_paid_orders_cache)) {
        return $has_paid_orders_cache[$user_id];
    }

    if (function_exists('wc_get_customer_order_count') && wc_get_customer_order_count($user_id) < 1) {
        return $has_paid_orders_cache[$user_id] = false;
    }

    $has_paid_orders = !empty(wc_get_orders([
        'customer' => $user_id,
        'status' => wc_get_is_paid_statuses(),
        'limit' => 1,
        'return' => 'ids',
    ]));

    return $has_paid_orders_cache[$user_id] = (bool) apply_filters(
        'growtype_wc_user_has_paid_orders',
        $has_paid_orders,
        $user_id
    );
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

/**
 * Render or get the account avatar HTML for a given user.
 * Displays uploaded profile picture if available, or filtered default avatar HTML (SVG).
 *
 * @param int|null $user_id User ID (defaults to current user)
 * @param int $size Avatar size in pixels (default 54)
 * @return string HTML output for the avatar
 */
function growtype_wc_get_account_avatar_html($user_id = null, int $size = 54, ?string $color = null): string
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();

    $profile_picture = $user_id ? get_user_meta($user_id, "profile_picture", true) : "";
    $has_custom_photo = !empty($profile_picture);

    if ($has_custom_photo) {
        $html = sprintf(
            '<div style="width:%1$dpx;height:%1$dpx;border-radius:50%%;background-image: url(\'%2$s\');background-size: cover;background-position: center;background-repeat: no-repeat;" class="account-img profile-picture"></div>',
            $size,
            esc_url($profile_picture)
        );
    } else {
        $svg = function_exists('growtype_get_default_avatar_svg')
            ? growtype_get_default_avatar_svg($size, $color)
            : sprintf(
                '<svg width="%1$d" height="%1$d" viewBox="0 0 128 128" version="1.1" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="account avatar"><g><circle cx="64" cy="64" r="64" style="%2$s"></circle><g><path fill="#fff" d="M103,102.1388 C93.094,111.92 79.3504,118 64.1638,118 C48.8056,118 34.9294,111.768 25,101.7892 L25,95.2 C25,86.8096 31.981,80 40.6,80 L87.4,80 C96.019,80 103,86.8096 103,95.2 L103,102.1388 Z"></path><path fill="#fff" d="M63.9961647,24 C51.2938136,24 41,34.2938136 41,46.9961647 C41,59.7061864 51.2938136,70 63.9961647,70 C76.6985159,70 87,59.7061864 87,46.9961647 C87,34.2938136 76.6985159,24 63.9961647,24"></path></g></g></svg>',
                $size,
                !empty($color) ? sprintf('fill: %s;', esc_attr($color)) : 'fill: var(--gc-main-color, #ff9000)'
            );

        $default_html = sprintf('<div class="account-img">%s</div>', $svg);

        $html = apply_filters(
            "growtype_wc_default_account_avatar_html",
            $default_html,
            $user_id,
            $size
        );
    }

    return apply_filters(
        "growtype_wc_account_avatar_html",
        $html,
        $user_id,
        $size,
        $has_custom_photo
    );
}
