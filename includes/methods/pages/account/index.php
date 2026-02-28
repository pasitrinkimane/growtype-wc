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

function growtype_wc_get_account_subpage_url($subpage_slug)
{
    return get_permalink(get_option('woocommerce_myaccount_page_id')) . $subpage_slug;
}

function growtype_wc_get_account_subpage_intro_details($subpage)
{
    $available_pages = [
        'orders' => __('Orders', 'growtype-wc') . ' <div class="e-subtitle">' . __('Purchase history', 'growtype-wc') . '</div>',
        'edit-account' => __('Profile', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust account details', 'growtype-wc') . '</div>',
        'edit-address' => __('Addresses', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust shipping and billing details', 'growtype-wc') . '</div>',
        'auctions-endpoint' => __('Auctions', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust auctions settings', 'growtype-wc') . '</div>',
        'purchased-products' => __('Purchased products', 'growtype-wc') . ' <div class="e-subtitle">' . __('Your products', 'growtype-wc') . '</div>',
        'uploaded-products' => __('Uploaded products', 'growtype-wc') . ' <div class="e-subtitle">' . __('Your uploads', 'growtype-wc') . '</div>',
        'subscriptions' => __('Subscriptions', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust subscriptions details', 'growtype-wc') . '</div>',
        'manage-subscription' => __('Manage Subscription', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust subscription details', 'growtype-wc') . '</div>',
        'downloads' => __('Downloads', 'growtype-wc') . ' <div class="e-subtitle">' . __('Available to download products', 'growtype-wc') . '</div>',
        'payment-methods' => __('Payment methods', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust payment methods', 'growtype-wc') . '</div>',
        'customer-logout' => __('Logout', 'growtype-wc') . ' <div class="e-subtitle">' . __('Sign out from system', 'growtype-wc') . '</div>',
        'billing' => __('Billing address', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust billing address details', 'growtype-wc') . '</div>',
        'shipping' => __('Shipping address', 'growtype-wc') . ' <div class="e-subtitle">' . __('Adjust shipping address details', 'growtype-wc') . '</div>',
    ];

    $available_pages = apply_filters('growtype_wc_get_account_subpage_intro_available_pages', $available_pages, $subpage);

    $intro_details = $available_pages[$subpage] ?? null;

    $intro_details = apply_filters('growtype_wc_get_account_subpage_intro_details', $intro_details, $available_pages, $subpage);

    return $intro_details;
}

/**
 * Add intro section
 */
add_action('woocommerce_before_account_payment_methods', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_account_downloads', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_account_purchased_products', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_account_uploaded_products', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_account_subscriptions', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_account_orders', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_edit_account_form', 'growtype_wc_account_page_intro_block');
add_action('woocommerce_before_edit_account_address_form', 'growtype_wc_account_page_intro_block');
function growtype_wc_account_page_intro_block()
{
    $url_slug = Growtype_Page::get_url_slug();

    $back_url = get_permalink(wc_get_page_id('myaccount'));
    $intro_details = growtype_wc_get_account_subpage_intro_details($url_slug);

    if ($url_slug === 'subscriptions' && isset($_GET['action']) && $_GET['action'] === 'manage') {
        $back_url = growtype_wc_get_account_subpage_url('subscriptions');
        $intro_details = growtype_wc_get_account_subpage_intro_details('manage-subscription');
    }

    echo growtype_wc_include_view('woocommerce.myaccount.sections.info-header', [
        'back_url' => $back_url,
        'intro_details' => $intro_details
    ]);
}

/**
 *
 */
add_action('wp_loaded', 'growtype_wc_woocommerce_account_remove_navigation');
function growtype_wc_woocommerce_account_remove_navigation()
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
add_filter('woocommerce_account_menu_items', 'growtype_wc_woocommerce_account_extend_menu_items', 20);
function growtype_wc_woocommerce_account_extend_menu_items($items)
{
    /**
     * Reorder menu
     */
    $new_items = [];
    foreach ($items as $key => $item) {
        $item = growtype_wc_get_account_subpage_intro_details($key) ?? $item;

        if ($key === 'dashboard') {
            continue;
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

        if (get_theme_mod('woocommerce_account_payment_methods_tab_disabled') && $key === 'payment-methods') {
            continue;
        }

        $new_items[$key] = $item;

        if ($key === 'orders' || (empty($new_items['earnings']) && $key === 'edit-account')) {
             $new_items['earnings'] = growtype_wc_get_account_subpage_intro_details('earnings');
        }

        /**
         * Purchased products tab
         */
        if (!get_theme_mod('woocommerce_account_purchased_products_tab_disabled')) {
            $new_items['purchased-products'] = growtype_wc_get_account_subpage_intro_details('purchased-products');
        }

        /**
         * Uploaded products tab
         */
        if (!get_theme_mod('woocommerce_account_uploaded_products_tab_disabled')) {
            $new_items['uploaded-products'] = growtype_wc_get_account_subpage_intro_details('uploaded-products');
        }

        /**
         * Subscription
         */
        if (!get_theme_mod('woocommerce_account_subscriptions_tab_disabled')) {
            $new_items['subscriptions'] = growtype_wc_get_account_subpage_intro_details('subscriptions');
        }
    }

    return apply_filters('growtype_wc_woocommerce_account_extend_menu_items', $new_items);
}

/**
 * Add New Tab on the My Account page
 */
add_action('init', 'growtype_wc_woocommerce_account_extend_endpoints');
function growtype_wc_woocommerce_account_extend_endpoints()
{
    add_rewrite_endpoint('purchased-products', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('uploaded-products', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('subscriptions', EP_ROOT | EP_PAGES);
}

/**
 * Add query variable
 */
add_filter('query_vars', 'growtype_wc_woocommerce_account_extend_query_vars', 0);
function growtype_wc_woocommerce_account_extend_query_vars($vars)
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
