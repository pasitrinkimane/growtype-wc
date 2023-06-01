<?php

/**
 * Skip cart page if necessary
 */
add_action('template_redirect', 'growtype_wc_skip_cart_redirect');
function growtype_wc_skip_cart_redirect()
{
    if (growtype_wc_skip_cart_page() && is_cart()) {
        wc_clear_notices();
        if (!WC()->cart->is_empty()) {
            wp_safe_redirect(wc_get_checkout_url());
        } else {
            wp_safe_redirect(wc_get_page_permalink('shop'));
        }
        exit();
    }
}
