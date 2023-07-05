<?php

/**
 * Woocommerce coupon discount
 */
add_shortcode('growtype_wc_features', 'growtype_wc_features_shorcode');
function growtype_wc_features_shorcode($atts)
{
    $items = [
        [
            'title' => 'Fast delivery',
            'description' => '3-6 business days',
            'icon' => 'fas fa-truck',
        ],
        [
            'title' => 'Free shipping',
            'description' => 'Free shipping over €59',
            'icon' => 'fa-solid fa-gift',
        ],
        [
            'title' => 'Sustainability',
            'description' => 'Products from sustainable materials',
            'icon' => 'fa-solid fa-earth-americas',
        ],
        [
            'title' => 'Safe payments',
            'description' => 'Quick and safe online payments',
            'icon' => 'fa-solid fa-credit-card',
        ]
    ];

    return growtype_wc_include_view('shortcodes.features', [
        'items' => $items,
    ]);
}
