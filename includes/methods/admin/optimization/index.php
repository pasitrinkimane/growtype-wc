<?php
/**
 * Remove marketing
 */
add_action('admin_menu', 'growtype_wc_remove_woocommerce_marketing_menu', 999);
function growtype_wc_remove_woocommerce_marketing_menu()
{
    remove_menu_page('woocommerce-marketing');
}

/**
 * Supress WooCommerce Helper Admin Notices
 */
add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');

/**
 * Disable setup widget
 */
add_action('wp_dashboard_setup', 'growtype_wc_disable_woocommerce_setup_remove_dashboard_widgets', 40);
function growtype_wc_disable_woocommerce_setup_remove_dashboard_widgets()
{
    remove_meta_box('wc_admin_dashboard_setup', 'dashboard', 'normal');
}

/**
 * Increase the default batch limit of 50 in the CSV product exporter to a more usable 5000
 */
add_filter('woocommerce_product_export_batch_limit', function () {
    return 5000;
}, 999);

/**
 * Remove marketplace suggestions
 */
add_filter('woocommerce_allow_marketplace_suggestions', '__return_false');

/**
 * Remove connect your store to WooCommerce.com admin notice
 */
add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');

/**
 * Delete the WooCommerce usage tracker cron event
 */
wp_clear_scheduled_hook('woocommerce_tracker_send_event');

/**
 * Disable the Payment Gateway Admin Suggestions
 */
add_filter('woocommerce_admin_payment_gateway_suggestion_specs', '__return_empty_array');

/**
 * Menu admin page "Home"
 */
add_action('admin_menu', 'growtype_wc_remove_wc_admin_home_menu', 999);
function growtype_wc_remove_wc_admin_home_menu()
{
    remove_submenu_page('woocommerce', 'wc-admin');
}
