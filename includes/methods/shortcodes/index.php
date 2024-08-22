<?php

include('partials/coupon.php');
include('partials/wishlist.php');
include('partials/product.php');
include('partials/features.php');
include('partials/payment-methods.php');
include('partials/countdown.php');
include('partials/justpurchased.php');

require_once GROWTYPE_WC_PATH . 'includes/methods/shortcodes/partials/benefits.php';
new Growtype_Wc_Benefits_Shortcode();
