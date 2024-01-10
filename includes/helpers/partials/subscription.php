<?php

function growtype_wc_create_subscription_order_object($args = array ())
{
    $now = gmdate('Y-m-d H:i:s');
    $order = (isset($args['order_id'])) ? wc_get_order($args['order_id']) : null;

    $product = (isset($args['product_id'])) ? wc_get_product($args['product_id']) : null;

    if (empty($product) && !empty($order)) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
        }
    }

    $default_args = array (
        'status' => '',
        'order_id' => 0,
        'customer_note' => null,
        'customer_id' => null,
        'start_date' => $args['date_created'] ?? $now,
        'date_created' => $now,
        'created_via' => '',
        'currency' => get_woocommerce_currency(),
        'prices_include_tax' => get_option('woocommerce_prices_include_tax'),
        'product_id' => isset($args['product_id']) ? $args['product_id'] : $product->get_id(),
    );

    // If we are creating a subscription from an order, we use some of the order's data as defaults.
    if ($order instanceof \WC_Order) {
        $default_args['customer_id'] = $order->get_user_id();
        $default_args['created_via'] = $order->get_created_via('edit');
        $default_args['currency'] = $order->get_currency('edit');
        $default_args['prices_include_tax'] = $order->get_prices_include_tax('edit') ? 'yes' : 'no';
        $default_args['date_created'] = growtype_wc_sub_get_datetime_utc_string($order->get_date_created('edit'));
    }

    $args = wp_parse_args($args, $default_args);

    if (!isset($args['billing_period'])) {
        $args['billing_period'] = get_post_meta($product->get_id(), '_growtype_wc_subscription_period', true);
    }

    if (!isset($args['billing_interval'])) {
        $args['billing_interval'] = (int)get_post_meta($product->get_id(), '_growtype_wc_subscription_duration', true);
    }

    if (!isset($args['billing_price'])) {
        $args['billing_price'] = get_post_meta($product->get_id(), '_growtype_wc_subscription_price', true);
    }

    if (!isset($args['customer_note'])) {
        $args['customer_note'] = $order->get_customer_note();
    }

    if (!isset($args['title'])) {
        $args['title'] = $product->get_title();
    }

    /**
     * Check data
     */

    // Check that the given status exists.
    if (empty($args['status']) || !empty($args['status']) && !array_key_exists($args['status'], growtype_wc_get_subscription_statuses())) {
        return new WP_Error('woocommerce_invalid_subscription_status', __('Invalid subscription status given.', 'woocommerce-subscriptions'));
    }

    // Validate the date_created arg.
    if (!is_string($args['date_created']) || false === growtype_wc_sub_datetime_mysql_format($args['date_created'])) {
        return new WP_Error('woocommerce_subscription_invalid_date_created_format', _x('Invalid created date. The date must be a string and of the format: "Y-m-d H:i:s".', 'Error message while creating a subscription', 'woocommerce-subscriptions'));
    }
    // Check if the date is in the future.
    if (growtype_wc_sub_date_to_time($args['date_created']) > time()) {
        return new WP_Error('woocommerce_subscription_invalid_date_created', _x('Subscription created date must be before current day.', 'Error message while creating a subscription', 'woocommerce-subscriptions'));
    }

    // Validate the start_date arg.
    if (!is_string($args['start_date']) || false === growtype_wc_sub_datetime_mysql_format($args['start_date'])) {
        return new WP_Error('woocommerce_subscription_invalid_start_date_format', _x('Invalid date. The date must be a string and of the format: "Y-m-d H:i:s".', 'Error message while creating a subscription', 'woocommerce-subscriptions'));
    }

    if (get_option('woocommerce_enable_guest_checkout') === 'no') {
        if (empty($args['customer_id']) || !is_numeric($args['customer_id']) || $args['customer_id'] <= 0) {
            return new WP_Error('woocommerce_subscription_invalid_customer_id', _x('Invalid subscription customer_id.', 'Error message while creating a subscription', 'woocommerce-subscriptions'));
        }
    }

    if (empty($args['billing_period']) || !array_key_exists(strtolower($args['billing_period']), growtype_wc_sub_get_subscription_period_strings())) {
        return new WP_Error('woocommerce_subscription_invalid_billing_period', __('Invalid subscription billing period given.', 'woocommerce-subscriptions'));
    }

    if (empty($args['billing_interval']) || !is_numeric($args['billing_interval']) || absint($args['billing_interval']) <= 0) {
        return new WP_Error('woocommerce_subscription_invalid_billing_interval', __('Invalid subscription billing interval given. Must be an integer greater than 0.', 'woocommerce-subscriptions'));
    }

    $subscription = new \Growtype_Wc_Subscription();

    $subscription->set_status($args['status']);

    $subscription->set_title($args['title'] ?? sprintf('Order id: %s', $order->get_id()));
    $subscription->set_billing_price($args['billing_price']);
    $subscription->set_customer_note($args['customer_note']);
    $subscription->set_customer_id($args['customer_id']);
    $subscription->set_date_created($args['date_created']);
    $subscription->set_created_via($args['created_via']);
    $subscription->set_currency($args['currency']);
    $subscription->set_prices_include_tax('no' !== $args['prices_include_tax']);
    $subscription->set_billing_period($args['billing_period']);
    $subscription->set_billing_interval(absint($args['billing_interval']));
    $subscription->set_start_date($args['start_date']);
    $subscription->set_product_id($args['product_id']);

    if ($args['order_id'] > 0) {
        $subscription->set_parent_id($args['order_id']);
    }

    $subscription->save();

    /**
     * Filter the newly created subscription object.
     * We need to fetch the subscription from the database as the current object state doesn't match the loaded state.
     *
     * @param WC_Subscription $subscription
     * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.2.22
     */
    $subscription = apply_filters('growtype_wc_created_subscription', $subscription);

    /**
     * Triggered after a new subscription is created.
     *
     * @param WC_Subscription $subscription
     * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.2.22
     */
    do_action('growtype_wc_create_subscription_order_object', $subscription);

    return $subscription;
}

function growtype_wc_sub_date_to_time($date_string)
{

    if (0 == $date_string) {
        return 0;
    }

    $date_time = new WC_DateTime($date_string, new DateTimeZone('UTC'));

    return intval($date_time->getTimestamp());
}

function growtype_wc_sub_get_datetime_utc_string($datetime)
{
    $date = clone $datetime; // Don't change the original date object's timezone
    $date->setTimezone(new DateTimeZone('UTC'));
    return $date->format('Y-m-d H:i:s');
}

function growtype_wc_sub_datetime_mysql_format($time)
{
    if (!is_string($time)) {
        return false;
    }

    $format = 'Y-m-d H:i:s';

    $date_object = DateTime::createFromFormat($format, $time);

    // DateTime::createFromFormat will return false if it is an invalid date.
    return $date_object
        // We also need to check the output of the format() method against the provided string as it will sometimes return
        // the closest date. Passing `2022-02-29 01:02:03` will return `2022-03-01 01:02:03`
        && $date_object->format($format) === $time
        // we check the year is greater than or equal to 1900 as mysql will not accept dates before this.
        && (int)$date_object->format('Y') >= 1900;
}

function growtype_wc_sub_get_subscription_period_strings($number = 1, $period = '')
{
    $translated_periods = apply_filters('woocommerce_subscription_periods',
        array (
            // translators: placeholder is number of days. (e.g. "Bill this every day / 4 days")
            'day' => sprintf(_nx('day', '%s days', $number, 'Subscription billing period.', 'woocommerce-subscriptions'), $number), // phpcs:ignore WordPress.WP.I18n.MissingSingularPlaceholder,WordPress.WP.I18n.MismatchedPlaceholders
            // translators: placeholder is number of weeks. (e.g. "Bill this every week / 4 weeks")
            'week' => sprintf(_nx('week', '%s weeks', $number, 'Subscription billing period.', 'woocommerce-subscriptions'), $number), // phpcs:ignore WordPress.WP.I18n.MissingSingularPlaceholder,WordPress.WP.I18n.MismatchedPlaceholders
            // translators: placeholder is number of months. (e.g. "Bill this every month / 4 months")
            'month' => sprintf(_nx('month', '%s months', $number, 'Subscription billing period.', 'woocommerce-subscriptions'), $number), // phpcs:ignore WordPress.WP.I18n.MissingSingularPlaceholder,WordPress.WP.I18n.MismatchedPlaceholders
            // translators: placeholder is number of years. (e.g. "Bill this every year / 4 years")
            'year' => sprintf(_nx('year', '%s years', $number, 'Subscription billing period.', 'woocommerce-subscriptions'), $number), // phpcs:ignore WordPress.WP.I18n.MissingSingularPlaceholder,WordPress.WP.I18n.MismatchedPlaceholders
        ),
        $number
    );

    return (!empty($period)) ? $translated_periods[$period] : $translated_periods;
}

function growtype_wc_get_subscription_statuses()
{
    $subscription_statuses = array (
        'pending' => _x('Pending', 'Subscription status', 'growtype-wc'),
        'active' => _x('Active', 'Subscription status', 'growtype-wc'),
        'on-hold' => _x('On hold', 'Subscription status', 'growtype-wc'),
        'cancelled' => _x('Cancelled', 'Subscription status', 'growtype-wc'),
        'switched' => _x('Switched', 'Subscription status', 'growtype-wc'),
        'expired' => _x('Expired', 'Subscription status', 'growtype-wc'),
        'pending-cancel' => _x('Pending Cancellation', 'Subscription status', 'growtype-wc'),
    );

    return apply_filters('growtype_wc_subscription_statuses', $subscription_statuses);
}

function growtype_wc_sub_get_datetime_from($variable_date_type)
{

    try {
        if (empty($variable_date_type)) {
            $datetime = null;
        } elseif (is_a($variable_date_type, 'WC_DateTime')) {
            $datetime = $variable_date_type;
        } elseif (is_numeric($variable_date_type)) {
            $datetime = new WC_DateTime("@{$variable_date_type}", new DateTimeZone('UTC'));
            $datetime->setTimezone(new DateTimeZone(wc_timezone_string()));
        } else {
            $datetime = new WC_DateTime($variable_date_type, new DateTimeZone(wc_timezone_string()));
        }
    } catch (Exception $e) {
        $datetime = null;
    }

    return $datetime;
}

function growtype_wc_get_subscriptions($status = null)
{
    $posts = get_posts([
        'post_per_page' => -1,
        'post_type' => 'growtype_wc_subs',
        'post_status' => 'any',
    ]);

    $subscriptions = [];
    foreach ($posts as $post) {
        $post->sub_price = wc_price(get_post_meta($post->ID, '_price', true));
        $post->sub_status = get_post_meta($post->ID, '_status', true);
        $post->sub_duration = get_post_meta($post->ID, '_duration', true);
        $post->sub_start_date = date(get_option('date_format') . ' ' . get_option('time_format'), strtotime(get_post_meta($post->ID, '_start_date', true)));
        $post->sub_end_date = date(get_option('date_format') . ' ' . get_option('time_format'), strtotime(get_post_meta($post->ID, '_end_date', true)));

        if (!empty($status) && $status != $post->sub_status) {
            continue;
        }

        array_push($subscriptions, $post);
    }

    return $subscriptions;
}
