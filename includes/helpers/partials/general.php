<?php

if (!function_exists('d')) {
    function d($data)
    {
        highlight_string("<?php\n" . var_export($data, true) . ";\n?>");
        die();
    }
}

if (!function_exists('ddd')) {
    function ddd($arr)
    {
        return '<pre>' . var_export($arr, false) . '</pre>';
    }
}

/**
 * Check if account page
 */
function growtype_wc_is_account_page()
{
    global $wp;

    if (isset($_SERVER['REQUEST_URI'])) {
        $url_parts = explode('/', $_SERVER['REQUEST_URI']);
        if (!empty($url_parts)) {
            return in_array('my-account', explode('/', $_SERVER['REQUEST_URI']));
        }
    }

    return false;
}

/**
 * Check if account page
 */
function growtype_wc_is_dashboard_page()
{
    global $wp;

    if (isset($_SERVER['REQUEST_URI'])) {
        $url_parts = explode('/', $_SERVER['REQUEST_URI']);
        if (!empty($url_parts)) {
            $url_parts = array_filter($url_parts, function ($value) {
                return !empty($value);
            });

            return !empty($url_parts) && end($url_parts) === 'my-account';
        }
    }

    return false;
}

/**
 * @return bool
 * True when the store catalogue contains exactly one product (no plan selection or variants).
 * Used to simplify the UI — e.g. hide "Browse products" links, skip product-selection steps,
 * and go straight to checkout for that single product.
 */
function growtype_wc_selling_type_single_product(): bool
{
    return get_theme_mod('shop_selling_type_select', 'shop_selling_type_multiple') === 'shop_selling_type_single';
}

/**
 * @return bool
 * True when the shop sells a single product but restricts purchase to one unit at a time
 * (e.g. a subscription where the user can only hold one active at once).
 * Differs from single_product: single_product = one product in the catalogue;
 * single_item = catalogue may have variants but only one unit can be bought per transaction.
 */
function growtype_wc_selling_type_single_item(): bool
{
    return get_theme_mod('shop_selling_type_select', 'shop_selling_type_multiple') === 'shop_selling_type_single_item';
}

/**
 * @return bool
 * True for any single-selling-type model (single product or single item).
 */
function growtype_wc_selling_type_is_single(): bool
{
    return growtype_wc_selling_type_single_product() || growtype_wc_selling_type_single_item();
}

/**
 * @return bool
 */
function growtype_wc_user_can_manage_shop()
{
    return user_can(get_current_user_id(), 'editor_plus_shop_manager') ||
        user_can(get_current_user_id(), 'manage_woocommerce') ||
        user_can(get_current_user_id(), 'administrator');
}
