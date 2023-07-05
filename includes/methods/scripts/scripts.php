<?php

/*
 * Disable default woocommerce scripts
 */
add_action('wp_enqueue_scripts', 'growtype_disable_woocommerce_scripts');
function growtype_disable_woocommerce_scripts()
{
    ## Dequeue WooCommerce styles
    wp_dequeue_style('woocommerce-general');
    wp_dequeue_style('woocommerce-layout');
    wp_dequeue_style('woocommerce-smallscreen');

    ## Dequeue WooCommerce scripts
//        wp_dequeue_script('wc-cart-fragments');
//        wp_dequeue_script('woocommerce');
//        wp_dequeue_script('wc-add-to-cart');
//        wp_deregister_script('js-cookie');
//        wp_dequeue_script('js-cookie');

    /**
     * Deregister default wc variations script. Custom scripts "wc-main.js -> select-variation.js" script is used. If enabled, on select error occurs.
     */
    wp_deregister_script('wc-add-to-cart-variation');
    wp_dequeue_script('wc-add-to-cart-variation');

    /**
     * Flexslider
     */
    //remove_theme_support( 'wc-product-gallery-slider' );

    /**
     * Select
     */
    wp_dequeue_style('selectWoo');
    wp_deregister_style('selectWoo');

    wp_dequeue_script('selectWoo');
    wp_deregister_script('selectWoo');
}

/**
 * Custom scrips
 */
add_action('wp_enqueue_scripts', 'growtype_custom_woocommerce_scripts', 100);
function growtype_custom_woocommerce_scripts()
{
    /**
     * External Scripts
     */
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@9', ['jquery'], GROWTYPE_WC_VERSION, true);
}

/**
 * Ajax
 */

add_filter('woocommerce_ajax_variation_threshold', 'growtype_wc_ajax_variation_threshold');
function growtype_wc_ajax_variation_threshold()
{
    return 150;
}
