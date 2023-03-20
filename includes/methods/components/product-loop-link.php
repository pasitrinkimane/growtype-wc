<?php

/**
 * Show smaller price for variable product
 */
add_filter('woocommerce_template_loop_product_link_open', 'growtype_wc_template_loop_product_link');
add_filter('woocommerce_template_loop_product_link_close', 'growtype_wc_template_loop_product_link');
function growtype_wc_template_loop_product_link($link)
{
    return '';
}
