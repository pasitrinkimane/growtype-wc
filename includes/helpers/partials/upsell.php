<?php

/**
 * @param $product_id
 * @return bool
 */
function growtype_wc_get_upsell_position($product_id)
{
    return get_post_meta($product_id, '_growtype_wc_upsell_position', true);
}
