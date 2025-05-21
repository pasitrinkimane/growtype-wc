<?php

/**
 * @param $query
 * @return void
 */
add_action('pre_get_posts', 'growtype_wc_admin_woocommerce_orders_default_order');
function growtype_wc_admin_woocommerce_orders_default_order($query)
{
    if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order' && !isset($_GET['orderby']) && !isset($_GET['order'])) {
        $query->set('orderby', 'modified');
        $query->set('order', 'desc');
    }
}

add_filter('manage_woocommerce_page_wc-orders_columns', 'growtype_wc_add_payment_method_column');
function growtype_wc_add_payment_method_column($columns)
{
    $new_columns = [];

    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ('order_status' === $key) {
            $new_columns['payment_method_title'] = __('Payment Method', 'growtype-wc');
        }
    }

    return $new_columns;
}

add_action('manage_woocommerce_page_wc-orders_custom_column', 'growtype_wc_display_payment_method_column', 10, 2);
function growtype_wc_display_payment_method_column($column, $order)
{
    if ('payment_method_title' === $column) {
        $payment_method_title = $order->get_payment_method_title();

        if (empty($payment_method_title)) {
            $payment_method_key = $order->get_payment_method();
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $payment_method_title = $available_gateways[$payment_method_key]->method_title;
        }

        echo esc_html($payment_method_title);
    }
}
