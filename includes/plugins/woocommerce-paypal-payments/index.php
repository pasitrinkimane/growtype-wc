<?php

add_filter('woocommerce_paypal_payments_checkout_button_renderer_hook', function () {
    return 'woocommerce_review_order_before_submit';
});
