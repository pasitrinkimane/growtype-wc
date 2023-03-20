(function($) {
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
        setTimeout(function () {
            applyCoupon();
        }, 1000)
    });

    jQuery("body").on('updated_cart_totals', function () {
        applyCoupon();
    });
})(jQuery);
