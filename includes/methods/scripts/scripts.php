<?php

add_filter('woocommerce_enqueue_styles', 'magik_dequeue_styles');
function magik_dequeue_styles($enqueue_styles)
{
    unset($enqueue_styles['woocommerce-general']);
    unset($enqueue_styles['woocommerce-layout']);
    unset($enqueue_styles['woocommerce-smallscreen']);

    return $enqueue_styles;
}

/*
 * Disable default woocommerce scripts
 */
add_action('wp_enqueue_scripts', 'growtype_disable_woocommerce_scripts');
function growtype_disable_woocommerce_scripts()
{
    /**
     * Deregister default wc variations script. Custom scripts "wc-main.js -> select-variation.js" script is used. If enabled, on select error occurs.
     */
    wp_deregister_script('wc-add-to-cart-variation');
    wp_dequeue_script('wc-add-to-cart-variation');

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
//add_action('wp_enqueue_scripts', 'growtype_custom_woocommerce_scripts', 100);
function growtype_custom_woocommerce_scripts()
{

}

/**
 * Ajax
 */

add_filter('woocommerce_ajax_variation_threshold', 'growtype_wc_ajax_variation_threshold');
function growtype_wc_ajax_variation_threshold()
{
    return 150;
}
