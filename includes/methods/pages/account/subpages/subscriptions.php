<?php

/**
 *
 */

add_action('woocommerce_account_subscriptions_endpoint', 'woocommerce_account_subscriptions_endpoint_extend');
function woocommerce_account_subscriptions_endpoint_extend()
{
    if (!is_user_logged_in()) {
        return;
    }

    if (isset($_POST['subscription_id']) && !empty($_POST['subscription_id']) && isset($_POST['change_subscription_status']) && !empty($_POST['change_subscription_status'])) {
        $subscriptions = growtype_wc_get_user_subscriptions(get_current_user_id());

        foreach ($subscriptions as $subscription) {
            if ((int)$subscription->ID == (int)$_POST['subscription_id']) {
                Growtype_Wc_Subscription::change_status($_POST['subscription_id'], $_POST['change_subscription_status']);
                do_action('growtype_wc_change_subscription_status', $_POST['subscription_id'], $_POST['change_subscription_status']);
                break;
            }
        }
    }

    /**
     * Manage subscription
     */
    if (isset($_GET['action']) && $_GET['action'] === 'manage') {
        $manage_subscription_externally = true;

        if (isset($_GET['subscription']) && !empty($_GET['subscription'])) {
            $user_subscriptions = growtype_wc_get_user_subscriptions();

            $subscription = null;
            foreach ($user_subscriptions as $user_subscription) {
                if ((int)$user_subscription->ID === (int)$_GET['subscription']) {
                    $subscription = $user_subscription;
                    break;
                }
            }

            if (!empty($subscription)) {
                $order_id = get_post_meta($subscription->ID, '_order_id', true);

                if (!empty($order_id)) {
                    $stripe_subscription_id = get_post_meta($order_id, 'stripe_subscription_id', true);

                    if (!empty($stripe_subscription_id)) {
                        $growtype_wc_payment_gateway_stripe = new Growtype_Wc_Payment_Gateway_Stripe();
                        $stripe_subscription_details = $growtype_wc_payment_gateway_stripe->subscription_details($stripe_subscription_id, $subscription->ID);

                        if (!empty($stripe_subscription_details)) {
                            if (isset($_GET['status']) && $_GET['status'] === 'updated') {
                                if (isset($stripe_subscription_details['canceled_at']) && !empty($stripe_subscription_details['canceled_at'])) {
                                    if (Growtype_Wc_Subscription::status($subscription->ID) !== Growtype_Wc_Subscription::STATUS_CANCELLED) {
                                        Growtype_Wc_Subscription::change_status($subscription->ID, Growtype_Wc_Subscription::STATUS_CANCELLED);
                                    }
                                } else {
                                    if (Growtype_Wc_Subscription::status($subscription->ID) !== Growtype_Wc_Subscription::STATUS_ACTIVE) {
                                        Growtype_Wc_Subscription::change_status($subscription->ID, Growtype_Wc_Subscription::STATUS_ACTIVE);
                                    }
                                }

                                if (isset($stripe_subscription_details['renewal_date']) && !empty($stripe_subscription_details['renewal_date'])) {
                                    update_post_meta($subscription->ID, '_next_charge_date', $stripe_subscription_details['renewal_date']);
                                }
                            } else {
                                $billing_portal_url = $stripe_subscription_details['billing_portal_url'] ?? '';

                                if (!empty($billing_portal_url)) {
                                    wp_redirect($billing_portal_url);
                                    die();
                                }
                            }
                        } else {
                            wc_add_notice(sprintf('Something went wrong. Please contact us at <a href="mailto:%1$s">%1$s</a> for help.', get_option('admin_email')), 'error');
                        }
                    } else {
                        $manage_subscription_externally = false;

                        echo growtype_wc_include_view('woocommerce.myaccount.subscription-manage',
                            [
                                'subscription' => $subscription
                            ]
                        );
                    }
                }
            }
        }

        if ($manage_subscription_externally) {
            $redirect_url = add_query_arg([], growtype_wc_get_account_subpage_url('subscriptions'));

            wp_redirect($redirect_url);
            die();
        }
    } else {
        $subscriptions = growtype_wc_get_user_subscriptions();

        echo growtype_wc_include_view('woocommerce.myaccount.subscriptions',
            [
                'subscriptions' => $subscriptions
            ]
        );
    }
}
