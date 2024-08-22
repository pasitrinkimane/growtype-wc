<?php

/**
 * Add custom field summary
 */
add_action('wp_enqueue_scripts', 'growtype_wc_checkout_enqueue_scripts');
function growtype_wc_checkout_enqueue_scripts()
{
    $css = '';
    if (!get_theme_mod('woocommerce_checkout_order_review_table_show_subtotal', true)) {
        $css .= '
        .woocommerce-checkout-review-order-table .cart-subtotal {
            display:none!important;
        }';
    }

    if (!get_theme_mod('woocommerce_checkout_order_review_table_show_head', true)) {
        $css .= '
        .woocommerce-checkout-review-order-table thead {
            display:none;
        }
        .woocommerce-checkout-review-order-table .cart_item_detailed th {
           border-top:none!important;
        }
        .woocommerce-checkout-review-order-table .cart_item_detailed td {
           border-top:none!important;
        }
        ';
    }

    if (get_theme_mod('woocommerce_checkout_order_review_cart_item_style') === 'detailed') {
        $css .= '
        .woocommerce-checkout-review-order-table .cart_item:not(.cart_item_detailed) {
            display:none!important;
        }
        .woocommerce-checkout-review-order-table .cart_item_detailed {
                display: table-row;
        }';
    } else {
        $css .= '
        .woocommerce-checkout-review-order-table .cart_item_detailed {
            display:none;
        }';
    }

    if (!empty($css)) {
        wp_register_style('dma-inline-style', false);
        wp_enqueue_style('dma-inline-style');
        wp_add_inline_style('dma-inline-style', $css);
    }
}

/**
 * Locales data update
 */
add_filter('woocommerce_get_script_data', 'growtype_wc_get_script_data', 10, 2);
function growtype_wc_get_script_data($data, $handle)
{
    switch ($handle) :
        case 'wc-address-i18n':
            $locale_data = json_decode($data['locale'], true);
            $locale_data['LT']['state']['required'] = false;
            $data['locale'] = json_encode($locale_data);
            break;
    endswitch;

    return $data;
}

/**
 * Scripts
 */
add_action('wp_enqueue_scripts', 'growtype_wc_checkout_scripts');
function growtype_wc_checkout_scripts()
{
    if (class_exists('woocommerce') && is_checkout()) {
        wp_enqueue_script('wc-custom-checkout', GROWTYPE_WC_URL_PUBLIC . '/scripts/wc-checkout.js', [], GROWTYPE_WC_VERSION, true);
    }
}
