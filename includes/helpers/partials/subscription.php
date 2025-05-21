<?php

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

function growtype_wc_get_subscriptions($args = [])
{
    $meta_query = [];

    if (isset($args['user_id'])) {
        $meta_query[] = [
            [
                'key' => '_user_id',
                'value' => $args['user_id'],
                'compare' => '='
            ]
        ];
    }

    if (isset($args['order_id'])) {
        $meta_query[] = [
            [
                'key' => '_order_id',
                'value' => $args['order_id'],
                'compare' => '='
            ]
        ];
    }

    // Add status filtering if needed
    if (isset($args['status'])) {
        $meta_query[] = [
            'key' => '_status',
            'value' => $args['status'],
            'compare' => '='
        ];
    }

    $query = new WP_Query([
        'posts_per_page' => -1,
        'post_type' => 'growtype_wc_subs',
        'post_status' => 'any',
        'orderby' => 'post_date',
        'order' => 'DESC',
        'meta_query' => $meta_query
    ]);

    $posts = $query->posts;
    $subscriptions = [];

    foreach ($posts as $post) {
        // Fetch all metadata at once
        $meta_data = get_post_meta($post->ID);

        $post->sub_price = wc_price($meta_data['_price'][0] ?? '');
        $post->sub_status = $meta_data['_status'][0] ?? '';
        $post->sub_duration = $meta_data['_duration'][0] ?? '';

        $start_date = $meta_data['_start_date'][0] ?? '';
        $post->sub_start_date = !empty($start_date) ? date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start_date)) : '';

        $end_date = $meta_data['_end_date'][0] ?? '';
        $post->sub_end_date = !empty($end_date) ? date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($end_date)) : '';

        $order_id = $meta_data['_order_id'][0] ?? '';
        $order = wc_get_order($order_id);

        $sub_payment_date = $order ? $order->get_date_paid() : false;
        $post->sub_payment_date = $sub_payment_date
            ? $sub_payment_date->date_i18n(get_option('date_format') . ' ' . get_option('time_format'))
            : '';

        $next_charge_date = $meta_data['_next_charge_date'][0] ?? '';
        $post->sub_next_charge = !empty($next_charge_date) ? date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($next_charge_date)) : '';

        // Add the post to subscriptions array
        $subscriptions[] = $post;
    }

    return $subscriptions;
}

function growtype_wc_get_subcription_duration($product_id)
{
    return growtype_wc_product_is_subscription($product_id) ? (int)get_post_meta($product_id, '_growtype_wc_subscription_duration', true) : null;
}

function growtype_wc_get_subcription_period($product_id)
{
    return growtype_wc_product_is_subscription($product_id) ? get_post_meta($product_id, '_growtype_wc_subscription_period', true) : null;
}
