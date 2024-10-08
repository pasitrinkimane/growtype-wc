<?php
/**
 * Order by switch
 */
$wp_customize->add_setting('wc_catalog_result_count_hide',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'wc_catalog_result_count_hide',
    array (
        'label' => esc_html__('Hide results count'),
        'section' => 'woocommerce_product_catalog',
        'description' => __('Hide products result count label.', 'growtype-wc'),
    )
));

/**
 * Products preview style
 */
$wp_customize->add_setting('wc_catalog_products_layout_style',
    array (
        'default' => 'default',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'wc_catalog_products_layout_style',
    array (
        'label' => __('Products layout', 'growtype-wc'),
        'description' => esc_html__('Choose products layout style', 'growtype-wc'),
        'section' => 'woocommerce_product_catalog',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => $this->products_layout_styles
    )
));

/**
 * Intro access
 */
$wp_customize->add_setting('wc_catalog_access_notice',
    array (
        'default' => '',
        'transport' => 'postMessage',
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'wc_catalog_access_notice',
    array (
        'label' => __('Access'),
        'description' => __('Below you can change shop page access settings'),
        'section' => 'woocommerce_product_catalog'
    )
));

/**
 * Shop disable access
 */
$wp_customize->add_setting('catalog_disable_access',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'catalog_disable_access',
    array (
        'label' => esc_html__('Disable Access'),
        'section' => 'woocommerce_product_catalog',
        'description' => __('Disable access to "shop" page', 'growtype-wc'),
    )
));

/**
 * Intro orderby
 */
$wp_customize->add_setting('wc_catalog_orderby_intro',
    array (
        'default' => '',
        'transport' => 'postMessage',
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'wc_catalog_orderby_intro',
    array (
        'label' => __('Order By'),
        'description' => __('Below you can change product order select settings'),
        'section' => 'woocommerce_product_catalog'
    )
));

/**
 * Order by switch
 */
$wp_customize->add_setting('wc_catalog_orderby_disable',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'wc_catalog_orderby_disable',
    array (
        'label' => esc_html__('Order By Disabled'),
        'section' => 'woocommerce_product_catalog',
        'description' => __('Order by enabled', 'growtype-wc'),
    )
));

/**
 * Order by disabled options
 */
$wp_customize->add_setting('catalog_orderby_switch_disabled_options',
    array (
        'default' => '',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'catalog_orderby_switch_disabled_options',
    array (
        'label' => __('Order By - Disabled Options', 'growtype-wc'),
        'description' => esc_html__('Which order by options should be disabled.', 'growtype-wc'),
        'section' => 'woocommerce_product_catalog',
        'input_attrs' => array (
            'placeholder' => __('Please select options...', 'growtype-wc'),
            'multiselect' => true,
        ),
        'choices' => [
            'popularity' => 'Popularity',
            'price' => 'Price',
            'price-desc' => 'Price descending',
            'date' => 'Date',
            'menu_order' => 'Menu order',
            'rating' => 'Rating',
        ]
    )
));

/**
 * Intro sidebar
 */
$wp_customize->add_setting('wc_catalog_sidebar_intro',
    array (
        'default' => '',
        'transport' => 'postMessage',
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'wc_catalog_sidebar_intro',
    array (
        'label' => __('Sidebar'),
        'description' => __('Below you can change shop sidebar settings'),
        'section' => 'woocommerce_product_catalog'
    )
));

/**
 * Sidebar switch
 */
$wp_customize->add_setting('catalog_sidebar_enabled',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'catalog_sidebar_enabled',
    array (
        'label' => esc_html__('"Shop" Sidebar'),
        'section' => 'woocommerce_product_catalog',
        'description' => __('Sidebar enabled/disabled', 'growtype-wc'),
    )
));

/**
 * Sidebar shop position
 */
$wp_customize->add_setting('sidebar_shop_position',
    array (
        'default' => 'left',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'sidebar_shop_position',
    array (
        'label' => __('"Shop" Sidebar Position', 'growtype-wc'),
        'description' => esc_html__('Choose shop sidebar position', 'growtype-wc'),
        'section' => 'woocommerce_product_catalog',
        'input_attrs' => array (
            'placeholder' => __('Choose position...', 'growtype-wc'),
            'multiselect' => false,
        ),
        'choices' => array (
            'left' => __('Left', 'growtype-wc'),
            'right' => __('Right', 'growtype-wc'),
        )
    )
));

/**
 * Intro featured image
 */
$wp_customize->add_setting('wc_catalog_featured_intro',
    array (
        'default' => '',
        'transport' => 'postMessage',
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'wc_catalog_featured_intro',
    array (
        'label' => __('Intro'),
        'description' => __('Below you can change shop page featured intro settings'),
        'section' => 'woocommerce_product_catalog'
    )
));

/**
 * Featured intro
 */
$wp_customize->add_setting('catalog_header_enabled',
    array (
        'default' => 0,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'catalog_header_enabled',
    array (
        'label' => esc_html__('Products Header Enabled'),
        'section' => 'woocommerce_product_catalog',
        'description' => __('Enable shop "products Header".', 'growtype-wc'),
    )
));

/**
 * Featured intro
 */
$wp_customize->add_setting('catalog_featured_intro_enabled',
    array (
        'default' => 0,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'catalog_featured_intro_enabled',
    array (
        'label' => esc_html__('Featured Intro Enabled'),
        'section' => 'woocommerce_product_catalog',
        'description' => __('Enable shop "featured image" as shop page intro image, if featured image is not empty.', 'growtype-wc'),
    )
));
