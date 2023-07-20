<?php

add_filter('woocommerce_email_settings', 'growtype_wc_woocommerce_email_settings');
function growtype_wc_woocommerce_email_settings($settings)
{
    /**
     * Add extra settings
     */
    array_push($settings, array (
        'title' => __('Enable logging', 'woocommerce'),
        'desc' => __('Print emails information in log file', 'woocommerce'),
        'id' => 'growtype_wc_enabled_email_logs',
        'type' => 'checkbox',
        'checkboxgroup' => 'start',
        'default' => 'no',
        'autoload' => false,
    ));

    return $settings;
}
