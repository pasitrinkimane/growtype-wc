<?php

$wp_customize->add_section(
    'woocommerce_cart_page',
    array (
        'title' => __('Cart', 'growtype-wc'),
        'panel' => 'woocommerce',
    )
);

/**
 *
 */
$wp_customize->add_setting('woocommerce_cart_enabled',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_cart_enabled',
    array (
        'label' => esc_html__('Cart preview'),
        'description' => __('Enable/disable cart preview sidebar.', 'growtype-wc'),
        'section' => 'woocommerce_cart_page',
    )
));

/**
 *
 */
$wp_customize->add_setting('woocommerce_cart_icon',
    array (
        'default' => 1,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_cart_icon',
    array (
        'label' => esc_html__('Cart Page Icon'),
        'description' => __('Enable/disable cart page icon.', 'growtype-wc'),
        'section' => 'woocommerce_cart_page',
    )
));

/**
 *
 */
$wp_customize->add_setting('woocommerce_skip_cart_page',
    array (
        'default' => 0,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_skip_cart_page',
    array (
        'label' => esc_html__('Skip Cart Page'),
        'description' => __('Skip cart page and go directly to checkout', 'growtype-wc'),
        'section' => 'woocommerce_cart_page',
    )
));
