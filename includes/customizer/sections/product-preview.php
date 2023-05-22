<?php

$wp_customize->add_section(
    'woocommerce_product_preview_page',
    array (
        'title' => __('Product Preview', 'growtype-wc'),
        'panel' => 'woocommerce',
    )
);

/**
 * Intro
 */
$wp_customize->add_setting('woocommerce_product_preview_page_details',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'woocommerce_product_preview_page_details',
    array (
        'label' => __('Product Preview Settings'),
        'description' => __('Below you can change product preview details.'),
        'section' => 'woocommerce_product_preview_page'
    )
));

/**
 * Product preview style
 */
$wp_customize->add_setting('woocommerce_product_preview_style',
    array (
        'default' => 'product-style-1',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_product_preview_style',
    array (
        'label' => __('Product Preview Style', 'growtype-wc'),
        'description' => __('Change product preview style.', 'growtype-wc'),
        'section' => 'woocommerce_product_preview_page',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => array (
            'product-style-1' => __('Style 1', 'growtype-wc'),
            'product-style-2' => __('Style 2', 'growtype-wc')
        )
    )
));

/**
 * Sale flash
 */
$wp_customize->add_setting('woocommerce_product_preview_sale_badge',
    array (
        'default' => 1,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_preview_sale_badge',
    array (
        'label' => esc_html__('Sale badge'),
        'section' => 'woocommerce_product_preview_page',
        'description' => __('Enable/disable sale badge (flash).', 'growtype-wc'),
    )
));

/**
 * product title
 */
$wp_customize->add_setting('woocommerce_product_page_shop_loop_item_title',
    array (
        'default' => 1,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_shop_loop_item_title',
    array (
        'label' => esc_html__('Product title'),
        'section' => 'woocommerce_product_preview_page',
        'description' => __('Enable/disable product title.', 'growtype-wc'),
    )
));


/**
 * product price
 */
$wp_customize->add_setting('woocommerce_product_page_shop_loop_item_price',
    array (
        'default' => 1,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_shop_loop_item_price',
    array (
        'label' => esc_html__('Product price'),
        'section' => 'woocommerce_product_preview_page',
        'description' => __('Enable/disable product price.', 'growtype-wc'),
    )
));

/**
 * Add to cart button status
 */
$wp_customize->add_setting('woocommerce_product_preview_cta_btn',
    array (
        'default' => 1,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_preview_cta_btn',
    array (
        'label' => esc_html__('CTA Button'),
        'description' => __('Enable/disable cta button.', 'growtype-wc'),
        'section' => 'woocommerce_product_preview_page',
    )
));

/**
 * CTA button label
 */
$wp_customize->add_setting('woocommerce_product_preview_cta_label', array (
    'capability' => 'edit_theme_options',
    'default' => 'Preview product',
    'sanitize_callback' => array ($this, 'woocommerce_product_preview_cta_label_translation')
));

$wp_customize->add_control('woocommerce_product_preview_cta_label', array (
    'type' => 'text',
    'section' => 'woocommerce_product_preview_page',
    'label' => __('CTA Button Label'),
    'description' => __('Default: "Preview product"')
));
