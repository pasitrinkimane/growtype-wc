<?php

/**
 *
 */
add_action('woocommerce_account_subscriptions_endpoint', 'woocommerce_account_subscriptions_endpoint_extend');
function woocommerce_account_subscriptions_endpoint_extend()
{
    $products = Growtype_Wc_Product::get_user_subscriptions();

    echo growtype_wc_include_view('woocommerce.myaccount.subscriptions', ['products' => $products]);
}
