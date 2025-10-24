<?php

/**
 * @param $product_id
 * @return bool
 */
function growtype_wc_product_is_trial($product_id)
{
    $product_type_trial = get_post_meta($product_id, '_growtype_wc_trial', true);

    return !empty($product_type_trial) && $product_type_trial === 'yes' ? true : false;
}

function growtype_wc_get_trial_duration($product_id)
{
    return growtype_wc_product_is_trial($product_id) ? (int)get_post_meta($product_id, '_growtype_wc_trial_duration', true) : null;
}

function growtype_wc_get_trial_price($product_id)
{
    return growtype_wc_product_is_trial($product_id) ? (float)get_post_meta($product_id, '_growtype_wc_trial_price', true) : null;
}

function growtype_wc_get_trial_period($product_id)
{
    return growtype_wc_product_is_trial($product_id) ? get_post_meta($product_id, '_growtype_wc_trial_period', true) : null;
}
