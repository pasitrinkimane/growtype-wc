<?php

add_filter('woocommerce_email_settings', 'growtype_wc_woocommerce_email_settings');
function growtype_wc_woocommerce_email_settings($settings)
{
    $settings[] = array (
        'title' => __('Growtype WC Email Settings', 'growtype-wc'),
        'type' => 'title',
        'id' => 'growtype_wc_email_settings_section_title'
    );

    $settings[] = array (
        'title' => __('Email Logging', 'growtype-wc'),
        'desc' => __('Print emails information to log file', 'growtype-wc'),
        'id' => 'growtype_wc_enabled_email_logs',
        'type' => 'checkbox',
        'default' => 'no',
    );

    $settings[] = array (
        'title' => __('Disable Payment Method', 'growtype-wc'),
        'desc' => __('Remove payment method line from final order emails', 'growtype-wc'),
        'id' => 'growtype_wc_disable_payment_method_in_emails',
        'type' => 'checkbox',
        'default' => 'no',
    );

    $settings[] = array (
        'type' => 'sectionend',
        'id' => 'growtype_wc_email_settings_section_end'
    );

    return $settings;
}
