<?php

/**
 * Add to cart message
 */
add_filter('wc_add_to_cart_message_html', '__return_false');

add_action('init', function () {
    $disable_notices = get_theme_mod('growtype_wc_disable_default_wc_notices', false);

    if ($disable_notices) {
        remove_action('woocommerce_before_single_product', 'wc_print_notices', 10);
        remove_action('woocommerce_before_cart', 'wc_print_notices', 10);
        remove_action('woocommerce_before_checkout_form', 'wc_print_notices', 10);
        remove_action('woocommerce_cart_is_empty', 'wc_print_notices', 10);
        remove_action('woocommerce_notices', 'wc_print_notices', 10);
        remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);

        add_filter('woocommerce_add_error', '__return_false');

        add_filter('woocommerce_add_success', function ($value) {
            wc_clear_notices();

            if (isset($_REQUEST['wc-ajax']) && $_REQUEST['wc-ajax'] === 'remove_coupon') {
                return $value;
            }

            return false;
        });

        add_filter('woocommerce_add_notice', '__return_false');
    }
});
