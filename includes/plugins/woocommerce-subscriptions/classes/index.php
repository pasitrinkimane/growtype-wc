<?php

include_once(GROWTYPE_WC_PATH . '/includes/plugins/woocommerce-subscriptions/classes/partials/class-wc-order.php');
new Growtype_Wc_Plugin_Woocommerce_Subscriptions_Order();

/**
 * WC_Subscriptions
 */
if (!class_exists('WC_Subscriptions')) {
    include_once(GROWTYPE_WC_PATH . '/includes/plugins/woocommerce-subscriptions/classes/partials/class-wc-subscriptions.php');
    new WC_Subscriptions();
}

/**
 * WC_Subscriptions
 */
if (!class_exists('WC_Subscriptions_Cart')) {
    include_once(GROWTYPE_WC_PATH . '/includes/plugins/woocommerce-subscriptions/classes/partials/class-wc-subscriptions-cart.php');
    new WC_Subscriptions_Cart();
}

/**
 * WC_Subscriptions product
 */
if (!class_exists('WC_Subscriptions_Product')) {
    include_once(GROWTYPE_WC_PATH . '/includes/plugins/woocommerce-subscriptions/classes/partials/class-wc-subscriptions-product.php');
    new WC_Subscriptions_Product();
}

/**
 * WC_Subscriptions admin
 */
if (!class_exists('WC_Subscriptions_Admin')) {
    include_once(GROWTYPE_WC_PATH . '/includes/plugins/woocommerce-subscriptions/classes/partials/class-wc-subscriptions-admin.php');
    new WC_Subscriptions_Admin();
}
