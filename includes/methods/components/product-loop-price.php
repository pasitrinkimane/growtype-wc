<?php

/**
 * Sale flash product single page
 */
add_action('wp_loaded', function () {
    if (!get_theme_mod('woocommerce_product_page_shop_loop_item_price', true)) {
        remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    }
});
