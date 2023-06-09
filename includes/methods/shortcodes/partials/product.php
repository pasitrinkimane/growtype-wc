<?php

/**
 * Woocommerce custom products shortcode
 */
add_shortcode('growtype_products', 'growtype_products_shortcode');
function growtype_products_shortcode($atts, $content = null)
{
    global $woocommerce_loop, $wpdb;

    if (!function_exists('wc_get_products')) {
        return '';
    }

    /**
     * Get properties from shortcode
     */
    extract(shortcode_atts(array (
        'ids' => '',
        'category' => '',
        'per_page' => '',
        'columns' => '',
        'orderby' => 'menu_order',
        'order' => 'asc',
        'visibility' => ['catalog', 'search'],
        'products_group' => 'default',
        'product_type' => '',
        'preview_style' => Growtype_Wc_Product::catalog_default_preview_style(),
        'edit_product' => 'false',
        'post_status' => 'publish',
        'cta_btn' => '',
        'before_shop_loop' => '',
        'after_shop_loop' => '',
        'not_found_message' => 'true',
        'not_found_subtitle' => __('You have no products.', 'growtype-wc'),
        'not_found_cta' => '',
        'ids_required' => 'true',
    ), $atts));

    /**
     * Default args
     */
    $args = array (
        'post_type' => 'product',
        'post_status' => $post_status,
        'orderby' => $orderby,
        'order' => $order
    );

    /**
     * Check if ids specified
     */
    $not_found_message_content = $not_found_message === 'true' ? App\template('partials.content.404.general', ['cta' => urldecode($not_found_cta), 'subtitle' => $not_found_subtitle]) : '';

    if (!empty($ids)) {
        $args['post__in'] = explode(',', $ids);
    }

    /**
     * Posts per page
     */
    if (!empty($per_page)) {
        $args['posts_per_page'] = $per_page;
    } else {
        $per_page = wc_get_default_products_per_row() * wc_get_default_product_rows_per_page();
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
        return $not_found_message_content;
    }

    /**
     * Get products
     */
    $products = new WP_Query($args);

    if ($products->have_posts()) {
        wc_set_loop_prop('current_page', $paged);
        wc_set_loop_prop('is_paginated', wc_string_to_bool(true));
        wc_set_loop_prop('page_template', get_page_template_slug());
        wc_set_loop_prop('per_page', $per_page);
        wc_set_loop_prop('total', $products->post_count);
        wc_set_loop_prop('total_pages', $products->max_num_pages);

        if (!empty($columns)) {
            $woocommerce_loop['columns'] = $columns;
        }

        if ($cta_btn) {
            set_query_var('cta_btn', $cta_btn);
        }

        /**
         * Render
         */
        ob_start();

        if (isset($before_shop_loop) && $before_shop_loop) {
            do_action('woocommerce_before_shop_loop');
        }

        wc_get_template('loop/loop-start.php', ['preview_style' => $preview_style, 'products_group' => $products_group]);

        set_query_var('visibility', $visibility);

        if ($edit_product === 'true') {
            set_query_var('preview_permalink', true);
        }

        if ($preview_style === 'table') {
            echo growtype_wc_include_view('woocommerce.components.table.product-table', ['products' => $products]);
        } else {
            while ($products->have_posts()) : $products->the_post();
                wc_get_template_part('content', 'product');
            endwhile;
        }

        wc_get_template('loop/loop-end.php');

        if (isset($after_shop_loop) && $after_shop_loop) {
            do_action('woocommerce_after_shop_loop');
        }

        wp_reset_postdata();

        $render = '<div class="woocommerce">' . ob_get_clean() . '</div>';
    }

    return isset($render) && !empty($render) ? $render : $not_found_message_content;
}
