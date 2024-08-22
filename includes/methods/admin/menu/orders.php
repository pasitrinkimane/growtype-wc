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
