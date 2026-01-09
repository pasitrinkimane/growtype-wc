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
        require_once 'settings/growtype-wc-admin-settings.php';
        new Growtype_Wc_Admin_Settings();

        /**
         * Appearance
         */
        require_once 'appearance/growtype-wc-admin-appearance.php';
        new Growtype_Wc_Admin_Appearance();

        /**
         * Orders
         */
        require_once 'orders/growtype-wc-admin-orders.php';
        new Growtype_Wc_Admin_Orders();
    }
}
