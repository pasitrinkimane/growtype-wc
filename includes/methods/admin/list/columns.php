<?php

/**
 *
 */
add_filter('manage_edit-product_columns', 'admin_products_type_column');
function admin_products_type_column($columns)
{
    $columns['product_type'] = 'Type';
    return $columns;
}

/**
 *
 */
add_action('manage_product_posts_custom_column', 'admin_products_type_column_content', 10, 2);
function admin_products_type_column_content($column, $product_id)
{
    if ($column == 'product_type') {
        $product = wc_get_product($product_id);
        echo get_the_terms($product_id, 'product_type')[0]->slug;
    }
}

/**
 *
 */
#todo filtering products
//add_action('pre_get_posts', 'growtype_wc__pre_get_posts');
function growtype_wc__pre_get_posts($query)
{
    if (!is_admin() && !is_main_query()) {
        return;
    }

    $post_type = $query->query['post_type'] ?? null;

    if (!empty($post_type) && $post_type === 'product') {
        $query->set('meta_query', array (
                'meta_value' => array (
                    'key' => '_preview_style',
                    'value' => 'plan',
                    'compare' => '!=',
                )
            )
        );
    }
}

/**
 * Orders columns
 */
add_filter('manage_edit-shop_order_columns', 'growtype_wc_admin_orders_product_column');
add_filter('manage_woocommerce_page_wc-orders_columns', 'growtype_wc_admin_orders_product_column');
function growtype_wc_admin_orders_product_column($columns)
{
    $columns['order_products'] = 'Product';
    return $columns;
}

/**
 * Orders columns content
 */
add_action('manage_shop_order_posts_custom_column', 'growtype_wc_admin_orders_product_column_content', 10, 2);
function growtype_wc_admin_orders_product_column_content($column, $post_id)
{
    if ($column == 'order_products') {
        $order = wc_get_order($post_id);
        if ($order) {
            $items = $order->get_items();
            $product_names = [];
            foreach ($items as $item) {
                $product_names[] = $item->get_name();
            }
            echo implode(', ', $product_names);
        }
    }
}

add_action('manage_woocommerce_page_wc-orders_custom_column', 'growtype_wc_admin_orders_hpos_product_column_content', 10, 2);
function growtype_wc_admin_orders_hpos_product_column_content($column, $order)
{
    if ($column == 'order_products') {
        $items = $order->get_items();
        $product_names = [];
        foreach ($items as $item) {
            $product_names[] = $item->get_name();
        }
        echo implode(', ', $product_names);
    }
}
