<?php
/**
 * Checkout coupon form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-coupon.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.4.4
 */

defined('ABSPATH') || exit;

if (!wc_coupons_enabled()) { // @codingStandardsIgnoreLine.
    return;
}

?>
<div class="woocommerce-form-coupon-toggle">
    <?php wc_print_notice(apply_filters('woocommerce_checkout_coupon_message', esc_html__('Have a coupon?', 'growtype-wc') . ' <a href="#" class="showcoupon">' . esc_html__('Click here to enter your code', 'growtype-wc') . '</a>'), 'notice'); ?>
</div>

<form class="checkout_coupon woocommerce-form-coupon" method="post" style="display:none">

    <p><?php esc_html_e('If you have a coupon code, please apply it below.', 'growtype-wc'); ?></p>

    <div class="row pt-2">
        <div class="col-9">
            <input type="text" name="coupon_code" class="input-text h-100" placeholder="<?php esc_attr_e('Coupon code', 'growtype-wc'); ?>" id="coupon_code" value=""/>
        </div>
        <div class="col-3">
            <button type="submit" class="button btn btn-primary w-100" name="apply_coupon" value="<?php esc_attr_e('Apply coupon', 'growtype-wc'); ?>"><?php esc_html_e('Apply coupon', 'growtype-wc'); ?></button>
        </div>
    </div>

    <div class="clear"></div>
</form>
