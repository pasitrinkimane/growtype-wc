<?php

/**
 * Woocommerce payment methods shortcode
 */
add_shortcode('growtype_wc_payment_methods_icons', 'growtype_wc_payment_methods_shorcode');
function growtype_wc_payment_methods_shorcode($atts)
{
    $params = shortcode_atts(array (
        'justify-content' => 'flex-start',
    ), $atts);

    $items = [
        'visa' => [
            'class' => 'border',
            'img' => GROWTYPE_WC_URL_PUBLIC . 'icons/credit-cards/visa.svg',
        ],
        'maestro' => [
            'class' => 'border',
            'img' => GROWTYPE_WC_URL_PUBLIC . 'icons/credit-cards/maestro.svg',
        ],
        'mastercard' => [
            'class' => 'border',
            'img' => GROWTYPE_WC_URL_PUBLIC . 'icons/credit-cards/mastercard.svg',
        ],
        'paypal' => [
            'class' => 'border custom',
            'img' => GROWTYPE_WC_URL_PUBLIC . 'icons/credit-cards/paypal.svg',
        ]
    ];

    return growtype_wc_include_view('shortcodes.payment-methods', [
        'params' => $params,
        'items' => $items,
    ]);
}
