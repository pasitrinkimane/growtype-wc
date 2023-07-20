<?php

/**
 * @return mixed
 * Skip cart page
 */
function growtype_wc_order_overview_disabled()
{
    return get_theme_mod('woocommerce_thankyou_page_order_overview_disabled', false);
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

    return apply_filters('growtype_wc_thankyou_page_intro_content', $woocommerce_thankyou_page_intro_content);
}
