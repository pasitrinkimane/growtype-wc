<?php

/**
 * Shipping fields
 */
add_filter('woocommerce_shipping_fields', 'growtype_wc_shipping_fields');
function growtype_wc_shipping_fields($fields)
{
    return $fields;
}
