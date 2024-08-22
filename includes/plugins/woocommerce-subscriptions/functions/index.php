<?php

/**
 *
 */
if (!function_exists('wcs_order_contains_subscription')) {
    function wcs_order_contains_subscription($order_id)
    {
        $subscription_exists = growtype_wc_order_contains_subscription_order($order_id);

        error_log(sprintf('Subscription exists: %s', $subscription_exists));

        return $subscription_exists;
    }
}

/**
 *
 */
if (!function_exists('wcs_get_subscriptions_for_renewal_order')) {
    function wcs_get_subscriptions_for_renewal_order($order_id)
    {
        error_log('wcs_get_subscriptions_for_renewal_order');

        $order_id = is_object($order_id) ? $order_id->get_id() : $order_id;

        return [
            growtype_wc_order_get_subscription_order($order_id)
        ];
    }
}

/**
 *
 */
if (!function_exists('wcs_get_subscriptions_for_order')) {
    function wcs_get_subscriptions_for_order($order_id)
    {
        error_log('wcs_get_subscriptions_for_order');
        return growtype_wc_get_order_active_subscriptions($order_id);
    }
}

/**
 *
 */
if (!function_exists('wcs_user_has_subscription')) {
    function wcs_user_has_subscription($user_id = 0, $product_id = '', $status = 'any')
    {
        $has_subscription = !empty(growtype_wc_user_has_active_subscription($user_id)) ? true : false;

        error_log(sprintf('wcs_user_has_subscription: %s', $has_subscription ? 'true' : 'false'));
        return $has_subscription;
    }
}

/**
 *
 */
if (!function_exists('wcs_is_subscription')) {
    function wcs_is_subscription($subscription)
    {
        if (!empty($subscription)) {
            error_log(sprintf('wcs_is_subscription: %s', $subscription));

            return growtype_wc_order_is_subscription_order($subscription->get_id());
        }

        return false;
    }
}

if (!function_exists('wcs_order_contains_renewal')) {
    function wcs_order_contains_renewal($order)
    {
        error_log('wcs_order_contains_renewal');

        if (!is_a($order, 'WC_Abstract_Order')) {
            $order = wc_get_order($order);
        }

        $related_subscriptions = growtype_wc_order_get_subscription_order($order->get_id());

        if (wcs_is_order($order) && !empty($related_subscriptions)) {
            $is_renewal = true;
        } else {
            $is_renewal = false;
        }

        return apply_filters('growtype_wc_is_renewal_order', $is_renewal, $order);
    }
}

if (!function_exists('wcs_is_order')) {
    function wcs_is_order($order)
    {
        error_log('wcs_is_order');

        if (is_callable(array ($order, 'get_type'))) {
            $is_order = ('shop_order' === $order->get_type());
        } else {
            $is_order = (isset($order->order_type) && 'simple' === $order->order_type);
        }

        return $is_order;
    }
}

if (!function_exists('wcs_get_subscription')) {
    function wcs_get_subscription($the_subscription)
    {
        if (!empty($the_subscription)) {
            error_log(sprintf('growtype. wcs_get_subscription: %s', $the_subscription));

            $subscription = wcs_order_contains_subscription($the_subscription);

            if ($subscription) {
                $order = wc_get_order($the_subscription);
                $subscription = growtype_wc_order_get_subscription_order($order->get_id());
            }

            return $subscription;
        }
    }
}
