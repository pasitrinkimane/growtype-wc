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

function growtype_wc_single_item_available($product_id)
{
    $sold_individually = get_post_meta($product_id, '_sold_individually', true) === 'yes';
    $manage_stock = get_post_meta($product_id, '_manage_stock', true) === 'yes';
    $stock = (int)get_post_meta($product_id, '_stock', true);

    $single_item = false;

    if ($sold_individually || $manage_stock && $stock <= 1 || growtype_wc_selling_type_single_item()) {
        $single_item = true;
    }

    return $single_item;
}
