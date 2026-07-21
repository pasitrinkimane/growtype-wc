<?php

/**
 * Apply coupon discount to sale price
 */
add_filter('woocommerce_product_get_sale_price', function ($sale_price, $product) {
    // Skip in admin or if cart not available
    if (is_admin() || !function_exists('WC') || !WC()->cart) {
        return $sale_price;
    }

    $applied_coupons = WC()->cart->get_applied_coupons();

    if (empty($applied_coupons)) {
        return $sale_price;
    }

    return growtype_wc_price_apply_coupon_discount($product->get_id(), $sale_price, $applied_coupons);
}, 20, 2);

/**
 * Sale flash product single page
 */
add_action('wp_loaded', function () {
    if (!get_theme_mod('woocommerce_product_page_shop_loop_item_price', true)) {
        remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    }
});

function growtype_wc_apply_coupon_discount($price, $coupon)
{
    $discount_type = $coupon->get_discount_type();
    $discount_amount = $coupon->get_amount();

    if ($discount_type === 'percent') {
        $price = $price - ($price * ($discount_amount / 100));
    } elseif ($discount_type === 'fixed_product') {
        $price = max(0, $price - $discount_amount);
    }

    return $price;
}
