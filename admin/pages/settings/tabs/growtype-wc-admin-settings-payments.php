<?php

class Growtype_Wc_Admin_Settings_Payments
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_wc_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['payments'] = 'Payments';

        return $tabs;
    }

    function admin_settings()
    {
        /**
         * Disable all payment methods
         */
        register_setting(
            'growtype_wc_settings_payments', // settings group name
            'growtype_wc_disable_all_payment_methods', // option name
        );

        add_settings_field(
            'growtype_wc_disable_all_payment_methods',
            'Disable All Payment Methods',
            function () {
                $html = '<input type="checkbox" name="growtype_wc_disable_all_payment_methods" value="1" ' . checked(1, get_option('growtype_wc_disable_all_payment_methods'), false) . ' />';
                echo $html;
            },
            'growtype-wc-settings',
            'growtype_wc_settings_payments_render'
        );

        /**
         * Notice
         */
        register_setting(
            'growtype_wc_settings_payments', // settings group name
            'growtype_wc_disabled_payment_methods_notice', // option name
        );

        add_settings_field(
            'growtype_wc_disabled_payment_methods_notice',
            'Disabled Payment Methods Notice',
            function () {
                echo '<textarea name="growtype_wc_disabled_payment_methods_notice" rows="5" cols="60" class="large-text">' . Growtype_Wc_Payment::disabled_payment_methods_notice() . '</textarea>';
            },
            'growtype-wc-settings',
            'growtype_wc_settings_payments_render'
        );
    }
}
