<?php

/**
 * Disable marketing
 */
add_action('admin_menu', 'growtype_wc_admin_menu');
function growtype_wc_admin_menu()
{
    global $menu, $submenu;

    /**
     * Woocommerce product menu custom title
     */
    if (!empty(get_option('woocommerce_products_menu_title'))) {
        $menu['26'][0] = get_option('woocommerce_products_menu_title');
        $submenu['edit.php?post_type=product'][5][0] = 'All ' . get_option('woocommerce_products_menu_title');
    }

    /**
     * Woocommerce wooocommerce menu custom title
     */
    if (!empty(get_option('woocommerce_main_menu_title'))) {
        $menu['55.5'][0] = get_option('woocommerce_main_menu_title');
    }
}
