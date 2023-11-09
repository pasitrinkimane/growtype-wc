<?php

/**
 * @return mixed
 * Skip cart page
 */
function growtype_wc_order_overview_disabled()
{
    return get_theme_mod('woocommerce_thankyou_page_order_overview', true);
}

function growtype_wc_thankyou_page_intro_content($order = null)
{
    $woocommerce_thankyou_page_intro_content = get_theme_mod('woocommerce_thankyou_page_intro_content');

    if (user_can_access_platform()) {
        $woocommerce_thankyou_page_intro_content_access_platform = get_theme_mod('woocommerce_thankyou_page_intro_content_access_platform');
        if (!empty($woocommerce_thankyou_page_intro_content_access_platform)) {
            $woocommerce_thankyou_page_intro_content = $woocommerce_thankyou_page_intro_content_access_platform;
        }
    }

    if (empty($woocommerce_thankyou_page_intro_content)) {
        $woocommerce_thankyou_page_intro_content = '<h3 class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">' . apply_filters('woocommerce_thankyou_order_received_text',
                esc_html__('Thank you. Your order has been received.', 'growtype-wc'),
                $order) . '</h3>';

        if (!empty($order)) {
            $woocommerce_thankyou_page_intro_content .= '<p>' . esc_html__('Below are the details of your order.', 'growtype-wc') . '</p>';
        } else {
            $woocommerce_thankyou_page_intro_content .= '<p>' . esc_html__('Unfortunately we do not received information about your payment yet.', 'growtype-wc') . '</p>';
        }
    }

    return apply_filters('growtype_wc_thankyou_page_intro_content', $woocommerce_thankyou_page_intro_content, $order);
}

/**
 * @return int[]|WP_Post[]
 */
function growtype_wc_get_user_orders()
{
    $customer_orders = get_posts(array (
        'numberposts' => -1,
        'order' => 'ASC',
        'meta_key' => '_customer_user',
        'meta_value' => get_current_user_id(),
        'post_type' => wc_get_order_types(),
        'post_status' => array_keys(wc_get_order_statuses()),
    ));

    return $customer_orders;
}

/**
 * @return bool|WC_Order|WC_Order_Refund
 */
function growtype_wc_get_user_first_order()
{
    $order = isset(growtype_wc_get_user_orders()[0]) ? wc_get_order(growtype_wc_get_user_orders()[0]->ID) : false;

    return $order;
}

/***
 * @param $order
 * @return mixed|null
 * @throws WC_Data_Exception
 */
function growtype_wc_order_get_subscription_order($order)
{
    foreach ($order->get_items() as $item_id => $item) {
        $is_subscription = growtype_wc_product_is_subscription($item->get_product_id());
        if ($is_subscription) {
            $subscription_order = growtype_wc_create_subscription_order_object([
                'status' => Growtype_Wc_Subscription::STATUS_ACTIVE,
                'order_id' => $order->get_id(),
                'product_id' => $item->get_product_id()
            ]);

            return $subscription_order;
        }
    }

    return null;
}

/**
 * @param $order_id
 * @return bool
 * @throws WC_Data_Exception
 */
function growtype_wc_order_contains_subscription_order($order)
{
    $subscription = growtype_wc_order_get_subscription_order($order);

    return !empty($subscription);
}

/**
 * @param $subscription
 * @return mixed|null
 */
function growtype_wc_order_is_subscription_order($subscription)
{
    if (is_object($subscription) && is_a($subscription, 'WC_Subscription')) {
        $is_subscription = true;
    } else {
        $is_subscription = false;
    }

    return apply_filters('growtype_wc_is_subscription', $is_subscription, $subscription);
}

function growtype_wc_get_order_active_subscriptions($order_id)
{
    $posts = growtype_wc_get_subscriptions(Growtype_Wc_Subscription::STATUS_ACTIVE);

    $subscriptions = [];
    foreach ($posts as $post) {
        if ($post->order_id === $order_id) {
            array_push($subscriptions, $post);
        }
    }

    return $subscriptions;
}
