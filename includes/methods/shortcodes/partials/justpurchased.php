<?php

/**
 * Woocommerce coupon discount
 */
add_shortcode('growtype_wc_justpurchased', 'growtype_wc_justpurchased_callback');
function growtype_wc_justpurchased_callback($atts)
{
    $users = [
        ['name' => 'Daniel', 'location' => 'Pennsylvania'],
        ['name' => 'Michael', 'location' => 'California'],
        ['name' => 'Ethan', 'location' => 'Florida'],
        ['name' => 'Jackson', 'location' => 'Ohio'],
        ['name' => 'Liam', 'location' => 'Michigan'],
        ['name' => 'Mason', 'location' => 'Arizona'],
        ['name' => 'Lucas', 'location' => 'Washington'],
        ['name' => 'Noah', 'location' => 'Colorado'],
        ['name' => 'Elijah', 'location' => 'Nevada'],
        ['name' => 'Carter', 'location' => 'Louisiana'],
        ['name' => 'William', 'location' => 'Tennessee'],
        ['name' => 'Jacob', 'location' => 'New York'],
        ['name' => 'Matthew', 'location' => 'Texas'],
        ['name' => 'Benjamin', 'location' => 'Illinois'],
        ['name' => 'James', 'location' => 'Georgia'],
        ['name' => 'Logan', 'location' => 'North Carolina'],
        ['name' => 'Oliver', 'location' => 'Wisconsin'],
        ['name' => 'Henry', 'location' => 'Washington'],
        ['name' => 'Alexander', 'location' => 'Virginia'],
        ['name' => 'Joseph', 'location' => 'Massachusetts'],
    ];

    $random_user = array_shuffle($users)[0];

    $content_main_default = '<strong>' . $random_user['name'] . '</strong> from <strong>' . $random_user['location'] . '</strong> just purchased.';

    $content_main = $atts['content_main'] ?? $content_main_default;
    $product_image = $atts['product_image'] ?? "";

    return growtype_wc_include_view('shortcodes.popup.justpurchased', [
        'content_main' => $content_main,
        'image_url' => $product_image,
    ]);
}
