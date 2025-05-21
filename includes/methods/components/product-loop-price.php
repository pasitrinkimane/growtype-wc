<?php

/**
 * Sale flash product single page
 */
add_action('wp_loaded', function () {
    if (!get_theme_mod('woocommerce_product_page_shop_loop_item_price', true)) {
        remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    }
});

add_filter('woocommerce_get_price_html', 'growtype_wc_woocommerce_extend_price_html', 10, 2);
function growtype_wc_woocommerce_extend_price_html($price_html, $product)
{
    if (!is_admin()) {
        $applied_coupons = !empty(WC()->cart) ? WC()->cart->get_applied_coupons() : null;

        if (!empty($applied_coupons)) {
            $applied_coupon = array_values($applied_coupons)[0];

            if (!empty($applied_coupon)) {
                $coupon = new WC_Coupon($applied_coupon);

                if ($coupon->is_valid_for_product($product, $product->get_id())) {
                    $regular_price = $product->get_regular_price();
                    $original_price = $product->get_price();
                    $discounted_price = growtype_wc_apply_coupon_discount($original_price, $coupon);

                    if ($discounted_price < $product->get_price()) {
                        $formatted_regular_price = wc_price($regular_price);
                        $formatted_original_price = wc_price($original_price);
                        $formatted_discounted_price = wc_price($discounted_price);

                        $price_html = sprintf(
                            '<del aria-hidden="true">%s</del> <span class="screen-reader-text">Original price was: %s.</span><ins aria-hidden="true">%s</ins><span class="screen-reader-text">Current price is: %s.</span>',
                            $formatted_regular_price,
                            $regular_price,
                            $formatted_discounted_price,
                            $discounted_price,
                        );
                    }
                }
            }
        }

        if (growtype_wc_product_is_subscription($product->get_id())) {
            $duration = growtype_wc_get_subcription_duration($product->get_id());
            $period = growtype_wc_get_subcription_period($product->get_id());
            $preview_as_monthly = get_post_meta($product->get_id(), '_growtype_wc_subscription_preview_as_monthly', true);

            if ($preview_as_monthly) {
                $price = $product->get_price();
                $months = $period === 'year' ? 12 * $duration : $duration;
                $monthly_price = $price / $months;
                $monthly_price = round($monthly_price);
                $formatted_monthly_price = wc_price($monthly_price);

                $formatted_monthly_price = apply_filters('growtype_wc_subscription_monthly_price', $formatted_monthly_price, $product);

                $price_html = preg_replace_callback('/<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">.*?<\/span>(\d+(\.\d+)?)<\/bdi><\/span>/', function ($matches) use ($formatted_monthly_price) {
                    return '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted_monthly_price . '</bdi></span>';
                }, $price_html);

                $period = 'month';
            }

            $period = apply_filters('growtype_wc_subscription_period', $period, $product);

            if (!empty($period)) {
                $price_html = $price_html . '<div class="duration-details"><span class="e-separator">/</span><span class="e-duration">' . $period . '</span></div>';
            }
        }
    }

    return $price_html;
}

function growtype_wc_apply_coupon_discount($price, $coupon)
{
    $discount_type = $coupon->get_discount_type();
    $discount_amount = $coupon->get_amount();

    if ($discount_type === 'percent') {
        $price = $price - ($price * ($discount_amount / 100));
    } elseif ($discount_type === 'fixed_product') {
        $price = max(0, $price - $discount_amount);
    }

    return $price;
}
