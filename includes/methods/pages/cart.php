<?php

/**
 * Coupon scripts
 */
add_action('wp_enqueue_scripts', 'growtype_wc_coupon_scripts_styles');
function growtype_wc_coupon_scripts_styles()
{
    $coupon_scripts_initiated = class_exists('woocommerce') && (is_cart() || is_checkout());
    $coupon_scripts_initiated = apply_filters('growtype_wc_coupon_scripts_initiated', $coupon_scripts_initiated);

    if ($coupon_scripts_initiated) {
        if (wc_coupons_enabled()) {
            growtype_wc_enqueue_coupon_scripts();
        }
    }
}

function growtype_wc_enqueue_coupon_scripts()
{
    wp_enqueue_script('wc-coupon', GROWTYPE_WC_URL_PUBLIC . '/scripts/wc-coupon.js', [], GROWTYPE_WC_VERSION, true);
}

/**
 *
 */
add_filter('woocommerce_cart_item_name', 'growtype_wc_cart_item_name');
function growtype_wc_cart_item_name($text)
{
    return __($text);
}
