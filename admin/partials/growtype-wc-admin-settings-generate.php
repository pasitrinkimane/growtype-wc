<?php

class Growtype_Wc_Admin_Settings_Generate
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_wc_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['generate'] = 'Generate Products';

        return $tabs;
    }

    function admin_settings()
    {

    }
}
