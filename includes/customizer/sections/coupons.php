<?php

$wp_customize->add_section(
    'woocommerce_coupons_page',
    array (
        'title' => __('Coupons', 'growtype-wc'),
        'priority' => 25,
        'panel' => 'woocommerce',
    )
);

/**
 * Featured Coupon
 */
$wp_customize->add_setting('shop_featured_coupon_select',
    array (
        'default' => '',
        'transport' => 'refresh',
    )
);

$wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'shop_featured_coupon_select',
    array (
        'label' => __('Featured coupon', 'growtype-wc'),
        'description' => esc_html__('Choose featured coupon', 'growtype-wc'),
        'section' => 'woocommerce_coupons_page',
        'input_attrs' => array (
            'multiselect' => false,
        ),
        'choices' => $this->available_wc_coupons
    )
));
