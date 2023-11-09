<?php

/**
 * Woo-checkout-field-editor
 */
if (class_exists('QTX_Translator')) {
    include('qtranslate/index.php');
}

/**
 * Woo-checkout-field-editor
 */
if (class_exists('THWCFD')) {
    include('woo-checkout-field-editor/woo-checkout-field-editor.php');
}

/**
 * woo-payment-gateway / braintree payments
 */
if (class_exists('WC_Braintree_Manager')) {
    include('woo-payment-gateway-braintree/index.php');
}

/**
 * woo-payment-gateway-paysera
 */
if (class_exists('Wc_Paysera_Init')) {
    include('woo-payment-gateway-paysera/paysera.php');
}

/**
 * woo-payment-gateway-paysera
 */
if (!class_exists('Growtype_Wc_Google_Sheets')) {
    include('google/Growtype_Wc_Google_Sheets.php');
}

/**
 * woo-payment-gateway-paysera
 */
if (class_exists('Growtype_Cron')) {
    include('growtype-cron/Growtype_Wc_Growtype_Cron.php');
    new Growtype_Wc_Cron();
}

/**
 * Yoast-seo
 */
if (class_exists('WPSEO_Options')) {
    include('yoast-seo/index.php');
}

/**
 * Woo Subscriptions
 */
if (function_exists('woocommerce_gateway_stripe') || class_exists('\WooCommerce\PayPalCommerce\PluginModule')) {
    include('woocommerce-subscriptions/index.php');
    new Growtype_Wc_WC_Subscriptions();
}

/**
 * Woo Subscriptions
 */
if (class_exists('\WooCommerce\PayPalCommerce\PluginModule')) {
    include('woocommerce-paypal-payments/index.php');
}
