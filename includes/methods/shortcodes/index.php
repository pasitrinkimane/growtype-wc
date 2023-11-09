<?php

include('partials/coupon.php');
include('partials/wishlist.php');
include('partials/product.php');
include('partials/features.php');
include('partials/payment-methods.php');
include('partials/countdown.php');

require_once GROWTYPE_WC_PATH . 'includes/methods/shortcodes/partials/subscription-benefits.php';
new Growtype_Wc_Subscription_Benefits_Shortcode();
