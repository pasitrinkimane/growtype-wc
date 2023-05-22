<?php

class Growtype_Wc_Admin_Appearance
{
    public function __construct()
    {
        /**
         * General
         */
        include_once GROWTYPE_WC_PATH . 'admin/pages/appearance/menus/growtype-wc-admin-appearance-menus.php';
        new Growtype_Wc_Admin_Appearance_Menus();
    }
}
