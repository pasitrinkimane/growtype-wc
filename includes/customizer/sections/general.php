<?php

$wp_customize->add_section(
    'woocommerce_general_page',
    array (
        'title' => __('General', 'growtype-wc'),
        'priority' => 5,
        'panel' => 'woocommerce',
    )
);

/**
 * Shop type
 */
$wp_customize->add_setting('shop_selling_type_select',
    array (
        'default' => 'shop_selling_type_multiple',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'shop_selling_type_select',
    array (
        'label' => __('Selling model', 'growtype-wc'),
        'description' => esc_html__('Choose if store will be selling single or multiple products', 'growtype-wc'),
        'section' => 'woocommerce_general_page',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => array (
            'shop_selling_type_multiple' => __('Multiple products', 'growtype-wc'),
            'shop_selling_type_single' => __('Single product', 'growtype-wc'),
            'shop_selling_type_single_item' => __('Single item', 'growtype-wc')
        )
    )
));

/**
 * Allow only registered users to buy
 */
$wp_customize->add_setting('only_registered_users_can_buy',
    array (
        'default' => 0,
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'only_registered_users_can_buy',
    array (
        'label' => esc_html__('Registered Users'),
        'section' => 'woocommerce_general_page',
        'description' => __('Enable that only registered users would be able to buy products.', 'growtype-wc'),
    )
));
