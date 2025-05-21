<?php

/**
 * Order item
 */
add_filter('woocommerce_order_item_name', function ($get_name, $item, $is_visible) {

    $access_disabled = get_theme_mod('woocommerce_product_page_access_disabled');

    if ($access_disabled) {
        $get_name = $item->get_name();
    }

    $featured_image = get_the_post_thumbnail($item->get_product_id(), 'thumbnail');

    return $featured_image . $get_name;
}, 0, 3);

/*
 * Order details
 */
if (!function_exists('growtype_wc_order_details_enabled')) {
    function growtype_wc_order_details_enabled()
    {
        $order_details_enabled = get_theme_mod('woocommerce_thankyou_page_order_details', true);

        return apply_filters('growtype_wc_order_details_enabled', $order_details_enabled);
    }
}
