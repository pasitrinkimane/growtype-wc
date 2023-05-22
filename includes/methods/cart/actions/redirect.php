<?php

add_action('template_redirect', 'growtype_wc_skip_cart_redirect');
function growtype_wc_skip_cart_redirect()
{
    if (growtype_wc_skip_cart_page()) {
        wc_clear_notices();

        if (!WC()->cart->is_empty() && is_cart()) {
            wp_safe_redirect(wc_get_checkout_url());
            exit();
        } elseif (WC()->cart->is_empty() && is_cart()) {
            wp_safe_redirect(wc_get_page_permalink('shop'));
            exit();
        }
    }
}
