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

add_action('woocommerce_before_shop_loop_item', function () {
    echo Growtype_Wc_Product::get_promo_label_formatted();
});

add_action('woocommerce_after_shop_loop_item_title', function () {
    echo Growtype_Wc_Product::get_price_details_formatted();
    echo Growtype_Wc_Product::get_extra_details_formatted();
});

add_action('woocommerce_after_shop_loop_item_title', function () {
    echo '</div>';
});
