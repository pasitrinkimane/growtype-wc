<?php

/**
 * Add sign after quantity input
 */
add_action('woocommerce_before_quantity_input_field', 'growtype_wc_before_quantity_input_field');
function growtype_wc_before_quantity_input_field()
{
    if (!growtype_wc_product_is_sold_individually(get_the_ID())) {
        echo '<div class="btn btn-down">-</div>';
    }
}

/**
 * Add sign after quantity input
 */
add_action('woocommerce_after_quantity_input_field', 'growtype_wc_after_quantity_input_field');
function growtype_wc_after_quantity_input_field()
{
    if (!growtype_wc_product_is_sold_individually(get_the_ID())) {
        echo '<div class="btn btn-up">+</div>';
    }
}

/**
 * Add sign after quantity input
 */
add_filter('woocommerce_is_sold_individually', 'growtype_wc_woocommerce_is_sold_individually', 10, 2);
function growtype_wc_woocommerce_is_sold_individually($return, $product)
{
    return growtype_wc_product_is_sold_individually($product->get_id());
}
