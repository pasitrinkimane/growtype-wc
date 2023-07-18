<?php

/**
 * Catelog product select filter
 */
add_action('wp_ajax_filter_products', 'growtype_wc_filter_products');
add_action('wp_ajax_nopriv_filter_products', 'growtype_wc_filter_products');
function growtype_wc_filter_products()
{
    $filter_params = [
        'orderby' => isset($_POST['orderby']) ? $_POST['orderby'] : 'menu_order title',
        'categories_ids' => isset($_POST['categories_ids']) ? $_POST['categories_ids'] : [],
        'products_group' => isset($_POST['products_group']) ? $_POST['products_group'] : [],
        'min_price' => isset($_POST['min_price']) ? $_POST['min_price'] : [],
        'max_price' => isset($_POST['max_price']) ? $_POST['max_price'] : [],
        'base' => isset($_POST['base']) ? $_POST['base'] : '',
        'page_nr' => isset($_POST['page_nr']) ? $_POST['page_nr'] : '',
        'meta_key' => isset($_POST['meta_key']) ? $_POST['meta_key'] : [],
        'meta_values' => isset($_POST['meta_values']) ? json_decode(stripslashes($_POST['meta_values'])) : [],
    ];

    $products = growtype_wc_get_filtered_products($filter_params);

    if ($products->have_posts()) {
        /**
         * Products
         */
        ob_start();

        if (Growtype_Wc_Product::catalog_default_preview_style() === 'table') {
            $params = [
                'watchlist_btn' => isset($_POST['products_group']) && ($_POST['products_group'] === 'watchlist' || $_POST['products_group'] === 'default'),
                'edit_btn' => isset($_POST['products_group']) && $_POST['products_group'] === 'user_uploaded',
            ];

            echo growtype_wc_include_view('woocommerce.components.table.product-table', [
                'products' => $products,
                'params' => $params
            ]);
        } else {
            while ($products->have_posts()) : $products->the_post();
                wc_get_template_part('content', 'product');
            endwhile;
        }

        wp_reset_postdata();

        $rendered_products = ob_get_clean();

        /**
         * Pagination
         */

        ob_start();

        if (!empty($filter_params['categories_ids'])) {
            $cat_url = get_term_link((int)$filter_params['categories_ids'][0], 'product_cat');
        } else {
            $cat_url = get_permalink(wc_get_page_id('shop'));
        }

        $args = array (
            'total' => $products->max_num_pages,
            'current' => $filter_params['page_nr'],
            'base' => $cat_url . '%_%?orderby=' . $_POST['orderby'],
            'format' => 'page/%#%',
        );

        echo wc_get_template('loop/pagination.php', $args);

        $pagination = ob_get_clean();

    } else {
        ob_start();

        do_action('woocommerce_no_products_found');

        $rendered_products = ob_get_clean();
    }

    $data = [
        'products' => $rendered_products,
        'pagination' => isset($pagination) ? $pagination : '',
    ];

    return wp_send_json($data);
}

/**
 * @param $orderby
 * @param $categories_ids
 * @return WP_Query
 */
function growtype_wc_get_filtered_products($filter_params)
{
    $meta_key = '';
    $order = 'ASC';
    $orderby = isset($filter_params['orderby']) ? $filter_params['orderby'] : '';

    if (!empty($orderby)) {
        switch ($orderby) {
            case 'menu_order':
                $meta_key = '';
                $order = 'ASC';
                $orderby = 'menu_order title';
                break;
            case 'popularity':
                $meta_key = 'total_sales';
                $order = 'DESC';
                break;
            case 'price':
                $meta_key = '_price';
                $order = 'ASC';
                $orderby = 'meta_value_num';
                break;
            case 'price-desc':
                $meta_key = '_price';
                $order = 'DESC';
                $orderby = 'meta_value_num';
                break;
            case 'date':
                $meta_key = '';
                $order = 'DESC';
                $orderby = 'date';
                break;
            case 'rating':
                $meta_key = '_wc_average_rating';
                $order = 'DESC';
                $orderby = 'meta_value_num title';
                break;
        }
    }

    $args = array (
        'post_type' => 'product',
        'post_status' => 'publish',
        'orderby' => $orderby,
        'order' => $order,
        'paginate' => true,
        'featured' => false,
        'posts_per_page' => wc_get_default_products_per_row() * wc_get_default_product_rows_per_page(),
    );

    if (!empty($meta_key)) {
        $args['meta_query'] = array (
            'meta_value' => array (
                'key' => $meta_key,
            )
        );
    }

    /**
     * Price params
     */
    if (isset($filter_params['min_price']) && !empty($filter_params['min_price']) && isset($filter_params['max_price']) && !empty($filter_params['max_price'])) {
        $price_meta_data = array (
            'meta_value' => array (
                'key' => '_price',
                'value' => array ($filter_params['min_price'], $filter_params['max_price']),
                'compare' => 'BETWEEN',
                'type' => 'DECIMAL(10,' . wc_get_price_decimals() . ')',
            )
        );

        $args['meta_query'] = isset($args['meta_query']) ? array_merge($args['meta_query'], $price_meta_data) : $price_meta_data;
    }

    /**
     * Take product group ids
     */
    if (isset($filter_params['products_group'])) {
        if ($filter_params['products_group'] === 'watchlist') {
            $user_ID = get_current_user_id();
            $watchlist_ids = get_user_meta($user_ID, '_auction_watch');
            $args['post__in'] = $watchlist_ids;
        } elseif ($filter_params['products_group'] === 'user_uploaded') {
            $user_ID = get_current_user_id();
            $post__in = Growtype_Wc_Product::get_user_created_products_ids($user_ID);
            $args['post__in'] = $post__in;
            set_query_var('visibility', 'any');
        } elseif ($filter_params['products_group'] === 'user_active_bids') {
            $bids_ids = Growtype_Wc_Auction::user_active_bids_ids(get_current_user_id());
            $args['post__in'] = $bids_ids;
        }
    }

    if (isset($filter_params['categories_ids']) && !empty($filter_params['categories_ids'])) {
        $args['tax_query'] = array (
            array (
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $filter_params['categories_ids']
            ),
        );

        if (count($filter_params['categories_ids']) > 1) {
            $args['tax_query'][0]['operator'] = 'AND';
        }
    }

    if (isset($filter_params['products_group']) && $filter_params['products_group'] !== 'user_uploaded') {
        $visibility_tax_data = array (
            array (
                'taxonomy' => 'product_visibility',
                'field' => 'name',
                'terms' => 'exclude-from-catalog',
                'operator' => 'NOT IN',
            )
        );

        if (isset($args['tax_query'])) {
            array_push($args['tax_query'], $visibility_tax_data);
        } else {
            $args['tax_query'] = $visibility_tax_data;
        }
    }

    $args['paged'] = isset($_POST['page_nr']) ? (int)$_POST['page_nr'] : 1;
    $args['limit'] = apply_filters('loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page());

    /**
     * Meta key and values
     */
    if (isset($filter_params['meta_key']) && !empty($filter_params['meta_key']) && isset($filter_params['meta_values']) && !empty($filter_params['meta_values'])) {
        $meta_query = [];
        foreach ($filter_params['meta_values'] as $meta_value) {
            array_push($meta_query, [
                'key' => $filter_params['meta_key'],
                'value' => $meta_value,
                'compare' => 'LIKE'
            ]);
        }

        $args['meta_query'] = $meta_query;
        $args['meta_query']['relation'] = 'OR';
    }

    /**
     * Extend arguments
     */
    $args = apply_filters('growtype_wc_catalog_filtering_args', $args, $filter_params);

    return new WP_Query($args);
}
