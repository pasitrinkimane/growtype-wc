<?php

/**
 * Product rating
 */
add_action('woocommerce_after_shop_loop_item_title', 'woocommerce_after_shop_loop_item_title_extend', 5);
function woocommerce_after_shop_loop_item_title_extend()
{
    global $product;

    $rating = $product->get_average_rating();

    $show_rating = get_theme_mod('woocommerce_product_preview_show_rating', false);

    if (wc_review_ratings_enabled() && $rating == 0 && $show_rating) {
        echo '<div class="star-rating ehi-star-rating"><span style="width:' . (($rating / 5) * 100) . '%"></span></div>';
    }
}
