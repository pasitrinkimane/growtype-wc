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
 * Check if visability is set with shortcode function
 */
if (get_query_var('visibility') === 'any') {
    $is_visible = true;
} else {
    $is_visible = $product->is_visible();
}

/**
 * Ensure visability
 */
if (empty($product) || !$is_visible) {
    return;
}

/**
 * Remove firs,last classes
 */
$classes = Growtype_Wc_Product::get_classes($product->get_id());

/**
 * Table row
 */
array_push($classes, 'table-body-row');

$classes = implode(' ', $classes);

if (isset($filterClasses) && $filterClasses) {
    $classes = str_replace('first', '', $classes);
    $classes = str_replace('last', '', $classes);
}

$classes = 'class="' . $classes . '"';

echo growtype_wc_include_view('woocommerce.components.table.product-table-row-content', [
    'classes' => $classes,
    'product' => $product,
    'params' => isset($params) ? $params : [],
]);
