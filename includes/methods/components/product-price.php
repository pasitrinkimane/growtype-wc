<?php

/**
 * Show smaller price for variable product
 */
add_filter('woocommerce_variable_sale_price_html', 'growtype_wc_variable_price_html', 10, 2);
add_filter('woocommerce_variable_price_html', 'growtype_wc_variable_price_html', 10, 2);
function growtype_wc_variable_price_html($price, $product)
{
    if (empty($product) || is_admin() && !wp_doing_ajax()) {
        return $price;
    }

    $show_single_price = get_theme_mod('woocommerce_product_page_shop_loop_item_price_is_single', true);

    if ($show_single_price) {
        $variation_min_reg_price = $product->get_variation_regular_price('min', true);
        $variation_min_sale_price = $product->get_variation_sale_price('min', true);

        if ($product->is_on_sale() && !empty($variation_min_sale_price) && $variation_min_sale_price < $variation_min_reg_price) {
            if (!empty($variation_min_sale_price)) {
                $price = '<del class="strike">' . wc_price($variation_min_reg_price) . '</del><ins class="highlight">' . wc_price($variation_min_sale_price) . '</ins>';
            }
        } else {
            if (!empty($variation_min_reg_price)) {
                $price = '<ins class="highlight">' . wc_price($variation_min_reg_price) . '</ins>';
            } else {
                $price = '<ins class="highlight">' . wc_price($product->regular_price) . '</ins>';
            }
        }
    }

    $price_starts_from_text = get_theme_mod('woocommerce_product_page_shop_loop_item_price_starts_from_text');

    if (!empty($price_starts_from_text)) {
        $price = sprintf(__('' . $price_starts_from_text . ' %s', 'growtype-wc'), $price);
    }

    return $price;
}

/**
 * Show smaller price for variable product
 */
add_filter('wc_price', function ($return, $price, $args, $unformatted_price, $original_price) {
    if ((int)$price === 0) {
        return '<span class="is-free">Free</span>';
    }

    return $return;
}, 0, 5);
