import {message} from "./components/message";

(function ($) {
    "use strict";

    jQuery('form.checkout').on('submit', function (event) {
    });

    jQuery(document.body).on('checkout_error', function () {
        message();
    });

    /**
     * Update state select after country change
     */
    jQuery('select#billing_country, select#shipping_country').on('change', function () {
        window.select.chosen("destroy");
        setTimeout(function () {
            window.select = jQuery('select:visible');
            window.select.chosen(window.selectArgs);
        }, 200)
    });

    /**
     * Clear payment methods empty description boxes
     */
    jQuery('body').on('updated_checkout', function () {
        jQuery('.wc_payment_methods .payment_box').each(function (index, element) {
            if (jQuery(element).find('p').length === 0 && jQuery(this).hasClass('payment_method_braintree_paypal')) {
                jQuery(this).addClass('is-disabled');
            }
        })
    });
})(jQuery);
