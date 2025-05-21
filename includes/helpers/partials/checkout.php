<?php

function growtype_wc_is_checkout_page()
{
    return strpos($_SERVER['REQUEST_URI'], 'checkout') > -1 && empty(is_wc_endpoint_url('order-received'));
}

function growtype_wc_user_can_not_buy_redirect_url()
{
    $redirect_url = home_url();

    if (function_exists('growtype_form_login_page_url')) {
        $redirect_url = growtype_form_login_page_url([
            'redirect_after' => get_permalink()
        ]);

        $redirect_url = apply_filters('growtype_wc_add_to_cart_can_not_buy_redirect', $redirect_url);
    }

    return $redirect_url;
}

function growtype_wc_get_checkout_style()
{
    return apply_filters('growtype_wc_get_checkout_style', get_theme_mod('woocommerce_checkout_style_select', 'default'));
}

function growtype_wc_checkout_breadcrumbs_active()
{
    return apply_filters('growtype_wc_checkout_breadcrumbs_active', get_theme_mod('woocommerce_checkout_breadcrumbs', false));
}

function growtype_wc_checkout_payment_methods_position()
{
    return apply_filters('growtype_wc_checkout_payment_methods_position', get_theme_mod('woocommerce_checkout_payment_methods_position', 'default'));
}
