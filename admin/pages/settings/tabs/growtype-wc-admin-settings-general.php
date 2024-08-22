<?php

class Growtype_Wc_Admin_Settings_General
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_wc_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['general'] = 'General';

        return $tabs;
    }

    function admin_settings()
    {
        /**
         * Optimization enabled
         */
        register_setting(
            'growtype_wc_settings_general', // settings group name
            'woocommerce_optimization_is_enabled', // option name
        );

        add_settings_field(
            'woocommerce_optimization_is_enabled',
            'WC Plugin Optimization Enabled',
            function () {
                $html = '<input type="checkbox" name="woocommerce_optimization_is_enabled" value="1" ' . checked(1, get_option('woocommerce_optimization_is_enabled'), false) . ' />';
                echo $html;
            },
            'growtype-wc-settings',
            'growtype_wc_settings_general_render'
        );

        /**
         * Woocommerce main menu title
         */
        register_setting(
            'growtype_wc_settings_general', // settings group name
            'woocommerce_main_menu_title', // option name
//            'sanitize_text_field' // sanitization function
        );

        add_settings_field(
            'woocommerce_main_menu_title',
            'Woocommerce menu title',
            array ($this, 'woocommerce_main_menu_title_callback'),
            'growtype-wc-settings',
            'growtype_wc_settings_general_render'
        );

        /**
         * Woocommerce products menu title
         */
        register_setting(
            'growtype_wc_settings_general', // settings group name
            'woocommerce_products_menu_title', // option name
            'sanitize_text_field' // sanitization function
        );

        add_settings_field(
            'woocommerce_products_menu_title',
            'Products menu title',
            array ($this, 'woocommerce_products_menu_title_callback'),
            'growtype-wc-settings',
            'growtype_wc_settings_general_render'
        );
    }

    /**
     * Woocommerce mian menu title
     */
    function woocommerce_main_menu_title_callback()
    {
        $html = '<input type="text" name="woocommerce_main_menu_title" style="min-width:400px;" value="' . get_option('woocommerce_main_menu_title') . '" />';
        echo $html;
    }

    /**
     * Woocommerce products menu title
     */
    function woocommerce_products_menu_title_callback()
    {
        $html = '<input type="text" name="woocommerce_products_menu_title" style="min-width:400px;" value="' . get_option('woocommerce_products_menu_title') . '" />';
        echo $html;
    }
}
