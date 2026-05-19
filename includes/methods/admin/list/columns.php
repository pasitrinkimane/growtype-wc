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
    $columns['order_tags'] = 'Tags';
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
    
    if ($column == 'order_tags') {
        $order = wc_get_order($post_id);
        
        if ($order) {
            growtype_wc_render_order_tags_column_content($order);
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
    
    if ($column == 'order_tags') {
        growtype_wc_render_order_tags_column_content($order);
    }
}

/**
 * Helper to fetch unique tags associated with a WooCommerce order.
 */
function growtype_wc_get_order_tags($order)
{
    if (!$order instanceof WC_Order) {
        return [];
    }

    $tags = [];

    return apply_filters('growtype_wc_get_order_tags', $tags, $order);
}

/**
 * Render order tags as clean, styled premium badge pills.
 */
function growtype_wc_render_order_tags_column_content($order)
{
    $tags = growtype_wc_get_order_tags($order);
    
    if (empty($tags)) {
        echo apply_filters('growtype_wc_order_tags_empty_placeholder', '<span style="color:#999; font-style:italic;">—</span>', $order);
        return;
    }

    echo '<div class="order-tag-badges-container" style="display:flex; flex-wrap:wrap; gap:4px; max-width:200px;">';
    
    $rendered_tags = [];
    
    foreach ($tags as $tag) {
        if (is_array($tag)) {
            $tag_name   = $tag['name'] ?? '';
            $bg_color   = $tag['bg_color'] ?? '#e2e8f0';
            $text_color = $tag['text_color'] ?? '#475569';
        } else {
            $tag_name   = $tag;
            $bg_color   = '#e2e8f0';
            $text_color = '#475569';
        }

        if (empty($tag_name) || in_array($tag_name, $rendered_tags, true)) {
            continue;
        }
        $rendered_tags[] = $tag_name;

        // Styling variants are applied flexibly via filters.
        $badge_styles = apply_filters('growtype_wc_order_tag_badge_styles', [
            'bg_color'   => $bg_color,
            'text_color' => $text_color,
        ], $tag_name, $order);

        $bg_color   = $badge_styles['bg_color'] ?? $bg_color;
        $text_color = $badge_styles['text_color'] ?? $text_color;

        $badge_html = sprintf(
            '<span class="order-tag-badge" style="background-color:%s; color:%s; font-size:11px; font-weight:600; padding:2px 8px; border-radius:12px; white-space:nowrap; text-transform:capitalize;">%s</span>',
            esc_attr($bg_color),
            esc_attr($text_color),
            esc_html(str_replace(['_', '-'], ' ', $tag_name))
        );

        echo apply_filters('growtype_wc_order_tag_badge_html', $badge_html, $tag_name, $order, $bg_color, $text_color);
    }
    echo '</div>';
}
