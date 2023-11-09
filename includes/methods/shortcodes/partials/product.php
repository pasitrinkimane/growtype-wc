<?php

/**
 * Woocommerce custom products shortcode
 */
add_shortcode('growtype_wc_products', 'growtype_wc_products_shortcode');
function growtype_wc_products_shortcode($atts, $content = null)
{
    if (!function_exists('wc_get_products')) {
        return '';
    }

    $shortcode_params = shortcode_atts(array (
        'ids' => '',
        'category' => '',
        'posts_per_page' => wc_get_default_products_per_row() * wc_get_default_product_rows_per_page(),
        'columns' => '',
        'orderby' => isset($_GET['orderby']) ? $_GET['orderby'] : get_option('woocommerce_default_catalog_orderby'),
        'order' => '',
        'visibility' => ['catalog', 'search'],
        'products_group' => 'default',
        'product_type' => '',
        'preview_style' => Growtype_Wc_Product::catalog_default_preview_style(),
        'edit_product' => 'false',
        'post_status' => 'publish',
        'cta_btn' => '',
        'before_shop_loop' => 'false',
        'after_shop_loop' => '',
        'not_found_message' => 'true',
        'not_found_subtitle' => __('You have no products.', 'growtype-wc'),
        'not_found_cta' => '',
        'ids_required' => 'false',
        'meta_key' => '',
    ), $atts);

    /**
     * Get properties from shortcode
     */
    extract($shortcode_params);

    $growtype_wc_get_orderby_params = growtype_wc_get_orderby_params($orderby);

    $orderby = isset($growtype_wc_get_orderby_params['orderby']) ? $growtype_wc_get_orderby_params['orderby'] : 'menu_order';
    $meta_key = !empty($meta_key) ? $meta_key : (isset($growtype_wc_get_orderby_params['meta_key']) ? $growtype_wc_get_orderby_params['meta_key'] : '');
    $order = !empty($order) ? $order : (isset($growtype_wc_get_orderby_params['order']) ? $growtype_wc_get_orderby_params['order'] : 'asc');

    /**
     * Default args
     */
    $args = array (
        'post_type' => 'product',
        'post_status' => $post_status,
        'orderby' => $orderby,
        'order' => $order
    );

    if (!empty($meta_key)) {
        $args['meta_query'] = [
            'meta_value' => [
                'key' => $meta_key
            ]
        ];
    }

    /**
     * Check if ids specified
     */
    $not_found_message_html = $not_found_message === 'true' ? App\template('partials.content.404.general', ['cta' => urldecode($not_found_cta), 'subtitle' => $not_found_subtitle]) : '';

    if (!empty($ids)) {
        $args['post__in'] = explode(',', $ids);
    }

    /**
     * Posts per page
     */
    if (!empty($posts_per_page)) {
        $args['posts_per_page'] = $posts_per_page;
    }

    /**
     * Display type
     */
    if ($products_group === 'active_auctions') {
        $args['meta_query'] = [
            array (
                'key' => '_auction_has_started',
                'compare' => '1'
            )
        ];
    } elseif ($products_group === 'active_upcoming_auctions') {
        $args['meta_query'] = [
            array (
                'key' => '_auction_closed',
                'compare' => 'NOT EXISTS'
            ),
            array (
                'key' => '_auction_start_price',
                'compare' => 'EXISTS'
            )
        ];
    } elseif ($products_group === 'watchlist') {
        $user_ID = get_current_user_id();
        $watchlist_ids = get_user_meta($user_ID, '_auction_watch');

        if (!empty($watchlist_ids)) {
            $args['orderby'] = 'meta_value';
            $args['meta_key'] = '_auction_dates_to';
            $args['post__in'] = $watchlist_ids;
        } else {

            if (function_exists('App\template')) {
                return App\template('partials.content.404.general', ['cta' => urldecode($not_found_cta), 'subtitle' => $not_found_subtitle]);
            }

            return growtype_wc_include_view('partials.content.404.general');
        }
    } elseif ($products_group === 'user_active_bids') {
        $bids_ids = Growtype_Wc_Auction::user_active_bids_ids(get_current_user_id());

        if (!empty($bids_ids)) {
            $args['orderby'] = 'meta_value';
            $args['meta_key'] = '_auction_dates_to';
            $args['post__in'] = $bids_ids;
        } else {
            return growtype_wc_include_view('partials.content.404.general');
        }
    }

    /**
     * Page
     */
    $paged = (get_query_var('paged')) ? absint(get_query_var('paged')) : 1;
    $args['page'] = $paged;

    /**
     * Set prodcut visibility
     */
    if ($visibility !== 'any') {
        $visibility_tax_data = array (
            array (
                'taxonomy' => 'product_visibility',
                'field' => 'name',
                'terms' => array ('exclude-from-catalog', 'exclude-from-search'),
                'operator' => 'NOT IN',
            )
        );

        $args['tax_query'] = $visibility_tax_data;
    }

    if (isset($product_type) && $product_type === 'subscription') {
        $subscription_ids = Growtype_Wc_Product::get_subscriptions_ids();
        $args['post__in'] = isset($args['post__in']) ? array_merge($args['post__in'], $subscription_ids) : $subscription_ids;
    }

    /**
     * Check if still empty post ids
     */
    if ($ids_required === 'true' && (!isset($args['post__in']) || empty($args['post__in']))) {
        return $not_found_message_html;
    }

    /**
     * Render products
     */
    return growtype_wc_render_products($args, [
        'current_page' => $paged,
        'cta_btn' => $cta_btn,
        'preview_style' => $preview_style,
        'products_group' => $products_group,
        'visibility' => $visibility,
        'edit_product' => $edit_product,
        'not_found_message_html' => $not_found_message_html,
        'columns' => $columns,
        'before_shop_loop' => $before_shop_loop,
        'product_type' => $product_type
    ]);
}
