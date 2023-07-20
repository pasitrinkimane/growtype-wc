function filterEmpty(arr) {
    var new_arr = [];
    for (var i = arr.length - 1; i >= 0; i--) {
        if (arr[i] != "")
            new_arr.push(arr.pop());
        else
            arr.pop();
    }
    return new_arr.reverse();
}

function replaceBreaksWithParagraphs(input) {
    input = filterEmpty(input.split('\n')).join('</p><p>');
    return '<p>' + input + '</p>';
}

/**
 * Email template
 */
wp.customize('woocommerce_email_page_template', function (value) {
    value.bind(function (newval) {
        $("#sub-accordion-section-woocommerce_email_page li[id*='main_content']").slideUp();
        if (newval.length > 0) {
            wp.customize.previewer.previewUrl(window.location.origin + '/documentation/examples/email/preview?action=preview_email&email_type=' + newval + '&order_id=' + window.preview_order_id);
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

/**
 * Product Price preview
 */
wp.customize("woocommerce_product_page_shop_loop_item_price_starts_from_text", function (value) {
    value.bind(function (newval) {
        var convertedString = replaceBreaksWithParagraphs(newval);
        convertedString = convertedString.replace(/<[^/>][^>]*><\/[^>]+>/, "");
        $(".product .text-price-startsfrom").text(newval);
    });
});
