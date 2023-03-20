<?php

class Growtype_Wc_Theme_Settings
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));
    }

    function admin_settings()
    {
        /**
         * Woocommerce settings
         */
        add_settings_section(
            'woocommerce_options_settings', // section ID
            'Woocommerce', // title (if needed)
            '', // callback function (if needed)
            'growtype-plugin-settings' // page slug
        );

        /**
         * Woocommerce main menu title
         */
        register_setting(
            'plugin_options_settings', // settings group name
            'woocommerce_main_menu_title', // option name
            'sanitize_text_field' // sanitization function
        );

        add_settings_field(
            'woocommerce_main_menu_title',
            'Woocommerce menu title',
            array ($this, 'woocommerce_main_menu_title_callback'),
            'growtype-plugin-settings',
            'woocommerce_options_settings'
        );

        /**
         * Woocommerce products menu title
         */
        register_setting(
            'plugin_options_settings', // settings group name
            'woocommerce_products_menu_title', // option name
            'sanitize_text_field' // sanitization function
        );

        add_settings_field(
            'woocommerce_products_menu_title',
            'Products menu title',
            array ($this, 'woocommerce_products_menu_title_callback'),
            'growtype-plugin-settings',
            'woocommerce_options_settings'
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
