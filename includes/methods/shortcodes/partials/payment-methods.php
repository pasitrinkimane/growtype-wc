<?php

/**
 * Woocommerce payment methods shortcode
 */
add_shortcode('growtype_wc_payment_methods_icons', 'growtype_wc_payment_methods_shorcode');
function growtype_wc_payment_methods_shorcode($atts)
{
    $params = shortcode_atts(array (
        'justify_content' => 'flex-start',
        'disabled_methods' => '',
        'icons' => [
            'visa' => [
                'class' => 'visa',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/visa.svg',
            ],
            'maestro' => [
                'class' => 'maestro',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/maestro.svg',
            ],
            'mastercard' => [
                'class' => 'mastercard',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/mastercard.svg',
            ],
            'paypal' => [
                'class' => 'paypal',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/paypal.svg',
            ],
            'stripe' => [
                'class' => 'stripe',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/stripe.svg',
            ],
            'american_express' => [
                'class' => 'american-express',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/american-express.svg',
            ],
            'verisign' => [
                'class' => 'verisign',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/verisign.svg',
            ],
            'mcafee' => [
                'class' => 'mcafee',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/mcafee.svg',
            ]
        ],
    ), $atts);

    $params['disabled_methods'] = !empty($params['disabled_methods']) ? explode(',', $params['disabled_methods']) : [];

    $params = apply_filters('growtype_wc_payment_methods_icons_params', $params);

    if (!empty($params['disabled_methods'])) {
        foreach ($params['disabled_methods'] as $method) {
            if (isset($params['icons'][$method])) {
                unset($params['icons'][$method]);
            }
        }
    }

    return growtype_wc_include_view('shortcodes.payment-methods', [
        'params' => $params,
        'icons' => $params['icons'],
    ]);
}
