<?php

/**
 * Free gateway
 */
include_once 'gateways/wc-gateway-free.php';
include_once 'gateways/wc-gateway-paypal.php';
include_once 'gateways/wc-gateway-cc.php';
include_once 'gateways/wc-gateway-stripe.php';

/**
 * Add payment method
 */
add_filter('woocommerce_payment_gateways', 'growtype_wc_payment_gateways');
function growtype_wc_payment_gateways($gateways)
{
    $gateways[] = 'Growtype_WC_Gateway_Free';
    $gateways[] = 'Growtype_WC_Gateway_Paypal';
    $gateways[] = 'Growtype_WC_Gateway_Cc';
    $gateways[] = 'Growtype_WC_Gateway_Stripe';

    return $gateways;
}

add_action('woocommerce_review_order_before_payment', function () {
    $current = WC()->session->get('chosen_payment_method');
    $gateways = WC()->payment_gateways()->payment_gateways();
    if ($current && isset($gateways[$current])) {
        $gateway = $gateways[$current];
        $gateway->chosen = false;
    }
}, 1);

/**
 *
 */
add_action('woocommerce_checkout_order_processed', function ($order_id, $posted_data, $order) {

    error_log('woocommerce_checkout_order_processed ---');

    if (!$order->is_paid()) {
        error_log('order not paid');
    }
}, 0, 3);
