<?php

/**
 * Product classes
 */
add_filter('woocommerce_post_class', 'growtype_wc_woocommerce_post_class', 9999, 3);
function growtype_wc_woocommerce_post_class($classes, $product)
{
    $product_preview_style = Growtype_Wc_Product::preview_style();

    if (!empty($product_preview_style)) {
        $classes[] = $product_preview_style;
    }

    $preview_style = get_post_meta($product->get_id(), '_preview_style', true);

    if ($preview_style !== 'default') {
        $classes[] = $preview_style;
    }

    $promo_label = get_post_meta($product->get_id(), '_promo_label', true);

    if (!empty($promo_label)) {
        $classes[] = 'has-promo-label';
    }

    if (growtype_wc_product_is_subscription($product->get_id())) {
        $classes[] = 'subscription';
    }

    return $classes;
}

/**
 * Product html
 */
add_filter('woocommerce_blocks_product_grid_item_html', 'growtype_wc_woocommerce_blocks_product_grid_item_html', 9999, 3);
function growtype_wc_woocommerce_blocks_product_grid_item_html($content, $data, $product)
{
    $preview_style = get_post_meta($product->get_id(), '_preview_style', true);

    if ($preview_style === 'plan') {
        /**
         * Remove placeholder image
         */
        if (str_contains($data->image, 'placeholder')) {
            $data->image = null;
        }

        $data->description = $product->get_short_description();
        $data->permalink = '';
    }

    /**
     * Change button text if different
     */
    $default_add_to_cart_text = '';
    $default_add_to_cart_text = apply_filters('woocommerce_product_single_add_to_cart_text', $default_add_to_cart_text);

    if (!empty(Growtype_Wc_Product::get_add_to_cart_btn_label($product))) {
        $data->button = str_replace($default_add_to_cart_text, Growtype_Wc_Product::get_add_to_cart_btn_label($product), $data->button);
    }

    if (Growtype_Wc_Product::price_is_hidden($product->get_id())) {
        $data->price = null;
    }

    /**
     * Promo label
     */
    if (!empty(Growtype_Wc_Product::get_promo_label_formatted($product->get_id()))) {
        $data->promo_label = Growtype_Wc_Product::get_promo_label_formatted($product->get_id());
    }

    /**
     * Price details
     */
    $price_details = Growtype_Wc_Product::get_price_details($product->get_id());

    if (!empty($price_details)) {
        $data->price_details = $price_details;
    }

    return growtype_wc_include_view('woocommerce.blocks.product-preview', ['data' => $data]);
}
