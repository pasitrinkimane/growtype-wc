<?php

class Growtype_Wc_Admin_Settings_Plugins
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_wc_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['plugins'] = 'Plugins';

        return $tabs;
    }

    function admin_settings()
    {
        /**
         * Woocommerce main menu title
         */
        register_setting(
            'growtype_wc_settings_plugins', // settings group name
            'woocommerce_plugins_printful_enabled'
        );

        add_settings_field(
            'woocommerce_plugins_printful_enabled',
            'Printful enabled',
            array ($this, 'woocommerce_plugins_printful_enabled_callback'),
            'growtype-wc-settings',
            'growtype_wc_settings_plugins_render'
        );

        /**
         * Woocommerce main menu title
         */
        register_setting(
            'growtype_wc_settings_plugins', // settings group name
            'woocommerce_plugins_printful_token'
        );

        add_settings_field(
            'woocommerce_plugins_printful_token',
            'Printful token',
            array ($this, 'woocommerce_plugins_printful_token_callback'),
            'growtype-wc-settings',
            'growtype_wc_settings_plugins_render'
        );
    }

    /**
     * Woocommerce mian menu title
     */
    function woocommerce_plugins_printful_enabled_callback()
    {
        echo '<input type="checkbox" name="woocommerce_plugins_printful_enabled" value="1" ' . checked(1, get_option('woocommerce_plugins_printful_enabled'), false) . ' />';
    }

    /**
     * Woocommerce mian menu title
     */
    function woocommerce_plugins_printful_token_callback()
    {
        echo '<input type="text" name="woocommerce_plugins_printful_token" value="' . get_option('woocommerce_plugins_printful_token') . '"/>';
    }
}
