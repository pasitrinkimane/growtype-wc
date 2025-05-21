<?php

/**
 * @return void
 */
function growtype_wc_get_user_subscriptions($user_id = null): array
{
    $user_id = !empty($user_id) ? $user_id : get_current_user_id();

    return growtype_wc_get_subscriptions([
        'user_id' => $user_id
    ]);
}

function growtype_wc_user_has_active_subscription($user_id = null)
{
    $user_id = !empty($user_id) ? $user_id : get_current_user_id();

    if (empty($user_id)) {
        return false;
    }

    $subscriptions = growtype_wc_get_subscriptions([
        'status' => 'active',
        'user_id' => $user_id
    ]);

    return !empty($subscriptions);
}
