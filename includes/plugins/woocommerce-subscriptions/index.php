<?php

class Growtype_Wc_WC_Subscriptions
{
    public function __construct()
    {
        add_action('plugins_loaded', array ($this, 'loading'), 100);
    }

    function loading()
    {
        /**
         * Functions
         */
        include_once(GROWTYPE_WC_PATH . 'includes/plugins/woocommerce-subscriptions/functions/index.php');

        /**
         * Classes
         */
        include_once(GROWTYPE_WC_PATH . 'includes/plugins/woocommerce-subscriptions/classes/index.php');
    }
}
