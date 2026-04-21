<?php

class Growtype_Wc_Admin_Orders
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
        require GROWTYPE_WC_PATH . '/admin/pages/orders/partials/growtype-wc-admin-orders-columns.php';
        new Growtype_Wc_Admin_Orders_Columns();
        
        /**
         * Verification
         */
        require GROWTYPE_WC_PATH . '/admin/pages/orders/partials/growtype-wc-admin-orders-verification.php';
        new Growtype_Wc_Admin_Orders_Verification();
    }
}
