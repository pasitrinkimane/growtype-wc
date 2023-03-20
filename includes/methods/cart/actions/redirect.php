<?php

do_action('growtype_custom_page_redirect', 'growtype_wc_custom_page_redirect');

function growtype_wc_custom_page_redirect()
{
    $woocommerce_skip_cart_page = get_theme_mod('woocommerce_skip_cart_page');

    if ($woocommerce_skip_cart_page && get_permalink() === wc_get_cart_url()) {
        if (!cart_is_empty()) {
            wp_redirect(wc_get_checkout_url());
            exit();
        }

        /**
         * Clear cart page notices
         */
        wc_clear_notices();

        wp_redirect(get_home_url_custom());
        exit();
    }
}
