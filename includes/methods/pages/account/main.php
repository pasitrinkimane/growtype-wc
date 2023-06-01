<?php

/**
 * Add methods
 */
include('subpages/edit-account.php');

/**
 * Account permalink
 */
add_filter('growtype_user_account_permalink', 'growtypw_wc_growtype_user_account_permalink');
function growtypw_wc_growtype_user_account_permalink($permalink)
{
    if (empty($permalink) && class_exists('woocommerce')) {
        $permalink = wc_get_page_permalink('myaccount');
    }

    return $permalink;
}

function get_account_subpage_intro_details($subpage)
{
    $details = [
        'orders' => __('Orders', 'growtype-wc') . ' <div class="e-subtitle">' . __('Purchase history', 'growtype-wc') . '</div>',
        'edit-account' => __('Profile', 'growtype-wc') . ' <div class="e-subtitle">' . __('Profile & business details', 'growtype-wc') . '</div>',
        'edit-address' => __('Addresses', 'growtype-wc') . ' <div class="e-subtitle">' . __('Shipping and billing details', 'growtype-wc') . '</div>',
        'auctions-endpoint' => __('Auctions', 'growtype-wc') . ' <div class="e-subtitle">' . __('Auctions settings', 'growtype-wc') . '</div>',
        'purchased-products' => __('Purchased products', 'growtype-wc') . ' <div class="e-subtitle">' . __('Your products', 'growtype-wc') . '</div>',
        'uploaded-products' => __('Uploaded products', 'growtype-wc') . ' <div class="e-subtitle">' . __('Your uploads', 'growtype-wc') . '</div>',
        'subscriptions' => __('Subscription', 'growtype-wc') . ' <div class="e-subtitle">' . __('Subscription details', 'growtype-wc') . '</div>',
        'downloads' => __('Downloads', 'growtype-wc') . ' <div class="e-subtitle">' . __('Available to download products', 'growtype-wc') . '</div>',
        'payment-methods' => __('Payment methods', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust payment method', 'growtype-wc') . '</div>',
        'customer-logout' => __('Logout', 'growtype-wc') . ' <div class="e-subtitle">' . __('Sign out from system', 'growtype-wc') . '</div>',
        'billing' => __('Billing address', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust billing address details', 'growtype-wc') . '</div>',
        'shipping' => __('Shipping address', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust shipping address details', 'growtype-wc') . '</div>',
    ];

    return $details[$subpage] ?? null;
}

/**
 * Add intro section
 */
add_action('woocommerce_before_account_payment_methods', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_account_downloads', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_account_purchased_products', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_account_uploaded_products', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before__account_subscriptions', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_account_orders', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_edit_account_form', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_edit_account_address_form', 'growtype_wc_account_page_intro_block');
function growtype_wc_account_page_intro_block()
{
    $url_slug = Growtype_Page::get_url_slug();

    echo growtype_wc_include_view('woocommerce.myaccount.sections.info-header', ['intro_details' => get_account_subpage_intro_details($url_slug)]);
}

/**
 *
 */
add_action('wp_loaded', 'woocommerce_account_remove_navigation');
function woocommerce_account_remove_navigation()
{
    $navigation_enabled = growtype_wc_is_dashboard_page();

    if (!$navigation_enabled) {
        remove_action('woocommerce_account_navigation', 'woocommerce_account_navigation', 10, 1);
    }
}

/**
 * @param $items
 * @return mixed
 * Account tabs
 */
add_filter('woocommerce_account_menu_items', 'woocommerce_account_extend_menu_items', 20);
function woocommerce_account_extend_menu_items($items)
{
    /**
     * Reorder menu
     */
    $new_items = [];
    foreach ($items as $key => $item) {
        $item = get_account_subpage_intro_details($key) ?? $item;

        if ($key === 'dashboard') {
            $get_id = get_option('woocommerce_myaccount_page_id');
            $item = get_the_title($get_id);
        }

        if (get_theme_mod('woocommerce_account_orders_tab_disabled') && $key === 'orders') {
            continue;
        }

        if (get_theme_mod('woocommerce_account_downloads_tab_disabled') && $key === 'downloads') {
            continue;
        }

        if (get_theme_mod('woocommerce_account_logout_tab_disabled') && $key === 'customer-logout') {
            continue;
        }

        if (get_theme_mod('woocommerce_account_addresses_tab_disabled') && $key === 'edit-address') {
            continue;
        }

        $new_items[$key] = $item;

        /**
         * Purchased products tab
         */
        if (!get_theme_mod('woocommerce_account_purchased_products_tab_disabled') && $key === 'dashboard') {
            $new_items['purchased-products'] = get_account_subpage_intro_details('purchased-products');
        }

        /**
         * Uploaded products tab
         */
        if (!get_theme_mod('woocommerce_account_uploaded_products_tab_disabled') && $key === 'dashboard') {
            $new_items['uploaded-products'] = get_account_subpage_intro_details('uploaded-products');
        }

        /**
         * Subscription
         */
        if (!get_theme_mod('woocommerce_account_subscriptions_tab_disabled') && $key === 'dashboard') {
            $new_items['subscriptions'] = get_account_subpage_intro_details('subscriptions');
        }
    }

    return $new_items;
}

/**
 * Add New Tab on the My Account page
 */
add_action('init', 'woocommerce_account_extend_endpoints');
function woocommerce_account_extend_endpoints()
{
    add_rewrite_endpoint('purchased-products', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('uploaded-products', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('subscriptions', EP_ROOT | EP_PAGES);
}

/**
 * Add query variable
 */
add_filter('query_vars', 'woocommerce_account_extend_query_vars', 0);
function woocommerce_account_extend_query_vars($vars)
{
    $vars[] = 'purchased-products';
    $vars[] = 'uploaded-products';
    $vars[] = 'subscriptions';

    return $vars;
}

/**
 * Products tab
 */
include('subpages/purchased-products.php');

/**
 * Products tab
 */
include('subpages/uploaded-products.php');

/**
 * Subscriptions
 */
include('subpages/subscriptions.php');
