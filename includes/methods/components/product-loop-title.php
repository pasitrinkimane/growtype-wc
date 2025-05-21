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

/**
 * Short description
 */
add_action('woocommerce_after_shop_loop_item_title', function () {
    $short_description_enabled = get_theme_mod('woocommerce_product_preview_show_short_description', false);

    if ($short_description_enabled) {
        $product = wc_get_product();
        $short_description = $product->get_short_description();

        echo '<div class="short-description">' . $short_description . '</div>';
    }
}, 0);

add_action('woocommerce_before_shop_loop_item', function () {
    echo Growtype_Wc_Product::get_promo_label_formatted();
});

/**
 * Price details
 */
add_action('woocommerce_after_shop_loop_item_title', 'growtype_wc_after_shop_loop_item_title_price_details');

function growtype_wc_after_shop_loop_item_title_price_details()
{
    echo Growtype_Wc_Product::get_price_details_formatted();
}

/**
 * Extra details
 */
add_action('woocommerce_after_shop_loop_item_title', 'growtype_wc_after_shop_loop_item_title_extra_details');

function growtype_wc_after_shop_loop_item_title_extra_details()
{
    echo Growtype_Wc_Product::get_extra_details_formatted();
}

/**
 * Close title
 */
add_action('woocommerce_after_shop_loop_item_title', 'woocommerce_after_shop_loop_item_title_close_title');

function woocommerce_after_shop_loop_item_title_close_title()
{
    echo '</div>';
}
