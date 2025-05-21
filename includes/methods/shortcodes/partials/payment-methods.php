<?php

/**
 * Woocommerce payment methods shortcode
 */
add_shortcode('growtype_wc_payment_methods_icons', 'growtype_wc_payment_methods_shorcode');
function growtype_wc_payment_methods_shorcode($atts)
{
    $params = shortcode_atts(array (
        'justify_content' => 'flex-start',
        'disabled_icons' => '',
        'enabled_icons' => '',
        'icons' => [
            'visa' => [
                'class' => 'visa',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/visa.svg',
            ],
            'visa_white' => [
                'class' => 'visa-white',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/visa-white.svg',
            ],
            'maestro' => [
                'class' => 'maestro',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/maestro.svg',
            ],
            'mastercard' => [
                'class' => 'mastercard',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/mastercard.svg',
            ],
            'mastercard_white' => [
                'class' => 'mastercard-white',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/mastercard-white.svg',
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
            ],
            'coinbase' => [
                'class' => 'coinbase',
                'url' => GROWTYPE_WC_URL_PUBLIC . 'icons/payment-methods/coinbase.svg',
            ]
        ],
    ), $atts);

    $params['disabled_icons'] = !empty($params['disabled_icons']) ? explode(',', $params['disabled_icons']) : [];
    $params['enabled_icons'] = !empty($params['enabled_icons']) ? explode(',', $params['enabled_icons']) : [];

    $params = apply_filters('growtype_wc_payment_methods_icons_params', $params);

    if (!empty($params['enabled_icons'])) {
        $enabled_icons = [];
        foreach ($params['enabled_icons'] as $method) {
            if (isset($params['icons'][$method])) {
                $enabled_icons[$method] = $params['icons'][$method];
            }
        }
        $params['icons'] = $enabled_icons;
    }

    if (!empty($params['disabled_icons'])) {
        foreach ($params['disabled_icons'] as $method) {
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
