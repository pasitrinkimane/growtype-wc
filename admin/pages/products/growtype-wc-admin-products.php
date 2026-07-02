<?php

class Growtype_Wc_Admin_Products
{
    public function __construct()
    {
        $this->load_methods();
    }

    private function load_methods()
    {
        /**
         * Columns
         */
        require GROWTYPE_WC_PATH . '/admin/pages/products/partials/growtype-wc-admin-products-columns.php';
        new Growtype_Wc_Admin_Products_Columns();
    }
}
