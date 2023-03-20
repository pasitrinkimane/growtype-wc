<?php
/**
 * Product Loop Start
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/loop-start.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}


if (is_single()) {
    $customizer_preview_style = !get_theme_mod('woocommerce_product_page_related_products_preview_style') ? 'grid' : get_theme_mod('woocommerce_product_page_related_products_preview_style');
    $preview_style_class = 'preview-style--' . (isset($preview_style) && !empty($preview_style) ? $preview_style : $customizer_preview_style);
} else {
    $customizer_preview_style = Growtype_Wc_Product::catalog_default_preview_style();
    $preview_style_class = 'preview-style--' . (isset($preview_style) && !empty($preview_style) ? $preview_style : $customizer_preview_style);
}
?>

<ul class="products columns-<?php echo esc_attr(wc_get_loop_prop('columns')); ?> <?php echo $preview_style_class ?>"
    data-group="<?php echo isset($products_group) ? $products_group : 'default' ?>"
    data-base="<?php echo isset($products_base) ? get_permalink(wc_get_page_id('shop')) : '' ?>">
