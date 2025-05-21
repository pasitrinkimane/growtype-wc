<?php

add_filter('woocommerce_cart_totals_coupon_label', function ($label, $coupon) {
    return sprintf(__('Coupon: <span>%s</span>', 'growtype-wc'), $coupon->get_code());
}, 0, 2);

add_action('wp_ajax_growtype_wc_apply_coupon_custom', 'growtype_wc_apply_coupon_custom');
add_action('wp_ajax_nopriv_growtype_wc_apply_coupon_custom', 'growtype_wc_apply_coupon_custom');

function growtype_wc_apply_coupon_custom()
{
    check_ajax_referer('apply-coupon', 'security');

    $coupon_code = $_POST['coupon_code'] ?? '';
    $coupon_code = wc_format_coupon_code(wp_unslash($coupon_code));
    $billing_email = $_POST['billing_email'] ?? '';

    if (is_string($billing_email) && is_email($billing_email)) {
        wc()->customer->set_billing_email($billing_email);
    }

    if (!empty($coupon_code)) {
        WC()->cart->remove_coupon($coupon_code);

        $discount_applied = WC()->cart->add_discount($coupon_code);

        if ($discount_applied) {
            wp_send_json_success([
                'message' => __('Coupon applied successfully.', 'growtype-wc'),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Please enter a valid coupon code.', 'growtype-wc'),
            ]);
        }
    } else {
        wp_send_json_error([
            'message' => __('Please enter a coupon code.', 'growtype-wc'),
        ]);
    }
}
