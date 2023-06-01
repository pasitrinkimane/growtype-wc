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
 */
function growtype_wc_selling_type_single(): bool
{
    return get_theme_mod('shop_selling_type_select', 'shop_selling_type_multiple') === 'shop_selling_type_single';
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
