<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add Editor + Shop Manager Role
 */
add_action('init', function () {

    // Remove old role first
//    if (get_role('editor_plus_shop_manager')) {
//        remove_role('editor_plus_shop_manager');
//    }

    // Full admin caps
    $admin_caps = [
        'edit_users' => true,
        'edit_files' => true,
        'manage_options' => true,
        'manage_categories' => true,
        'manage_links' => true,
        'upload_files' => true,
        'unfiltered_html' => true,
        'edit_posts' => true,
        'edit_others_posts' => true,
        'edit_published_posts' => true,
        'publish_posts' => true,
        'edit_pages' => true,
        'read' => true,
        'level_10' => true,
        'level_9' => true,
        'level_8' => true,
        'level_7' => true,
        'level_6' => true,
        'level_5' => true,
        'level_4' => true,
        'level_3' => true,
        'level_2' => true,
        'level_1' => true,
        'level_0' => true,
        'edit_others_pages' => true,
        'edit_published_pages' => true,
        'publish_pages' => true,
        'delete_pages' => true,
        'delete_others_pages' => true,
        'delete_published_pages' => true,
        'delete_posts' => true,
        'delete_others_posts' => true,
        'delete_published_posts' => true,
        'delete_private_posts' => true,
        'edit_private_posts' => true,
        'read_private_posts' => true,
        'delete_private_pages' => true,
        'edit_private_pages' => true,
        'read_private_pages' => true,
    ];

    // Create new role
    add_role('editor_plus_shop_manager', 'Editor + Shop Manager', $admin_caps);

    $role = get_role('editor_plus_shop_manager');

    // Stop if WooCommerce not active
    if (!class_exists('WooCommerce') || !class_exists('WC_Install')) {
        return;
    }

    // Add WooCommerce core caps
    $wc_caps = WC_Install::get_core_capabilities();
    foreach ($wc_caps as $group) {
        foreach ($group as $cap) {
            $role->add_cap($cap);
        }
    }
});

/**
 * Remove unnecessary capabilities from shop_manager
 */
add_action('admin_init', function () {
    $role = get_role('shop_manager');
    if ($role) {
        $caps_to_remove = [
            'edit_pages',
            'edit_posts',
            'list_users',
            'read_private_pages',
            'read_private_posts',
            'edit_published_posts',
            'edit_published_pages',
            'edit_private_pages',
            'edit_private_posts',
            'edit_others_posts',
            'publish_posts',
            'publish_pages',
            'delete_posts',
            'delete_pages',
            'delete_private_pages',
            'delete_private_posts',
            'delete_published_pages',
            'delete_published_posts',
            'delete_others_posts',
            'delete_others_pages',
            'manage_categories',
            'manage_links',
            'moderate_comments',
        ];

        foreach ($caps_to_remove as $cap) {
            $role->remove_cap($cap);
        }
    }
});

/**
 * Hide restricted menus for Editor + Shop Manager
 */
add_action('admin_menu', function () {
    if (!current_user_can('editor_plus_shop_manager')) {
        return;
    }

    remove_menu_page('edit.php?post_type=acf-field-group'); // ACF
    remove_menu_page('growtype-theme-settings');            // Theme settings
    remove_menu_page('options-general.php');               // WP general
    remove_menu_page('tools.php');                         // Tools

    remove_submenu_page('woocommerce', 'wc-admin&path=/extensions'); // WC Extensions
    remove_submenu_page('woocommerce', 'wc-status');                 // WC Status
    remove_submenu_page('woocommerce', 'wc-settings');               // WC Settings top-level
    remove_submenu_page('woocommerce', 'wc-admin&path=/marketing');  // WC Marketing

    // Remove Checkout → Payments menu item
    global $submenu, $menu;

    // Remove submenu under WooCommerce
    if (isset($submenu['woocommerce'])) {
        foreach ($submenu['woocommerce'] as $key => $item) {
            if (strpos($item[2], 'wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM') !== false) {
                unset($submenu['woocommerce'][$key]);
            }
        }
    }

    // Remove top-level menu if it exists
    foreach ($menu as $key => $item) {
        if (isset($item[2]) && strpos($item[2], 'wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM') !== false) {
            unset($menu[$key]);
        }
    }
}, 999);

/**
 * Redirect Editor + Shop Manager from restricted pages
 */
add_action('admin_init', function () {
    if (!current_user_can('editor_plus_shop_manager')) {
        return;
    }

    $restricted_pages = [
        'edit.php?post_type=acf-field-group',
        'admin.php?page=growtype-theme-settings',
        'options-general.php',
        'tools.php',
        'admin.php?page=wc-admin&path=/extensions',
        'admin.php?page=wc-status',
        'admin.php?page=wc-settings',
        'admin.php?page=wc-admin&path=/marketing',
    ];

    foreach ($restricted_pages as $page) {
        if (strpos($_SERVER['REQUEST_URI'], $page) !== false) {
            wp_redirect(admin_url());
            exit;
        }
    }

    // Block WooCommerce Checkout → Payments tab
    if (
        isset($_GET['page'], $_GET['tab'], $_GET['from']) &&
        $_GET['page'] === 'wc-settings' &&
        $_GET['tab'] === 'checkout' &&
        $_GET['from'] === 'PAYMENTS_MENU_ITEM'
    ) {
        wp_redirect(admin_url('admin.php?page=wc-settings'));
        exit;
    }
});
