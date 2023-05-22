<?php

/**
 * Sale flash product single page
 */
add_action('wp_loaded', function () {
    remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10);

    if (get_theme_mod('woocommerce_product_page_sale_flash', true)) {
        add_action('woocommerce_single_product_summary', 'woocommerce_show_product_sale_flash', 1);
    }
});

/**
 * Sale flash product loop
 */
add_action('wp_loaded', function () {
    if (!get_theme_mod('woocommerce_product_preview_sale_badge', false)) {
        remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10);
    }
});
