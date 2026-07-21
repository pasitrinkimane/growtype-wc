<?php

add_filter('woocommerce_get_price_html', 'growtype_wc_woocommerce_extend_price_html', 10, 2);
function growtype_wc_woocommerce_extend_price_html($price_html, $product)
{
    if (is_admin()) {
        return $price_html;
    }

    $product_id = $product->get_id();

    // 1. Non-subscription product: maintain default price HTML with optional coupon discounts applied
    if (!growtype_wc_product_is_subscription($product_id)) {
        $applied_coupons = !empty(WC()->cart) ? WC()->cart->get_applied_coupons() : null;

        if (!empty($applied_coupons)) {
            $applied_coupon = array_values($applied_coupons)[0];
            if (!empty($applied_coupon)) {
                $coupon = new WC_Coupon($applied_coupon);
                if ($coupon->is_valid_for_product($product, $product_id)) {
                    $regular_price = $product->get_regular_price();
                    $original_price = $product->get_price();
                    $discounted_price = growtype_wc_apply_coupon_discount($original_price, $coupon);

                    if ($discounted_price < $original_price) {
                        $price_html = sprintf(
                            '<del aria-hidden="true">%s</del> <ins aria-hidden="true">%s</ins>',
                            wc_price($regular_price),
                            wc_price($discounted_price)
                        );
                    }
                }
            }
        }
        return $price_html;
    }

    // 2. Subscription product: resolve base billing duration and period
    $is_trial = function_exists('growtype_wc_product_is_trial') && growtype_wc_product_is_trial($product_id);
    if ($is_trial) {
        $billing_duration = function_exists('growtype_wc_get_trial_duration') ? (int)growtype_wc_get_trial_duration($product_id) : 1;
        $billing_period   = function_exists('growtype_wc_get_trial_period') ? growtype_wc_get_trial_period($product_id) : 'day';
    } else {
        $billing_duration = (int)growtype_wc_get_subscription_duration($product_id);
        $billing_period   = growtype_wc_get_subscription_period($product_id);
    }

    $billing_duration = $billing_duration ?: 1;
    $billing_period   = $billing_period ?: 'week';

    // 3. Determine custom price period divisor
    $custom_period = get_post_meta($product_id, '_custom_price_period', true);

    if (!empty($custom_period) && $custom_period !== 'default') {
        $days_in_period = [
            'day'   => 1,
            'week'  => 7,
            'month' => 30,
            'year'  => 365,
        ];
        $billing_days = $billing_duration * ($days_in_period[$billing_period] ?? 7);
        $target_days  = $days_in_period[$custom_period] ?? 7;

        $divisor        = ($billing_days / $target_days) ?: 1;
        $display_period = $custom_period; // key IS the period name (day/week/month/year)
    } else {
        $divisor        = $billing_duration;
        $display_period = $billing_period;
    }

    // 4. Calculate display prices (regular vs. sale/active price)
    $base_price = ($is_trial && function_exists('growtype_wc_get_trial_price'))
        ? (float)growtype_wc_get_trial_price($product_id)
        : (float)$product->get_price();

    $display_price   = $base_price / $divisor;
    $display_regular = (float)$product->get_regular_price() / $divisor;

    // Apply active coupons to display prices
    $applied_coupons = !empty(WC()->cart) ? WC()->cart->get_applied_coupons() : null;
    if (!empty($applied_coupons)) {
        $applied_coupon = array_values($applied_coupons)[0];
        if (!empty($applied_coupon)) {
            $coupon = new WC_Coupon($applied_coupon);
            if ($coupon->is_valid_for_product($product, $product_id)) {
                $display_price = growtype_wc_apply_coupon_discount($display_price, $coupon);
            }
        }
    }

    // Construct WooCommerce price HTML markup
    if ($display_price < $display_regular) {
        $price_html = sprintf(
            '<del aria-hidden="true">%s</del> <ins aria-hidden="true">%s</ins>',
            wc_price($display_regular),
            wc_price($display_price)
        );
    } else {
        $price_html = '<ins aria-hidden="true">' . wc_price($display_price) . '</ins>';
    }

    // 5. Append display period suffix
    $display_period = apply_filters('growtype_wc_subscription_period', $display_period, $product);
    if (!empty($display_period)) {
        $price_html .= '<div class="duration-details"><span class="e-separator">/</span><span class="e-duration-intro">per </span><span class="e-duration">' . $display_period . '</span></div>';
    }

    return $price_html;
}