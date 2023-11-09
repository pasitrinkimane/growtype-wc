<?php

class WC_Subscriptions_Cart
{
    public static $version = '5.5.0'; // WRCS: DEFINED_VERSION.

    public static function cart_contains_subscription()
    {
        if (!empty(WC()->cart->cart_contents)) {
            foreach (WC()->cart->cart_contents as $cart_item) {
                $is_subscription = growtype_wc_product_is_subscription($cart_item['product_id']);
                if ($is_subscription) {
                    return true;
                }
            }
        }

        return false;
    }
}
