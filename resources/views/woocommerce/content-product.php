<?php

/**
 * The template for displaying product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.6.0
 */

defined('ABSPATH') || exit;

global $product;

/**
 * Check if visibility is set with shortcode function
 */
if (get_query_var('visibility') === 'any') {
    $is_visible = true;
} else {
    $is_visible = $product->is_visible();
}

// Ensure visibility.
if (empty($product) || !$is_visible) {
    return;
}

/**
 * Remove firs,last classes
 */
$classes = wc_get_product_class(get_theme_mod('woocommerce_product_preview_style'), $product);

/**
 * Add auction classes
 */
if (class_exists('growtype-wc\Methods\auction\Growtype_Auction') && Growtype_Auction::has_started()) {
    array_push($classes, 'auction-has-started');
}

$classes = implode(' ', $classes);

if ($filterClasses ?? '') {
    $classes = str_replace('first', '', $classes);
    $classes = str_replace('last', '', $classes);
}

$classes = 'class="' . $classes . '"';

?>
<li <?php echo $classes ?>>
    <?php
    /**
     * Hook: woocommerce_before_shop_loop_item.
     *
     * @hooked woocommerce_template_loop_product_link_open - 10
     */
    do_action('woocommerce_before_shop_loop_item');

    /**
     * Hook: woocommerce_before_shop_loop_item_title.
     *
     * @hooked woocommerce_show_product_loop_sale_flash - 10
     * @hooked woocommerce_template_loop_product_thumbnail - 10
     */
    do_action('woocommerce_before_shop_loop_item_title');

    /**
     * Hook: woocommerce_shop_loop_item_title.
     *
     * @hooked woocommerce_template_loop_product_title - 10
     */
    do_action('woocommerce_shop_loop_item_title');

    /**
     * Hook: woocommerce_after_shop_loop_item_title.
     *
     * @hooked woocommerce_template_loop_rating - 5
     * @hooked woocommerce_template_loop_price - 10
     */
    do_action('woocommerce_after_shop_loop_item_title');

    /**
     * Hook: woocommerce_after_shop_loop_item.
     *
     * @hooked woocommerce_template_loop_product_link_close - 5
     * @hooked woocommerce_template_loop_add_to_cart - 10
     */
    do_action('woocommerce_after_shop_loop_item');
    ?>
</li>
