<?php

/**
 * // Wishlist table shortcode
 */

add_shortcode('growtype_wc_wishlist', 'growtype_wc_wishlist');
function growtype_wc_wishlist($atts, $content = null)
{
    extract(shortcode_atts(array (), $atts));

    return '<div class="wishlist-preview"></div>';
}
