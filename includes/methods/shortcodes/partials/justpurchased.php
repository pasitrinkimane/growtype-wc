<?php

/**
 * Woocommerce coupon discount
 */
add_shortcode('growtype_wc_justpurchased', 'growtype_wc_justpurchased_callback');
function growtype_wc_justpurchased_callback($atts)
{
    $content_main = $atts['content_main'] ?? "";
    $product_image = $atts['product_image'] ?? "";

    return growtype_wc_include_view('shortcodes.popup.justpurchased', [
        'content_main' => $content_main,
        'image_url' => $product_image,
    ]);
}
