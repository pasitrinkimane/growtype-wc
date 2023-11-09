<?php

/**
 *
 */
add_action('woocommerce_account_subscriptions_endpoint', 'woocommerce_account_subscriptions_endpoint_extend');
function woocommerce_account_subscriptions_endpoint_extend()
{
    if (isset($_POST['subscription_id']) && isset($_POST['change_subscription_status'])) {
        Growtype_Wc_Subscription::change_status($_POST['subscription_id'], $_POST['change_subscription_status']);
    }

    $subscriptions = growtype_wc_get_user_subscriptions();

    echo growtype_wc_include_view('woocommerce.myaccount.subscriptions', ['subscriptions' => $subscriptions]);
}
