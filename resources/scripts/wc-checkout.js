import {message} from "./components/message";

(function ($) {
    "use strict";

    let initialLoad = true;

    jQuery('form.checkout').on('submit', function (event) {
        console.log('submit');
    });

    jQuery(document.body).on('checkout_error', function () {
        if ($(window).width() > 640) {
            let notice = $('.wc-block-components-notice-banner').height() + 100;
            if (notice > 110) {
                $('.woocommerce-checkout-steps .woocommerce-NoticeGroup + .col2-set').css('padding-top', notice + 'px');
            }
        }
        message();
    });

    /**
     * Update state select after country change
     */
    jQuery('select#billing_country, select#shipping_country').on('change', function () {
        resetSelects();
    });

    /**
     * Reset form selects
     */
    function resetSelects() {
        window.select.chosen("destroy");
        if (parseInt(window.growtype_wc_ajax.wc_version) < 8) {
            setTimeout(function () {
                window.select = jQuery('select:visible');
                window.select.chosen(window.selectArgs);
            }, 200)
        } else {
            setTimeout(function () {
                $('select#billing_state').select2('destroy');
                $('select#billing_state').select2();
            }, 100)
        }
    }

    /**
     * Clear payment methods empty description boxes
     */
    jQuery('body').on('updated_checkout', function () {
        console.log('updated_checkout')

        initFloatingLabels();
        resetSelects();

        if (parseInt(window.growtype_wc_ajax.wc_version) > 7) {
            // window.select.chosen("destroy");
        } else {
            jQuery('.woocommerce-checkout .chosen-container').show();
        }

        if ($('select#billing_state').length > 0) {
            if ($('#billing_state option').length > 1) {
                $('#billing_state').closest('.form-row').show();
            } else {
                $('#billing_state').closest('.form-row').hide();
            }
        }

        jQuery(document).on('ready', function () {
            jQuery('.woocommerce-checkout .chosen-container').show();
        });

        jQuery('.wc_payment_methods .payment_box').each(function (index, element) {
            if (jQuery(element).find('p').length === 0 && jQuery(this).hasClass('payment_method_braintree_paypal')) {
                jQuery(this).addClass('is-disabled');
            }
        })

        selectPaymentMethod();
    });

    function selectPaymentMethod() {
        $('.woocommerce-checkout-payment .wc_payment_method label, .woocommerce-checkout-payment .wc_payment_method input').click(function () {
            if (!$(this).closest('.wc_payment_method').hasClass('is-active')) {
                $('.woocommerce-checkout-payment .wc_payment_method').removeClass('is-active')
                $(this).closest('.wc_payment_method').addClass('is-active');
            }
        });

        $('.woocommerce-checkout-payment .wc_payment_method').each(function (index, element) {
            if ($(element).find('input:checked').length > 0) {
                $(element).addClass('is-active');
                return;
            }
        });
    }

    /**
     * Input floating label
     */
    function initFloatingLabels() {
        console.log('initFloatingLabels')
        if ($('body').hasClass('woocommerce-checkout-input-label-style-floating')) {
            $('.form-row .woocommerce-input-wrapper input').each(function (index, element) {
                updateInputLabel($(element));
            });

            $('.form-row label').click(function () {
                $(this).next('input').focus();
            });

            $('.form-row .woocommerce-input-wrapper input').focusin(function () {
                $(this).closest('.form-row').find('label').addClass('is-active');
            });

            $('.form-row .woocommerce-input-wrapper input').focusout(function () {
                updateInputLabel($(this));
            });

            function updateInputLabel(input) {
                if (input.val().length <= 0) {
                    input.closest('.form-row').find('label').removeClass('is-active');
                } else {
                    input.closest('.form-row').find('label').addClass('is-active');
                }
            }
        }
    }

    if ($('body').hasClass('woocommerce-checkout-steps')) {
        $('#customer_details .btn-next').click(function () {

            if (!billingFieldsAreValid()) {
                return
            }

            $(this).closest('.b-actions').fadeOut();

            let formValues = getAllFormValues('.woocommerce-billing-fields__field-wrapper');
            let summary = $('<div class="woocommerce-billing-fields-summary"></div>');

            if (formValues.billing_first_name || formValues.billing_last_name) {
                summary.append('<div class="e-name">' + formValues.billing_first_name + ' ' + formValues.billing_last_name + '</div>')
            }

            Object.entries(formValues).map(function (element) {
                let key = element[0];
                let value = element[1];

                if (key === 'billing_first_name' || key === 'billing_last_name') {
                    return;
                }

                summary.append('<div class="e-value">' + value + '</div>')
            })

            let billingFields = $('.woocommerce-billing-fields__field-wrapper');
            billingFields.fadeOut().promise().done(function () {
                $(this).closest('.col2-set').addClass('is-completed')

                summary.insertAfter(billingFields);

                $(this).closest('.col2-set').find('.woocommerce-additional-fields').fadeIn();

                $('.b-breadcrumb .b-breadcrumb-text').removeClass('is-active');
                $('.b-breadcrumb .b-breadcrumb-text[data-type="payment"]').addClass('is-active');
            })
        });

        $('.woocommerce-billing-fields .btn-edit').click(function () {
            let parent = $(this).closest('.col2-set');

            parent.find('.woocommerce-additional-fields').fadeOut();
            parent.find('.woocommerce-billing-fields-summary').fadeOut().promise().done(function () {
                parent.removeClass('is-completed')
                parent.find('.woocommerce-billing-fields__field-wrapper').fadeIn();
                parent.find('.b-actions').fadeIn();

                $('.b-breadcrumb .b-breadcrumb-text').removeClass('is-active');
                $('.b-breadcrumb .b-breadcrumb-text[data-type="information"]').addClass('is-active');
            })
        });

        function billingFieldsAreValid() {
            jQuery('.validate-required input, .validate-required select').trigger('validate');

            let isValid = true;
            jQuery('.woocommerce-invalid').each(function (index, element) {
                if (element.id.length > 0) {
                    isValid = false;
                }
            })

            return isValid;
        }

        function getAllFormValues(form) {
            const formElements = document.querySelectorAll(form + ' input, ' + form + ' select, ' + form + ' textarea');

            const formData = {};

            formElements.forEach(element => {
                if (element.name) {
                    if (element.type === 'checkbox') {
                        formData[element.name] = element.checked;
                    } else if (element.type === 'select-one' && element.options[element.selectedIndex]) {
                        formData[element.name] = element.options[element.selectedIndex].text;
                    } else {
                        formData[element.name] = element.value;
                    }
                }
            });

            return formData;
        }

        $('#order_review_heading').click(function () {
            $(this).toggleClass('is-open')
        });
    }
})(jQuery);
