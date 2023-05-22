/**
 * Email template
 */
wp.customize('woocommerce_email_page_template', function (value) {
    value.bind(function (newval) {
        $("#sub-accordion-section-woocommerce_email_page li[id*='main_content']").slideUp();
        if (newval.length > 0) {
            wp.customize.previewer.previewUrl(window.location.origin + '/documentation/examples/email/preview?action=previewemail&email_type=' + newval + '&order_id=' + window.preview_order_id);
            $("#sub-accordion-section-woocommerce_email_page li[id*=" + newval.toLowerCase() + "]").slideDown();
        }
    });
});

/**
 * Product page payment details
 */
wp.customize("woocommerce_product_page_payment_details", function (value) {
    value.bind(function (newval) {
        $(".single-product .b-paymentdetails").html(newval);
    });
});

/**
 * Success page extra content
 */
wp.customize("woocommerce_thankyou_page_intro_content", function (value) {
    value.bind(function (newval) {
        $(".woocommerce-order .b-intro-content").html(newval);
    });
});

/**
 * Success page extra content
 */
wp.customize("woocommerce_thankyou_page_intro_content_access_platform", function (value) {
    value.bind(function (newval) {
        $(".woocommerce-order .b-intro-content").html(newval);
    });
});

/**
 * Product single
 */
wp.customize("woocommerce_product_page_sidebar_content", function (value) {
    value.bind(function (newval) {
        $(".sidebar-product .sidebar-inner").html(newval);
    });
});

/**
 * Wc email
 */
wp.customize("wc_email_customer_invoice_successful_main_content", function (value) {
    value.bind(function (newval) {
        var convertedString = replaceBreaksWithParagraphs(newval);
        convertedString = convertedString.replace(/<[^/>][^>]*><\/[^>]+>/, "");
        $("#body_content_inner .b-intro").html(convertedString);
    });
});

wp.customize("wc_email_customer_processing_order_main_content", function (value) {
    value.bind(function (newval) {
        var convertedString = replaceBreaksWithParagraphs(newval);
        convertedString = convertedString.replace(/<[^/>][^>]*><\/[^>]+>/, "");
        $("#body_content_inner .b-intro").html(convertedString);
    });
});

wp.customize("wc_email_customer_completed_order_main_content", function (value) {
    value.bind(function (newval) {
        var convertedString = replaceBreaksWithParagraphs(newval);
        convertedString = convertedString.replace(/<[^/>][^>]*><\/[^>]+>/, "");
        $("#body_content_inner .b-intro").html(convertedString);
    });
});

/**
 * Checkout intro
 */
wp.customize("woocommerce_checkout_intro_text", function (value) {
    value.bind(function (newval) {
        var convertedString = replaceBreaksWithParagraphs(newval);
        convertedString = convertedString.replace(/<[^/>][^>]*><\/[^>]+>/, "");
        $(".woocommerce-checkout-intro").html(newval);
    });
});

/**
 * Product preview cta label
 */
wp.customize("woocommerce_product_preview_cta_label", function (value) {
    value.bind(function (newval) {
        var convertedString = replaceBreaksWithParagraphs(newval);
        convertedString = convertedString.replace(/<[^/>][^>]*><\/[^>]+>/, "");
        $(".products .product .button").text(newval);
    });
});

wp.customize("add_to_cart_button_background_color", function (value) {
    value.bind(function (newval) {
        $('.woocommerce-checkout .woocommerce button.button.alt, .woocommerce div.product form.cart .button, .b-shoppingcart .buttons .btn-primary').css({
            'background-color': newval,
            'border-color': newval,
        });
    });
});

wp.customize("add_to_cart_button_text_color", function (value) {
    value.bind(function (newval) {
        $('.woocommerce-checkout .woocommerce button.button.alt, .woocommerce div.product form.cart .button, .b-shoppingcart .buttons .btn-primary').css({
            'color': newval,
        });
    });
});
