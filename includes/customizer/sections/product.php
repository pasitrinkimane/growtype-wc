<?php

/**
 * Woocommerce Product Single Page
 */
$wp_customize->add_section(
    'woocommerce_product_page',
    array (
        'title' => __('Product Single Page', 'growtype-wc'),
        'priority' => 5,
        'panel' => 'woocommerce',
    )
);

/**
 * Access section
 */
$wp_customize->add_setting('woocommerce_product_page_access_details',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'woocommerce_product_page_access_details',
    array (
        'label' => __('Access'),
        'description' => __('Below you can change access settings'),
        'section' => 'woocommerce_product_page'
    )
));

/**
 * Access to product single page
 */
$wp_customize->add_setting('woocommerce_product_page_access_disabled',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_access_disabled',
    array (
        'label' => esc_html__('Disabled', 'growtype-wc'),
        'section' => 'woocommerce_product_page',
        'description' => __('Product page is disabled', 'growtype-wc'),
    )
));

/**
 * Breadcrumb
 */
$wp_customize->add_setting('woocommerce_product_page_breadcrumb_details',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'woocommerce_product_page_breadcrumb_details',
    array (
        'label' => __('Breadcrumb'),
        'description' => __('Below you can change breadcrumb settings'),
        'section' => 'woocommerce_product_page'
    )
));

/**
 * Breadcrumb status
 */
$wp_customize->add_setting('woocommerce_product_page_breadcrumb',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_breadcrumb',
    array (
        'label' => esc_html__('Enabled', 'growtype-wc'),
        'section' => 'woocommerce_product_page',
        'description' => __('Woocommerce breadcrumb', 'growtype-wc'),
    )
));

/**
 * Main IMG
 */
$wp_customize->add_setting('single_page_gallery_main_img',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'single_page_gallery_main_img',
    array (
        'label' => __('Main IMG'),
        'description' => __('Below you can change main IMG settings'),
        'section' => 'woocommerce_product_page'
    )
));

/**
 * Width
 */
$wp_customize->add_setting("single_page_gallery_main_img_width", array (
    "default" => "700",
    'type' => 'option',
    'capability' => 'manage_woocommerce',
));

$wp_customize->add_control('single_page_gallery_main_img_width', array (
    'label' => __('Main IMG Width', 'growtype-wc'),
    'description' => __('In pixels', 'growtype-wc'),
    'section' => 'woocommerce_product_page',
    'type' => 'number',
));

/**
 * Height
 */
$wp_customize->add_setting("single_page_gallery_main_img_height", array (
    "default" => "600",
    'type' => 'option',
    'capability' => 'manage_woocommerce',
));

$wp_customize->add_control('single_page_gallery_main_img_height', array (
    'label' => __('Main IMG Height', 'growtype-wc'),
    'description' => __('In pixels', 'growtype-wc'),
    'section' => 'woocommerce_product_page',
    'type' => 'number',
));

/**
 * Cropped
 */
$wp_customize->add_setting('single_page_gallery_main_img_cropped',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'single_page_gallery_main_img_cropped',
    array (
        'label' => esc_html__('Main IMG Cropped'),
        'section' => 'woocommerce_product_page',
        'description' => __('Main IMG is cropped', 'growtype-wc'),
    )
));

/**
 * Gallery
 */
$wp_customize->add_setting('single_page_gallery_details',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'single_page_gallery_details',
    array (
        'label' => __('Gallery'),
        'description' => __('Below you can change gallery settings'),
        'section' => 'woocommerce_product_page'
    )
));

/**
 * Shop gallery type
 */
$wp_customize->add_setting('woocommerce_product_page_gallery_type',
    array (
        'default' => 'woocommerce-product-gallery-type-2',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_product_page_gallery_type',
    array (
        'label' => __('Product Gallery Type', 'growtype-wc'),
        'description' => esc_html__('Choose product gallery type', 'growtype-wc'),
        'section' => 'woocommerce_product_page',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => array (
            'woocommerce-product-gallery-type-1' => __('Horizontal', 'growtype-wc'),
            'woocommerce-product-gallery-type-2' => __('Vertical', 'growtype-wc'),
            'woocommerce-product-gallery-type-3' => __('Grid', 'growtype-wc'),
            'woocommerce-product-gallery-type-4' => __('Full width', 'growtype-wc'),
            'woocommerce-product-gallery-type-5' => __('Main image constant', 'growtype-wc'),
        )
    )
));

/**
 * Gallery animation
 */
$wp_customize->add_setting('woocommerce_product_page_gallery_animation',
    array (
        'default' => 'slide',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_product_page_gallery_animation',
    array (
        'label' => __('Gallery Animation', 'growtype-wc'),
        'description' => esc_html__('Choose product gallery animation', 'growtype-wc'),
        'section' => 'woocommerce_product_page',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => array (
            'slide' => __('Slide', 'growtype-wc'),
            'fade' => __('Fade', 'growtype-wc'),
        )
    )
));

/**
 * Gallery nav arrows
 */
$wp_customize->add_setting('woocommerce_product_page_gallery_nav_arrows',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_gallery_nav_arrows',
    array (
        'label' => esc_html__('Nav arrows'),
        'section' => 'woocommerce_product_page',
        'description' => __('Navigation arrows', 'growtype-wc'),
    )
));

/**
 * Photoswipe
 */
$wp_customize->add_setting('woocommerce_product_page_gallery_lightbox',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_gallery_lightbox',
    array (
        'label' => esc_html__('Lightbox', 'growtype-wc'),
        'section' => 'woocommerce_product_page',
        'description' => __('Lightbox enabled/disabled', 'growtype-wc'),
    )
));

/**
 * Adaptive gallery thumbnails height
 */
$wp_customize->add_setting('woocommerce_product_page_gallery_thumbnails_adaptive_height',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_gallery_thumbnails_adaptive_height',
    array (
        'label' => esc_html__('Adaptive thumbnails height'),
        'section' => 'woocommerce_product_page',
        'description' => __('Adaptive gallery thumbnails height', 'growtype-wc'),
    )
));

/**
 * Zoom icon
 */
$wp_customize->add_setting('woocommerce_product_page_gallery_trigger_icon',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_gallery_trigger_icon',
    array (
        'label' => esc_html__('Image zoom icon'),
        'section' => 'woocommerce_product_page',
        'description' => __('Icon which zooms image', 'growtype-wc'),
    )
));

/**
 * Content
 */
$wp_customize->add_setting('woocommerce_product_page_main_information_details',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'woocommerce_product_page_main_information_details',
    array (
        'label' => __('Main Information'),
        'description' => __('Below you can change main information settings'),
        'section' => 'woocommerce_product_page'
    )
));

/**
 * Payment details
 */
$wp_customize->add_setting('woocommerce_product_page_size_guide_details',
    array (
        'default' => '',
        'transport' => 'postMessage',
        'sanitize_callback' => array ($this, 'woocommerce_product_page_size_guide_details_translation')
    )
);

$wp_customize->add_control(new Skyrocket_TinyMCE_Custom_control($wp_customize, 'woocommerce_product_page_size_guide_details',
    array (
        'label' => __('Size Guide Details'),
        'description' => __('Size guide information'),
        'section' => 'woocommerce_product_page',
        'input_attrs' => array (
            'class' => 'qtranxs-translatable',
            'toolbar1' => 'bold italic bullist numlist alignleft aligncenter alignright link',
            'toolbar2' => 'formatselect',
            'mediaButtons' => true,
        )
    )
));

/**
 * Payment details
 */
$wp_customize->add_setting('woocommerce_product_page_payment_details',
    array (
        'default' => '',
        'transport' => 'postMessage',
        'sanitize_callback' => array ($this, 'woocommerce_product_page_payment_details_translation')
    )
);

$wp_customize->add_control(new Skyrocket_TinyMCE_Custom_control($wp_customize, 'woocommerce_product_page_payment_details',
    array (
        'label' => __('Payment Details'),
        'description' => __('Extra payments information'),
        'section' => 'woocommerce_product_page',
        'input_attrs' => array (
            'class' => 'qtranxs-translatable',
            'toolbar1' => 'bold italic bullist numlist alignleft aligncenter alignright link',
            'toolbar2' => 'formatselect',
            'mediaButtons' => true,
        )
    )
));

/**
 * Summary position
 */
$wp_customize->add_setting('woocommerce_product_page_excerpt_position',
    array (
        'default' => 'position-1',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_product_page_excerpt_position',
    array (
        'label' => __('Excerpt Position', 'growtype-wc'),
        'description' => esc_html__('Choose product summary/excerpt position', 'growtype-wc'),
        'section' => 'woocommerce_product_page',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => array (
            'position-1' => __('Above "Add to cart"', 'growtype-wc'),
            'position-2' => __('Below "Add to cart"', 'growtype-wc')
        )
    )
));

/**
 *
 */
$wp_customize->add_setting('woocommerce_product_page_short_description_section_title',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_short_description_section_title',
    array (
        'label' => esc_html__('Short Description Title'),
        'section' => 'woocommerce_product_page',
        'description' => __('Enable/disable short description section title.', 'growtype-wc'),
    )
));

/**
 *
 */
$wp_customize->add_setting('woocommerce_product_page_long_description_section_title',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_long_description_section_title',
    array (
        'label' => esc_html__('Long Description Title'),
        'section' => 'woocommerce_product_page',
        'description' => __('Enable/disable long description section title.', 'growtype-wc'),
    )
));

/**
 * Meta data
 */
$wp_customize->add_setting('woocommerce_product_page_meta_data_enabled',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_meta_data_enabled',
    array (
        'label' => esc_html__('Meta Data Enabled'),
        'section' => 'woocommerce_product_page',
        'description' => __('Enable/disable meta data.', 'growtype-wc'),
    )
));

/**
 * Sale flash
 */
$wp_customize->add_setting('woocommerce_product_page_sale_flash',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_sale_flash',
    array (
        'label' => esc_html__('Sale flash'),
        'section' => 'woocommerce_product_page',
        'description' => __('Enable/disable sale flash (badge).', 'growtype-wc'),
    )
));

/**
 * Quantity
 */
$wp_customize->add_setting('woocommerce_product_page_quantity_selector',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_quantity_selector',
    array (
        'label' => esc_html__('Quantity selector'),
        'section' => 'woocommerce_product_page',
        'description' => __('Enable/disable quantity selector.', 'growtype-wc'),
    )
));

/**
 * Related products
 */
$wp_customize->add_setting('woocommerce_product_page_related_products_notice',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'woocommerce_product_page_related_products_notice',
    array (
        'label' => __('Related Products'),
        'description' => __('Below you can change related products settings'),
        'section' => 'woocommerce_product_page'
    )
));

/**
 * Breadcrumb status
 */
$wp_customize->add_setting('woocommerce_product_page_related_products',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_related_products',
    array (
        'label' => esc_html__('Enabled'),
        'section' => 'woocommerce_product_page',
        'description' => __('Related products are enabled.', 'growtype-wc'),
    )
));

/**
 * Products preview style
 */
$wp_customize->add_setting('woocommerce_product_page_related_products_preview_style',
    array (
        'default' => 'grid',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_product_page_related_products_preview_style',
    array (
        'label' => __('Products preview style', 'growtype-wc'),
        'description' => esc_html__('Choose how products should be displayed', 'growtype-wc'),
        'section' => 'woocommerce_product_page',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => $this->product_preview_styles
    )
));

/**
 * Related products amount
 */
$wp_customize->add_setting('woocommerce_product_page_related_products_amount', array (
    'default' => '4',
));

$wp_customize->add_control('woocommerce_product_page_related_products_amount', array (
    'type' => 'text',
    'section' => 'woocommerce_product_page',
    'label' => __('Amount'),
    'description' => __('Related products amount')
));

/**
 * Sidebar
 */
$wp_customize->add_setting('woocommerce_product_page_sidebar_notice',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'woocommerce_product_page_sidebar_notice',
    array (
        'label' => __('Sidebar'),
        'description' => __('Below you can change sidebar settings'),
        'section' => 'woocommerce_product_page'
    )
));

/**
 * Sidebar enabled
 */
$wp_customize->add_setting('woocommerce_product_page_sidebar_enabled',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_product_page_sidebar_enabled',
    array (
        'label' => esc_html__('Sidebar Enabled'),
        'section' => 'woocommerce_product_page',
        'description' => __('Sidebar is enabled', 'growtype-wc'),
    )
));

/**
 * Product summary in sidebar
 */
$wp_customize->add_setting('woocommerce_product_page_sidebar_content',
    array (
        'default' => '',
        'transport' => 'postMessage',
        'sanitize_callback' => array ($this, 'woocommerce_product_page_sidebar_content_translation')
    )
);

$wp_customize->add_control(new Skyrocket_TinyMCE_Custom_control($wp_customize, 'woocommerce_product_page_sidebar_content',
    array (
        'label' => __('Sidebar Content'),
        'description' => __('Content for product sidebar.'),
        'section' => 'woocommerce_product_page',
        'priority' => 10,
        'input_attrs' => array (
            'class' => 'qtranxs-translatable',
            'toolbar1' => 'bold italic bullist numlist alignleft aligncenter alignright link',
            'toolbar2' => 'formatselect',
            'mediaButtons' => true,
        )
    )
));
