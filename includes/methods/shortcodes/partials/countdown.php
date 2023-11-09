<?php

/**
 * Woocommerce coupon discount
 */
add_shortcode('growtype_wc_countdown', 'growtype_wc_countdown_callback');
function growtype_wc_countdown_callback($atts)
{
    $time = $atts['time'] ?? "60";
    $compact = $atts['compact'] ?? "true";
    $format = $atts['format'] ?? "d H:m:s";

    return '<div class="auction-time-countdown" data-time="' . $time . '" data-format="' . $format . '" data-compact-counter="' . $compact . '"></div>';
}
