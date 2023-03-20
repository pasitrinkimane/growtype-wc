<?php

/**
 * Woo-checkout-field-editor
 */
if (class_exists('THWCFD')) {
    include('Plugins/woo-checkout-field-editor/woo-checkout-field-editor.php');
}

/**
 * woo-payment-gateway / braintree payments
 */
if (class_exists('WC_Braintree_Manager')) {
    include('Plugins/woo-payment-gateway/braintree.php');
}

/**
 * woo-payment-gateway-paysera
 */
if (class_exists('Wc_Paysera_Init')) {
    include('Plugins/woo-payment-gateway-paysera/paysera.php');
}
