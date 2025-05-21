import {message} from "./components/message";

(function ($) {
    "use strict";

    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(window.location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    function updateUrlParameter(param) {
        let currentUrl = window.location.href;

        let newUrl;
        if (currentUrl.includes('?')) {
            newUrl = currentUrl.split('?')[0] + `?${param}`;
        } else {
            newUrl = currentUrl + `?${param}`;
        }

        window.history.pushState({}, '', newUrl);
    }

    function removeUrlParameter(param) {
        var url = new URL(window.location.href);
        url.searchParams.delete(param);
        window.history.replaceState({}, document.title, url.toString());
    }

    let couponTrigger = $('.woocommerce-form-coupon-toggle .showcoupon');

    setTimeout(function () {
        if (getUrlParameter('growtype_wc_coupon_form_visible') === '1') {
            couponTrigger.first().click();
        }
    }, 500)

    /**
     * Coupon url params
     */
    couponTrigger.click(function () {
        if ($('.woocommerce-form-coupon').is(':visible')) {
            removeUrlParameter('growtype_wc_coupon_form_visible');
        } else {
            updateUrlParameter('growtype_wc_coupon_form_visible=1');
        }
    });

    function applyCoupon() {
        jQuery('.e-discount-trigger').click(function () {
            jQuery(this).fadeOut(function () {
                jQuery('.b-coupon').fadeIn();
            });
        });
    }

    applyCoupon();

    jQuery("body").on('applied_coupon', function (event, coupon, data) {
        $('.woocommerce-form-coupon-content .woocommerce-message').remove();
        $('.woocommerce-form-coupon-content').append('<div class="woocommerce-message">' + data.message + '</div>');

        setTimeout(function () {
            $('.woocommerce-form-coupon-content').find('.woocommerce-message').fadeOut().promise().done(function () {
                $(this).remove();
            });
        }, 3000);

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

    $(document.body).on('woocommerce-applied_coupon-in_checkout', function (event, response) {
        console.log('woocommerce-applied_coupon-in_checkout');
    });

    $('.coupon-form button[name="apply_coupon"]').on('click', function () {
        console.log('apply_coupon submit')
    });

    jQuery("body").on('updated_cart_totals', function () {
        console.log('updated_cart_totals')
        applyCoupon();
    });

    $(document.body).on('click', 'button[name="apply_coupon"]', function (e) {
        e.preventDefault();
        var form = $(this).closest('form');
        var coupon_code = form.find('input[name="coupon_code"]').val();

        var data = {
            'action': 'growtype_wc_apply_coupon_custom',
            'security': wc_checkout_params.apply_coupon_nonce,
            'coupon_code': coupon_code
        };

        removeUrlParameter('growtype_wc_coupon_form_visible');

        $.ajax({
            type: 'POST',
            url: growtype_wc_ajax.url,
            data: data,
            success: function (response) {
                if (response.success) {
                    $(document.body).trigger('update_checkout');
                    $(document.body).trigger('applied_coupon', [
                        coupon_code,
                        response.data
                    ]);
                } else {
                    $(document.body).trigger('apply_coupon_failed', [
                        coupon_code,
                        response.data
                    ]);
                }
            },
            error: function () {
                $(document.body).trigger('apply_coupon_failed', [
                    coupon_code,
                    {error: 'Ajax error occurred'}
                ]);
            }
        });
    });

    $(document.body).on('apply_coupon_failed', function (event, coupon_code, data) {
        $('.woocommerce-form-coupon-content .woocommerce-message').remove();
        $('.woocommerce-form-coupon-content').append('<div class="woocommerce-message woocommerce-error">' + data.message + '</div>');

        setTimeout(function () {
            $('.woocommerce-form-coupon-content').find('.woocommerce-message').fadeOut().promise().done(function () {
                $(this).remove();
            });
        }, 3000);

        /**
         * Init message close click
         */
        message();
    });
})(jQuery);
