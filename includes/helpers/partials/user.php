<?php

/**
 * @return void
 */
function growtype_wc_get_user_subscriptions($user_id = null): array
{
    $user_id = !empty($user_id) ? $user_id : get_current_user_id();
    $subscriptions = growtype_wc_get_subscriptions();

    $valid_subscriptions = [];
    foreach ($subscriptions as $subscription) {
        $sub_user_id = get_post_meta($subscription->ID, '_user_id', true);

        if ((int)$user_id === (int)$sub_user_id) {
            array_push($valid_subscriptions, $subscription);
        }
    }

    return $valid_subscriptions;
}

function growtype_wc_user_has_active_subscription($user_id = null)
{
    $subscriptions = growtype_wc_get_user_subscriptions($user_id);

    foreach ($subscriptions as $subscription) {
        $status = get_post_meta($subscription->ID, '_status', true);

        if ($status === Growtype_Wc_Subscription::STATUS_ACTIVE) {
            return true;
        }
    }

    return false;
}
