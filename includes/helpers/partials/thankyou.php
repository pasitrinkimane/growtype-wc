<?php

/**
 * @return mixed
 * Skip cart page
 */
function growtype_wc_order_overview_disabled()
{
    return get_theme_mod('woocommerce_thankyou_page_order_overview', true);
}

function growtype_wc_thankyou_page_payment_failed_content($order_id = null)
{
    $checkout_url = growtype_wc_get_order_checkout_url($order_id);

    $content = '<h2>' . __('Payment failed', 'growtype-wc') . '</h2>';
    $content .= '<p>' . sprintf(__('Unfortunately, your order cannot be processed as the payment was not successful. Please try again <a href="%1$s">here</a> or contact our support at <a href="mailto:%2$s">%2$s</a>.', 'growtype-wc'), $checkout_url, get_bloginfo('admin_email')) . '</p>';

    $content = apply_filters('growtype_wc_thankyou_page_payment_failed_content', $content, $order_id);

    return $content;
}

function growtype_wc_thankyou_page_intro_content($order_id = null)
{
    $order = wc_get_order($order_id);

    if (empty($order)) {
        return '';
    }

    $checkout_url = growtype_wc_get_order_checkout_url($order_id);

    if (!empty($order) && $order->is_paid()) {
        $content = get_theme_mod('woocommerce_thankyou_page_intro_content');

        if (empty($content)) {
            $content = '<h3 class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">' . apply_filters('woocommerce_thankyou_order_received_text',
                    esc_html__('Thank you for your order', 'growtype-wc'),
                    $order) . '</h3>';
            $content .= '<p>' . esc_html__('Below are the details of your order.', 'growtype-wc') . '</p>';
        }

        if (growtype_user_can_access_platform()) {
            $platform_content = get_theme_mod('woocommerce_thankyou_page_intro_content_access_platform');
            if (!empty($platform_content)) {
                $content = $platform_content;
            }
        }
    } else {
        $content = '<h3 class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">' . apply_filters('woocommerce_thankyou_order_received_text',
                esc_html__('Oops, incomplete payment', 'growtype-wc'),
                $order) . '</h3>';
        $content .= '<p><b>' . esc_html__("Thank you for your order. However, we haven't received your payment yet.", 'growtype-wc') . '</b></p>';
        $content .= '<p>' . sprintf(__('Please try again <a href="%1$s">here</a> or contact our support at <a href="mailto:%2$s">%2$s</a>.', 'growtype-wc'), $checkout_url, get_bloginfo('admin_email')) . '</p>';
    }

    return apply_filters('growtype_wc_thankyou_page_intro_content', $content, $order_id);
}

/**
 * @return bool
 */
function growtype_wc_is_thankyou_page()
{
    return is_checkout() && !empty(is_wc_endpoint_url('order-received'));
}
