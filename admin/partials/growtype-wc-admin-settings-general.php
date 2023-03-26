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
         * Woocommerce main menu title
         */
        register_setting(
            'growtype_wc_settings', // settings group name
            'woocommerce_main_menu_title', // option name
//            'sanitize_text_field' // sanitization function
        );

        add_settings_field(
            'woocommerce_main_menu_title',
            'Woocommerce menu title',
            array ($this, 'woocommerce_main_menu_title_callback'),
            'growtype-wc-settings',
            'growtype_wc_settings_general'
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
            'growtype_wc_settings_general'
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
