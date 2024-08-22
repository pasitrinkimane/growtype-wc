<?php

/**
 * Sale flash product single page
 */
add_action('wp_loaded', function () {
    if (!get_theme_mod('woocommerce_product_page_shop_loop_item_price', true)) {
        remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    }
});

add_filter('woocommerce_get_price_html', 'growtype_wc_woocommerce_extend_price_html', 10, 2);
function growtype_wc_woocommerce_extend_price_html($price, $product)
{
    if (growtype_wc_product_is_subscription($product->get_id())) {
        $period = growtype_wc_get_subcription_period($product->get_id());

        $price = $price . '<div class="duration-details"><span class="e-separator">/</span><span class="e-duration">' . $period . '</span></div>';
    }

    return $price;
}
