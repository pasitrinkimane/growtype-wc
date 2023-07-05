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
    if (!get_theme_mod('woocommerce_product_preview_sale_badge', true)) {
        remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10);
    }
});

/**
 * Sale flash product loop
 */
add_filter('woocommerce_sale_flash', 'growtype_wc_woocommerce_sale_flash', 20, 3);
function growtype_wc_woocommerce_sale_flash($html, $post, $product)
{
    $sale_badge_as_percentage = get_theme_mod('woocommerce_product_preview_sale_badge_as_percentage', false);

    if (!$sale_badge_as_percentage) {
        return $html;
    }

    if ($product->is_type('variable')) {
        $percentages = array ();

        $prices = $product->get_variation_prices();

        foreach ($prices['price'] as $key => $price) {
            if ($prices['regular_price'][$key] !== $price) {
                // Calculate and set in the array the percentage for each variation on sale
                $percentages[] = round(100 - (floatval($prices['sale_price'][$key]) / floatval($prices['regular_price'][$key]) * 100));
            }
        }

        $percentage = max($percentages) . '%';
    } elseif ($product->is_type('grouped')) {
        $percentages = array ();
        $children_ids = $product->get_children();

        foreach ($children_ids as $child_id) {
            $child_product = wc_get_product($child_id);

            $regular_price = (float)$child_product->get_regular_price();
            $sale_price = (float)$child_product->get_sale_price();

            if ($sale_price != 0 || !empty($sale_price)) {
                $percentages[] = round(100 - ($sale_price / $regular_price * 100));
            }
        }

        $percentage = max($percentages) . '%';
    } else {
        $regular_price = (float)$product->get_regular_price();
        $sale_price = (float)$product->get_sale_price();

        if ($sale_price != 0 || !empty($sale_price)) {
            $percentage = round(100 - ($sale_price / $regular_price * 100)) . '%';
        } else {
            return $html;
        }
    }

    return '<span class="onsale">-' . $percentage . '</span>';
}
