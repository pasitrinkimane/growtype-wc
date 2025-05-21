<?php

/**
 * Woocommerce coupon discount
 */
add_shortcode('growtype_wc_countdown', 'growtype_wc_countdown_callback');

function growtype_wc_countdown_callback($atts)
{
    $time = $atts['time'] ?? "60";
    $compact = $atts['compact'] ?? "false";
    $format = $atts['format'] ?? "d H:m:s";
    $labels = $atts['labels'] ?? "";
    $labels = !is_array($labels) ? $labels : implode(',', $labels);

    $id_base = str_replace(' ', '', $time . $format . $compact . $labels);
    $unique_id = md5($id_base);

    return '<div 
        id="growtype-wc-countdown-' . $unique_id . '"
        class="auction-time-countdown" 
        data-time="' . esc_attr($time) . '" 
        data-format="' . esc_attr($format) . '" 
        data-compact="' . esc_attr($compact) . '"
        data-labels="' . esc_attr($labels) . '"
    ></div>';
}
