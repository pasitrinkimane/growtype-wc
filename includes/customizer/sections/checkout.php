<?php

/**
 * Checkout style
 */
$wp_customize->add_setting('woocommerce_checkout_style_select',
    array (
        'default' => 'default',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_checkout_style_select',
    array (
        'label' => __('Checkout style', 'growtype-wc'),
        'section' => 'woocommerce_checkout',
        'priority' => 9,
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => array (
            'default' => __('Default', 'growtype-wc'),
            'vertical' => __('Vertical', 'growtype-wc'),
            'steps' => __('Steps', 'growtype-wc'),
        )
    )
));

/**
 * Input label style
 */
$wp_customize->add_setting('woocommerce_checkout_input_label_style',
    array (
        'default' => 'default',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_checkout_input_label_style',
    array (
        'label' => __('Input label style', 'growtype-wc'),
        'section' => 'woocommerce_checkout',
        'priority' => 9,
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => array (
            'default' => __('Default', 'growtype-wc'),
            'floating' => __('Floating', 'growtype-wc')
        )
    )
));

/**
 * Show 'optional' input labels
 */
$wp_customize->add_setting('woocommerce_checkout_optional_label', array (
    'default' => false
));

$wp_customize->add_control(
    new WP_Customize_Control(
        $wp_customize,
        'woocommerce_checkout_optional_label',
        array (
            'label' => __('Highlight optional fields with label', 'growtype-wc'),
            'section' => 'woocommerce_checkout',
            'settings' => 'woocommerce_checkout_optional_label',
            'type' => 'checkbox',
            'priority' => 9,
        )
    )
);

/**
 * Terms checked by default
 */
$wp_customize->add_setting('woocommerce_checkout_terms_is_checked_by_default',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_checkout_terms_is_checked_by_default',
    array (
        'label' => esc_html__('Terms checked'),
        'section' => 'woocommerce_checkout',
        'description' => __('Terms checked by default.', 'growtype-wc'),
    )
));

/**
 * Billing fields
 */
$wp_customize->add_setting('woocommerce_checkout_billing_fields',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_checkout_billing_fields',
    array (
        'label' => esc_html__('Billing fields'),
        'section' => 'woocommerce_checkout',
        'description' => __('Enabled/disable billing fields', 'growtype-wc'),
    )
));

/**
 * Order notes
 */
$wp_customize->add_setting('woocommerce_checkout_order_notes',
    array (
        'default' => 'optional',
        'transport' => 'refresh'
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_checkout_order_notes',
    array (
        'label' => __('Order notes', 'growtype-wc'),
        'section' => 'woocommerce_checkout',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => [
            'hidden' => 'Hidden',
            'optional' => 'Optional',
            'required' => 'Required'
        ],
        'priority' => 10
    )
));

/**
 * Email
 */
$wp_customize->add_setting('woocommerce_checkout_billing_email',
    array (
        'default' => 'required',
        'transport' => 'refresh'
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_checkout_billing_email',
    array (
        'label' => __('Email', 'growtype-wc'),
        'section' => 'woocommerce_checkout',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => [
            'hidden' => 'Hidden',
            'optional' => 'Optional',
            'required' => 'Required'
        ],
        'priority' => 10
    )
));

/**
 * Country
 */
$wp_customize->add_setting('woocommerce_checkout_billing_country',
    array (
        'default' => 'required',
        'transport' => 'refresh'
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_checkout_billing_country',
    array (
        'label' => __('Country', 'growtype-wc'),
        'section' => 'woocommerce_checkout',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => [
            'hidden' => 'Hidden',
            'optional' => 'Optional',
            'required' => 'Required'
        ],
        'priority' => 10
    )
));

/**
 * Address
 */
$wp_customize->add_setting('woocommerce_checkout_billing_address_1',
    array (
        'default' => 'required',
        'transport' => 'refresh'
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_checkout_billing_address_1',
    array (
        'label' => __('Address 1', 'growtype-wc'),
        'section' => 'woocommerce_checkout',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => [
            'hidden' => 'Hidden',
            'optional' => 'Optional',
            'required' => 'Required'
        ],
        'priority' => 10
    )
));

/**
 * Postcode
 */
$wp_customize->add_setting('woocommerce_checkout_billing_postcode',
    array (
        'default' => 'required',
        'transport' => 'refresh'
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_checkout_billing_postcode',
    array (
        'label' => __('Postcode', 'growtype-wc'),
        'section' => 'woocommerce_checkout',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => [
            'hidden' => 'Hidden',
            'optional' => 'Optional',
            'required' => 'Required'
        ],
        'priority' => 10
    )
));

/**
 * State
 */
$wp_customize->add_setting('woocommerce_checkout_billing_state',
    array (
        'default' => 'required',
        'transport' => 'refresh'
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_checkout_billing_state',
    array (
        'label' => __('State', 'growtype-wc'),
        'section' => 'woocommerce_checkout',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => [
            'hidden' => 'Hidden',
            'optional' => 'Optional',
            'required' => 'Required'
        ],
        'priority' => 10
    )
));

/**
 * billing_city
 */
$wp_customize->add_setting('woocommerce_checkout_billing_city',
    array (
        'default' => 'required',
        'transport' => 'refresh'
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_checkout_billing_city',
    array (
        'label' => __('City', 'growtype-wc'),
        'section' => 'woocommerce_checkout',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => [
            'hidden' => 'Hidden',
            'optional' => 'Optional',
            'required' => 'Required'
        ],
        'priority' => 10
    )
));

/**
 * Intro
 */
$wp_customize->add_setting('woocommerce_checkout_order_review_table_notice',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'woocommerce_checkout_order_review_table_notice',
    array (
        'label' => __('Order review table'),
        'description' => __('Below you can change order review table'),
        'section' => 'woocommerce_checkout'
    )
));

/**
 * Order review
 */
$wp_customize->add_setting('woocommerce_checkout_order_review_table',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_checkout_order_review_table',
    array (
        'label' => esc_html__('Enabled'),
        'section' => 'woocommerce_checkout',
        'description' => __('Enabled/disable order review table', 'growtype-wc'),
    )
));

/**
 * Order review heading
 */
$wp_customize->add_setting('woocommerce_checkout_order_review_heading',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_checkout_order_review_heading',
    array (
        'label' => esc_html__('Heading'),
        'section' => 'woocommerce_checkout',
        'description' => __('Enabled/disable order review heading', 'growtype-wc'),
    )
));

/**
 * Show table head
 */
$wp_customize->add_setting('woocommerce_checkout_order_review_table_show_head',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_checkout_order_review_table_show_head',
    array (
        'label' => esc_html__('Table head'),
        'section' => 'woocommerce_checkout',
        'description' => __('Show table head', 'growtype-wc'),
    )
));

/**
 * Show subtotal
 */
$wp_customize->add_setting('woocommerce_checkout_order_review_table_show_subtotal',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_checkout_order_review_table_show_subtotal',
    array (
        'label' => esc_html__('Subtotal'),
        'section' => 'woocommerce_checkout',
        'description' => __('Show subtotal price', 'growtype-wc'),
    )
));

/**
 * Order review background
 */
$wp_customize->add_setting('woocommerce_checkout_order_review_background',
    array (
        'default' => true,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_checkout_order_review_background',
    array (
        'label' => esc_html__('Payments background'),
        'section' => 'woocommerce_checkout',
        'description' => __('Enabled/disable payment methods background color', 'growtype-wc'),
    )
));

/**
 * Orde review Cart item style
 */
$wp_customize->add_setting('woocommerce_checkout_order_review_cart_item_style',
    array (
        'default' => 'default',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'woocommerce_checkout_order_review_cart_item_style',
    array (
        'label' => __('Cart item style', 'growtype-wc'),
        'section' => 'woocommerce_checkout',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => array (
            'default' => __('Default', 'growtype-wc'),
            'detailed' => __('Detailed', 'growtype-wc')
        )
    )
));

/**
 * Intro
 */
$wp_customize->add_setting('woocommerce_checkout_section_titles_notice',
    array (
        'default' => '',
        'transport' => 'postMessage'
    )
);

$wp_customize->add_control(new Skyrocket_Simple_Notice_Custom_control($wp_customize, 'woocommerce_checkout_section_titles_notice',
    array (
        'label' => __('Texts'),
        'description' => __('Below you can change main texts'),
        'section' => 'woocommerce_checkout'
    )
));

/**
 * Checkout intro
 */
$wp_customize->add_setting('woocommerce_checkout_intro_text',
    array (
        'default' => '',
        'transport' => 'postMessage',
        'sanitize_callback' => array ($this, 'woocommerce_checkout_intro_text_translation')
    )
);

$wp_customize->add_control(new Skyrocket_TinyMCE_Custom_control($wp_customize, 'woocommerce_checkout_intro_text',
    array (
        'label' => __('Intro Content'),
        'description' => __('Intro details.'),
        'section' => 'woocommerce_checkout',
        'priority' => 10,
        'input_attrs' => array (
            'class' => 'qtranxs-translatable',
            'toolbar1' => 'bold italic bullist numlist alignleft aligncenter alignright link',
            'toolbar2' => 'formatselect',
            'mediaButtons' => true,
        )
    )
));

/**
 * Billing section title
 */
$wp_customize->add_setting('woocommerce_checkout_billing_section_title', array (
    'capability' => 'edit_theme_options',
    'default' => 'Billing details',
    'sanitize_callback' => array ($this, 'woocommerce_checkout_billing_section_title_translation')
));

$wp_customize->add_control('woocommerce_checkout_billing_section_title', array (
    'type' => 'text',
    'section' => 'woocommerce_checkout', // Add a default or your own section
    'label' => __('"Billing Details" Section Title'),
    'description' => __('Default: Billing details')
));

/**
 * Additional section title
 */
$wp_customize->add_setting('woocommerce_checkout_additional_section_title', array (
    'capability' => 'edit_theme_options',
    'default' => 'Additional details',
    'sanitize_callback' => array ($this, 'woocommerce_checkout_additional_section_title_translation')
));

$wp_customize->add_control('woocommerce_checkout_additional_section_title', array (
    'type' => 'text',
    'section' => 'woocommerce_checkout', // Add a default or your own section
    'label' => __('"Additional Details" Section Title'),
    'description' => __('Default: Additional details')
));

/**
 * Account section title
 */
$wp_customize->add_setting('woocommerce_checkout_account_section_title', array (
    'capability' => 'edit_theme_options',
    'default' => 'Account details',
    'sanitize_callback' => array ($this, 'woocommerce_checkout_account_section_title_translation')
));

$wp_customize->add_control('woocommerce_checkout_account_section_title', array (
    'type' => 'text',
    'section' => 'woocommerce_checkout', // Add a default or your own section
    'label' => __('"Account details" Section Title'),
    'description' => __('Default: Additional details')
));

/**
 * Place order button title
 */
$wp_customize->add_setting('woocommerce_checkout_place_order_button_title', array (
    'capability' => 'edit_theme_options',
    'default' => __('Place order', 'growtype-wc'),
    'sanitize_callback' => array ($this, 'woocommerce_checkout_place_order_button_title_translation')
));

$wp_customize->add_control('woocommerce_checkout_place_order_button_title', array (
    'type' => 'text',
    'section' => 'woocommerce_checkout', // Add a default or your own section
    'label' => __('"Place order" Button Title'),
    'description' => __('Default: Place order')
));

/**
 * Create account
 */
$wp_customize->add_setting('woocommerce_checkout_create_account_checked',
    array (
        'default' => false,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'woocommerce_checkout_create_account_checked',
    array (
        'label' => esc_html__('Create Account Checked'),
        'section' => 'woocommerce_checkout',
        'description' => __('Create account checkbox checked by default.', 'growtype-wc'),
    )
));
