<?php

$wp_customize->add_section(
    'woocommerce_thankyou_page',
    array (
        'title' => __('Thank You Page', 'growtype-wc'),
        'panel' => 'woocommerce',
    )
);

/**
 * Intro
 */
$wp_customize->add_setting('woocommerce_thankyou_page_details',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'woocommerce_thankyou_page_details',
    array (
        'label' => __('Thank You Page'),
        'description' => __('Below you can change "thank you" page settings. Page will be visible after checkout.'),
        'section' => 'woocommerce_thankyou_page'
    )
));

/**
 * Style
 */
$wp_customize->add_setting('woocommerce_thankyou_page_style',
    array (
        'default' => '',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_thankyou_page_style',
    array (
        'label' => __('Page Style', 'growtype-wc'),
        'description' => esc_html__('Choose page style', 'growtype-wc'),
        'section' => 'woocommerce_thankyou_page',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => array (
            'default' => __('Default', 'growtype-wc'),
            'centered' => __('Centered', 'growtype-wc')
        )
    )
));

/**
 * Intro content
 */
$wp_customize->add_setting('woocommerce_thankyou_page_intro_content',
    array (
        'default' => '',
        'transport' => 'postMessage',
        'sanitize_callback' => array ($this, 'woocommerce_thankyou_page_intro_content_translation')
    )
);

$wp_customize->add_control(new Skyrocket_TinyMCE_Custom_control($wp_customize, 'woocommerce_thankyou_page_intro_content',
    array (
        'label' => __('Intro Content'),
        'description' => __('Intro details.'),
        'section' => 'woocommerce_thankyou_page',
        'priority' => 10,
        'input_attrs' => array (
            'class' => 'qtranxs-translatable',
            'toolbar1' => 'formatselect bold italic bullist numlist alignleft aligncenter alignright link',
            'mediaButtons' => true,
        )
    )
));

/**
 * Intro content not active account
 */
$wp_customize->add_setting('woocommerce_thankyou_page_intro_content_access_platform',
    array (
        'default' => '',
        'transport' => 'postMessage',
        'sanitize_callback' => array ($this, 'woocommerce_thankyou_page_intro_content_access_platform_translation')
    )
);

$wp_customize->add_control(new Skyrocket_TinyMCE_Custom_control($wp_customize, 'woocommerce_thankyou_page_intro_content_access_platform',
    array (
        'label' => __('Intro Content - Access Platform'),
        'description' => __('Extra details when account access is enabled.'),
        'section' => 'woocommerce_thankyou_page',
        'priority' => 10,
        'input_attrs' => array (
            'class' => 'qtranxs-translatable',
            'toolbar1' => 'formatselect bold italic bullist numlist alignleft aligncenter alignright link',
            'mediaButtons' => true,
        )
    )
));

/**
 *
 */
$wp_customize->add_setting('woocommerce_thankyou_page_order_overview',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_thankyou_page_order_overview',
    array (
        'label' => esc_html__('Order Overview'),
        'section' => 'woocommerce_thankyou_page',
        'description' => __('Enable/disable order overview.', 'growtype-wc'),
    )
));

/**
 *
 */
$wp_customize->add_setting('woocommerce_thankyou_page_order_details',
    array (
        'default' => 1,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_thankyou_page_order_details',
    array (
        'label' => esc_html__('Order Details'),
        'section' => 'woocommerce_thankyou_page',
        'description' => __('Enable/disable order details.', 'growtype-wc'),
    )
));

/**
 *
 */
$wp_customize->add_setting('woocommerce_thankyou_page_customer_details',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_thankyou_page_customer_details',
    array (
        'label' => esc_html__('Customer Details'),
        'section' => 'woocommerce_thankyou_page',
        'description' => __('Enable/disable customer details.', 'growtype-wc'),
    )
));

/**
 *
 */
$wp_customize->add_setting('woocommerce_thankyou_page_download_details',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_thankyou_page_download_details',
    array (
        'label' => esc_html__('Download Details'),
        'section' => 'woocommerce_thankyou_page',
        'description' => __('Enable/disable download details.', 'growtype-wc'),
    )
));

/**
 *
 */
$wp_customize->add_setting('woocommerce_thankyou_page_order_again',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_thankyou_page_order_again',
    array (
        'label' => esc_html__('Order Again'),
        'section' => 'woocommerce_thankyou_page',
        'description' => __('Enable/disable order again link.', 'growtype-wc'),
    )
));
