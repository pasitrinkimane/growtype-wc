<?php

/**
 * @return bool
 */
function growtype_wc_wishlist_page_icon()
{
    $disabled = get_theme_mod('woocommerce_wishlist_page_icon', true);

    return $disabled ? true : false;
}
