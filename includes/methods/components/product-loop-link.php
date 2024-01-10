<?php

/**
 * Disable product preview link to inner page
 */
add_action('init', function () {
    if (get_theme_mod('woocommerce_product_page_access_disabled', false)) {
        remove_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5);
    }
});
