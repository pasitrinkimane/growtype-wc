<?php

/**
 * @return bool
 */
function growtype_wc_format_price($string)
{
    return floatval(str_replace(',', '.', $string));
}

function growtype_wc_price_apply_coupon_discount($wc_product_id, $sale_price, $applied_coupons = [])
{
    if (empty($wc_product_id) || empty($sale_price)) {
        return 0;
    }

    $product = wc_get_product($wc_product_id);

    foreach ($applied_coupons as $coupon_code) {
        $coupon = new WC_Coupon($coupon_code);

        // Skip invalid or non-applicable coupons
        if (!$coupon->is_valid() || !$coupon->is_valid_for_product($product)) {
            continue;
        }

        $discount_type = $coupon->get_discount_type();
        $amount = (float)$coupon->get_amount();

        if ($discount_type === 'percent') {
            $sale_price -= ($sale_price * ($amount / 100));
        } elseif (in_array($discount_type, ['fixed_product', 'fixed_cart'], true)) {
            $sale_price -= $amount;
        }
    }

    return $sale_price > 0 ? round($sale_price, 2) : 0;
}
