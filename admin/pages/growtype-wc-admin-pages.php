<?php

class Growtype_Wc_Admin_Pages
{
    public function __construct()
    {
        $this->load_pages();
    }

    public function load_pages()
    {
        /**
         * Settings
         */
        require GROWTYPE_WC_PATH . '/admin/pages/settings/growtype-wc-admin-settings.php';
        new Growtype_Wc_Admin_Settings();

        /**
         * Appearance
         */
        require GROWTYPE_WC_PATH . '/admin/pages/appearance/growtype-wc-admin-appearance.php';
        new Growtype_Wc_Admin_Appearance();

        /**
         * Orders
         */
        require GROWTYPE_WC_PATH . '/admin/pages/orders/growtype-wc-admin-orders.php';
        new Growtype_Wc_Admin_Orders();

        /**
         * WP Users tweaks
         */
        require GROWTYPE_WC_PATH . '/admin/pages/users/growtype-wc-admin-users.php';
        new Growtype_Wc_Admin_Users();
    }
}