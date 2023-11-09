<?php

class Growtype_Wc_Plugin_Woocommerce_Subscriptions_Order
{
    public function __construct()
    {
//        add_action('woocommerce_order_status_changed', array ($this, 'change_order_status_conditionally'), 0, 4);
//        add_filter('woocommerce_order_item_needs_processing', [$this, 'woocommerce_order_item_needs_processing'], 0, 3);
    }

    function woocommerce_order_item_needs_processing($virtual_downloadable, $product, $order_id)
    {
        if (growtype_wc_product_is_subscription($product->get_id())) {
            $virtual_downloadable = true;
        }

        return $virtual_downloadable;
    }

    function change_order_status_conditionally($order_id, $status_from, $status_to, $order)
    {
        if ($order->get_payment_method() === 'stripe' && $status_from === 'pending' && $status_to === 'processing') {
            $order->update_status('on-hold');
        }
    }
}
