<?php

/**
 * Sale flash product single page
 */
add_action('wp_loaded', function () {
    if (!get_theme_mod('woocommerce_product_page_shop_loop_item_title', true)) {
        remove_action("woocommerce_shop_loop_item_title", "woocommerce_template_loop_product_title", 10);
    }
});

add_action('woocommerce_shop_loop_item_title', function () {
    echo '<div class="content-wrapper">';
}, 0);

add_action('woocommerce_after_shop_loop_item_title', function () {
    echo '</div>';
});
