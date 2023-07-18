<?php

add_filter('growtype_page_is_among_enabled_pages', 'growtype_wc_page_is_among_enabled_pages');
function growtype_wc_page_is_among_enabled_pages($enabled_pages)
{
    if (class_exists('woocommerce')) {
        if (
            (is_product() && in_array('single_shop_page', $enabled_pages))
            ||
            (is_shop() && in_array(wc_get_page_id('shop'), $enabled_pages))
        ) {
            return true;
        }
    }

    return false;
}

add_filter('growtype_permalink', 'growtype_wc_permalink');
function growtype_wc_permalink($permalink)
{
    if (class_exists('woocommerce') && is_shop()) {
        return get_permalink(wc_get_page_id('shop'));
    }

    return $permalink;
}

/**
 * Catalog search
 */
add_filter('woocommerce_redirect_single_search_result', '__return_false');

/**
 * @param $orderby
 * @return mixed
 * Edit WooCommerce orderby dropdown menu items of shop page
 */
add_filter("woocommerce_catalog_orderby", "growtype_wc_catalog_orderby", 20);
function growtype_wc_catalog_orderby($orderby)
{
    $disabled_options = explode(',', get_theme_mod('catalog_orderby_switch_disabled_options')) ?? null;

    if (!empty($disabled_options)) {
        foreach ($disabled_options as $option) {
            unset($orderby[$option]);
        }
    }

    return $orderby;
}

/**
 * Products filtering by specific properties
 */
add_action('woocommerce_product_query', 'growtype_wc_product_query');
function growtype_wc_product_query($query)
{
    $uri = $_SERVER['REQUEST_URI'];

    if (strstr($uri, 'cat=sale')) {

        $grouped_products = wc_get_products(array (
            'limit' => -1,
            'type' => 'grouped',
            'status' => 'publish'
        ));

        $grouped_products_on_sale = [];
        foreach ($grouped_products as $grouped_product) {
            if ($grouped_product->is_on_sale()) {
                array_push($grouped_products_on_sale, $grouped_product->get_id());
            }
        }

        $product_ids_on_sale = array_merge(wc_get_product_ids_on_sale(), $grouped_products_on_sale);

        $query->set('post__in', (array)$product_ids_on_sale);
    }

    if (strstr($uri, 'cat=new')) {
        $query->set('date_query', array (
            array (
                'after' => "15 day ago"
            )
        ));
    }
}

/**
 * Disable shop page access if enabled
 */
add_action('template_redirect', 'growtype_catalog_disable_access');
function growtype_catalog_disable_access()
{
    if (is_shop() && get_theme_mod('catalog_disable_access')) {
        wp_redirect(home_url());
        exit();
    } elseif (get_theme_mod('catalog_disable_access') && is_product_category()) {
        /**
         * Disable product category access if enabled
         */
        wp_redirect(home_url());
        exit();
    }
}

/**
 * Remove product count label
 */
add_action('wp_loaded', 'woocommerce_template_loop_result_count_remove');
function woocommerce_template_loop_result_count_remove()
{
    if (get_theme_mod('wc_catalog_result_count_hide')) {
        remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
    }
}

/**
 * Filters btn for mobile
 */
add_action('woocommerce_before_shop_loop', 'growtype_wc_woocommerce_before_main_content');
function growtype_wc_woocommerce_before_main_content()
{
    echo '<button class="btn btn-secondary btn-filters-trigger">' . __('Filter', 'growtype-wc') . '</button>';
}
