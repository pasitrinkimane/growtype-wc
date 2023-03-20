<?php

/**
 * Allow access if user has these products
 */
$wp_customize->add_setting('theme_access_user_must_have_products_list',
    array (
        'default' => '',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'theme_access_user_must_have_products_list',
    array (
        'label' => __('Must have products', 'growtype-wc'),
        'description' => esc_html__('User must order specific products to proceed. ', 'growtype-wc'),
        'section' => 'theme-access',
        'input_attrs' => array (
            'placeholder' => __('Please select products...', 'growtype-wc'),
            'multiselect' => true,
        ),
        'choices' => $this->available_products
    )
));
