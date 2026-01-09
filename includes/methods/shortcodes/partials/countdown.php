<?php

/**
 * Woocommerce coupon discount
 */
add_shortcode('growtype_wc_countdown', 'growtype_wc_countdown_callback');

function growtype_wc_countdown_callback( $atts ) {
    // Merge incoming attributes with defaults
    $atts = shortcode_atts(
        [
            'time'        => '60',                // Seconds or timestamp offset
            'format'      => 'd H:m:s',           // Date format for countdown
            'compact'     => 'false',             // Use compact labels ("true" or "false")
            'labels'      => '',                  // Comma-separated labels or JSON array
            'description' => '',                  // Optional description under countdown
        ],
        $atts,
        'growtype_countdown'
    );

    // Normalize labels: accept JSON array or comma-separated string
    $labels = $atts['labels'];
    if ( empty( $labels ) ) {
        $labels = '';
    } elseif ( strpos( $labels, '[' ) === 0 ) {
        // JSON-style array
        $decoded = json_decode( $labels, true );
        if ( is_array( $decoded ) ) {
            $labels = implode( ',', $decoded );
        }
    } elseif ( is_array( $labels ) ) {
        // Direct array passed
        $labels = implode( ',', $labels );
    }

    // Build a unique ID from parameters
    $id_base   = implode( '|', [ $atts['time'], $atts['format'], $atts['compact'], $labels ] );
    $unique_id = 'growtype-wc-countdown-' . wp_hash( $id_base );

    // Prepare data attributes
    $data_attrs = [
        'data-time'        => $atts['time'],
        'data-format'      => $atts['format'],
        'data-compact'     => $atts['compact'],
        'data-description' => $atts['description'],
    ];
    if ( $labels !== '' ) {
        $data_attrs['data-labels'] = $labels;
    }

    // Build the attribute string, escaping each value
    $attr_str = "id=\"$unique_id\" class=\"gwc-time-countdown\"";
    foreach ( $data_attrs as $name => $value ) {
        $attr_str .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
    }

    // Return the countdown container markup
    return sprintf( '<div %s></div>', $attr_str );
}
