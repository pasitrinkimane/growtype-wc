<?php

/**
 * Add new role
 */
add_action('init', 'growtype_wc_add_editor_plus_shop_manager_role');
function growtype_wc_add_editor_plus_shop_manager_role()
{
    global $wp_roles;

    if (!class_exists('WC_Product') && !function_exists('get_core_capabilities')) {
        $role = get_role('editor_plus_shop_manager');
        if (!empty($role)) {
            remove_role('editor_plus_shop_manager');
        }

        return false;
    }

    /**
     * delete role
     */
    add_role(
        'editor_plus_shop_manager',
        __('Editor + Shop manager'),
        array (
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
            'read' => true,
            'delete_others_posts' => true,
            'delete_others_pages' => true,
            'moderate_comments' => true,
            'activate_plugins' => false,
            'delete_pages' => true,
            'delete_posts' => true,
            'delete_private_pages' => true,
            'delete_private_posts' => true,
            'delete_published_pages' => true,
            'delete_published_posts' => true,
            'edit_dashboard' => true,
            'edit_others_pages' => true,
            'edit_others_posts' => true,
            'edit_pages' => true,
            'edit_posts' => true,
            'edit_private_pages' => true,
            'edit_private_posts' => true,
            'edit_published_pages' => true,
            'edit_published_posts' => true,
            'edit_theme_options' => true,
            'export' => true,
            'import' => true,
            'list_users' => false,
            'manage_categories' => true,
            'manage_links' => true,
            'manage_options' => false,
            'manage_comments' => true,
            'promote_users' => false,
            'publish_pages' => true,
            'publish_posts' => true,
            'read_private_pages' => true,
            'read_private_posts' => true,
            'remove_users' => false,
            'switch_themes' => false,
            'upload_files' => true,
            'unfiltered_html' => true
        )
    );

    if (class_exists('WC_Install') && function_exists('get_core_capabilities')) {
        $wcIntall = new WC_Install();
        $capabilities = $wcIntall->get_core_capabilities();

        foreach ($capabilities as $cap_group) {
            foreach ($cap_group as $cap) {
                $wp_roles->add_cap('editor_plus_shop_manager', $cap);
            }
        }
    }
}

/**
 * Remove roles from shop_manager role
 */
add_action('admin_init', 'growtype_wc_remove_roles_from_shop_manager');
function growtype_wc_remove_roles_from_shop_manager()
{
    $role = get_role('shop_manager');
    if (!empty($role)) {
        $role->remove_cap('edit_pages');
        $role->remove_cap('edit_posts');
        $role->remove_cap('list_users');
        $role->remove_cap('read_private_pages');
        $role->remove_cap('read_private_posts');
        $role->remove_cap('edit_published_posts');
        $role->remove_cap('edit_published_pages');
        $role->remove_cap('edit_private_pages');
        $role->remove_cap('edit_private_posts');
        $role->remove_cap('edit_others_posts');
        $role->remove_cap('publish_posts');
        $role->remove_cap('publish_pages');
        $role->remove_cap('delete_posts');
        $role->remove_cap('delete_pages');
        $role->remove_cap('delete_private_pages');
        $role->remove_cap('delete_private_posts');
        $role->remove_cap('delete_published_pages');
        $role->remove_cap('delete_published_posts');
        $role->remove_cap('delete_others_posts');
        $role->remove_cap('delete_others_pages');
        $role->remove_cap('manage_categories');
        $role->remove_cap('manage_links');
        $role->remove_cap('moderate_comments');
    }
}
