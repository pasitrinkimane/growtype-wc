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
