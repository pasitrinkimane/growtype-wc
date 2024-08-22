<?php

/**
 * Skip cart page if necessary
 */
add_action('template_redirect', 'growtype_wc_skip_cart_redirect');
function growtype_wc_skip_cart_redirect()
{
    if (growtype_wc_skip_cart_page() && is_cart()) {
        wc_clear_notices();

        $redirect_url = wc_get_page_permalink('shop');

        if (!WC()->cart->is_empty()) {
            $redirect_url = wc_get_checkout_url();
        }

        $redirect_url = apply_filters('growtype_wc_skip_cart_redirect_url', $redirect_url);

        wp_safe_redirect($redirect_url);

        exit();
    }
}
