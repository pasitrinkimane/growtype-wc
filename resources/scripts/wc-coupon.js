import {message} from "./components/message";

(function ($) {
    "use strict";

    function applyCoupon() {
        jQuery('.e-discount-trigger').click(function () {
            jQuery(this).fadeOut(function () {
                jQuery('.b-coupon').fadeIn();
            });
        });
    }

    applyCoupon();

    jQuery("body").on('applied_coupon', function (event, coupon) {
        console.log('applied_coupon')

        setTimeout(function () {
            applyCoupon();
        }, 1000)

        /**
         * Init message close click
         */
        message();
    });

    jQuery("body").on('applied_coupon_in_checkout', function (event, coupon) {
        console.log('applied_coupon_in_checkout');

        /**
         * Init message close click
         */
        message();
    });

    jQuery("body").on('update_checkout', function (event, coupon) {
        console.log('update_checkout');
    });

    jQuery("body").on('updated_checkout', function (event, coupon) {
        console.log('updated_checkout');
    });

    jQuery("body").on('removed_coupon', function (event, coupon) {
        console.log('removed_coupon');
    });

    jQuery("body").on('updated_wc_div', function (event, coupon) {
        console.log('updated_wc_div');
    });

    $(document.body).on('checkout_error', function () {
        console.log('checkout_error');
    });

    $(document.body).on('woocommerce-applied_coupon-in_checkout', function(event, response){
        console.log('woocommerce-applied_coupon-in_checkout');
    });

    $('.coupon-form button[name="apply_coupon"]').on( 'click', function(){
        console.log('apply_coupon submit')
    });

    jQuery("body").on('updated_cart_totals', function () {
        console.log('updated_cart_totals')
        applyCoupon();
    });
})(jQuery);
