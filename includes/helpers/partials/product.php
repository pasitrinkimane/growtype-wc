<?php

function growtype_wc_get_all_products()
{
    $args = array (
        'limit' => -1,
        'orderby' => 'menu_order',
        'order' => 'DESC'
    );

    $query = new WC_Product_Query($args);

    return $query->get_products();
}

/**
 * @param $product_id
 * @return bool
 */
function growtype_wc_product_is_sold_individually($product_id)
{
    $sold_individually = get_post_meta($product_id, '_sold_individually', true) === 'yes';
    $manage_stock = get_post_meta($product_id, '_manage_stock', true) === 'yes';
    $stock = (int)get_post_meta($product_id, '_stock', true);

    if ($sold_individually || $manage_stock && $stock <= 1 || growtype_wc_selling_type_single_item() || growtype_wc_product_is_subscription($product_id)) {
        return true;
    }

    return false;
}

/**
 * @param $product_id
 * @return bool
 */
function growtype_wc_product_is_subscription($product_id)
{
    $product_type_subscription = get_post_meta($product_id, Growtype_Wc_Subscription::META_KEY, true);

    return !empty($product_type_subscription) && $product_type_subscription === 'yes' ? true : false;
}

/**
 * @param $query_args
 * @param $params
 * @return mixed|string
 */
function growtype_wc_render_products($query_args, $params = [])
{
    global $woocommerce_loop;

    $products = new WP_Query($query_args);

    /**
     * Default params
     */
    $params['current_page'] = isset($params['current_page']) ? $params['current_page'] : 1;
    $params['preview_style'] = isset($params['preview_style']) ? $params['preview_style'] : '';
    $params['products_group'] = isset($params['products_group']) ? $params['products_group'] : '';
    $params['visibility'] = isset($params['visibility']) ? $params['visibility'] : '';

    $params = array_merge($query_args, $params);

    if ($products->have_posts()) {
        wc_set_loop_prop('current_page', $params['current_page']);
        wc_set_loop_prop('is_paginated', wc_string_to_bool(true));
        wc_set_loop_prop('page_template', get_page_template_slug());
        wc_set_loop_prop('per_page', $query_args['posts_per_page']);
        wc_set_loop_prop('total', $products->post_count);
        wc_set_loop_prop('total_pages', $products->max_num_pages);

        if (isset($params['columns']) && !empty($params['columns'])) {
            $woocommerce_loop['columns'] = $params['columns'];
        }

        if (isset($params['cta_btn']) && !empty($params['cta_btn'])) {
            set_query_var('cta_btn', $params['cta_btn']);
        }

        /**
         * Render
         */
        ob_start();

        if (isset($params['before_shop_loop']) && $params['before_shop_loop'] === 'true') {
            do_action('woocommerce_before_shop_loop');
        }

        wc_get_template('loop/loop-start.php', [
            'preview_style' => $params['preview_style'],
            'products_group' => $params['products_group']
        ]);

        set_query_var('visibility', $params['visibility']);

        if (isset($params['edit_product']) && $params['edit_product'] === 'true') {
            set_query_var('preview_permalink', true);
        }

        if ($params['preview_style'] === 'table') {
            echo growtype_wc_include_view('woocommerce.components.table.product-table', ['products' => $products]);
        } else {
            while ($products->have_posts()) : $products->the_post();
                echo growtype_wc_include_view('woocommerce.content-product', ['params' => $params]);
            endwhile;
        }

        wc_get_template('loop/loop-end.php');

        if (isset($after_shop_loop) && $after_shop_loop) {
            do_action('woocommerce_after_shop_loop');
        }

        wp_reset_postdata();

        $render = '<div class="woocommerce">' . ob_get_clean() . '</div>';

        $render = apply_filters('growtype_wc_render_products_after', $render, $query_args, $params);
    }

    return isset($render) && !empty($render) ? $render : $params['not_found_message_html'];
}
