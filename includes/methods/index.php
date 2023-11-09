<?php

/**
 * Register theme support
 */
add_action('after_setup_theme', function () {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}, 20);

/**
 * Crud
 */
include('crud/class-growtype-wc-crud.php');

/**
 * Product
 */
include('product/class-growtype-wc-product.php');

/**
 * Templates
 */
include('templates/index.php');

/**
 * Auction
 */
include('product/class-growtype-wc-auction.php');

/**
 * Payment
 */
include('payments/index.php');

/**
 * Shipping
 */
include('shipping/index.php');

/**
 * Admin
 */
include('admin/menu/features.php');
include('admin/menu/orders.php');
include('admin/menu/main.php');

include('admin/list/columns.php');

include('admin/product/sections/data-attributes.php');
include('admin/product/sections/data-general.php');
include('admin/product/sections/data-advanced.php');
include('admin/product/sections/data-inventory.php');
include('admin/product/sections/data-shipping.php');
include('admin/product/sections/data-variation.php');
include('admin/product/sections/data-subscription.php');
include('admin/product/sections/description.php');

include('admin/product/types/subscription.php');

include('scripts/scripts.php');

include('emails/index.php');

include('orders/orders.php');

/**
 * Product single
 */
include('components/modal.php');
include('components/message.php');
include('components/product-single-meta.php');
include('components/product-single-intro.php');
include('components/product-single-tabs.php');
include('components/product-single-excerpt.php');
include('components/product-single-summary.php');
include('components/product-single-gallery.php');
include('components/product-single-related-products.php');
include('components/product-single-button.php');
include('components/product-single-reviews.php');
include('components/product-single-sale-flash.php');
include('components/product-single-quantity.php');

/**
 * Components
 */
include('components/product-loop-link.php');
include('components/product-loop-thumbnail.php');
include('components/product-loop-button.php');
include('components/product-loop-rating.php');
include('components/product-loop-title.php');
include('components/product-loop-price.php');

include('components/product-price.php');

/**
 * Product cart
 */
include('cart/main.php');

/**
 * Widgets
 */
include('widgets/widgets.php');

/**
 * Pages
 */
include('pages/wishlist.php');
include('pages/product.php');
include('pages/login.php');
include('pages/catalog.php');
include('pages/checkout.php');
include('pages/cart.php');
include('pages/account/main.php');
include('pages/thank-you.php');

/**
 * Blocks
 */
include('blocks/product-grid-item.php');

/**
 * Shortcodes
 */
include('shortcodes/index.php');

/**
 * Layout
 */
include('layout/index.php');

/**
 * Users
 */
include('users/index.php');

/**
 * Subscriptions
 */
include('subscriptions/index.php');
