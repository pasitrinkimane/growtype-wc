<?php
/**
 * Single Product Image
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/product-image.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.5.1
 */

defined('ABSPATH') || exit;

// Note: `wc_get_gallery_image_html` was added in WC 3.3.2 and did not exist prior. This check protects against theme overrides being used on older versions of WC.
if (!function_exists('wc_get_gallery_image_html')) {
    return;
}

global $product;

$columns = apply_filters('woocommerce_product_thumbnails_columns', 4);
$featured_image_id = $product->get_image_id();
$gallery_image_ids = $product->get_gallery_image_ids();
$woocommerce_product_page_gallery_thumbnails_adaptive_height = get_theme_mod('woocommerce_product_page_gallery_thumbnails_adaptive_height') ? 'enabled' : 'disabled';
$woocommerce_product_page_gallery_thumbnails_adaptive_height = 'woocommerce-product-gallery-adaptive-height-' . $woocommerce_product_page_gallery_thumbnails_adaptive_height;
$woocommerce_product_page_gallery_lightbox = 'woocommerce-product-gallery-lightbox-' . (get_theme_mod('woocommerce_product_page_gallery_lightbox',
        true) ? 'enabled' : 'disabled');

$wrapper_classes = apply_filters(
    'woocommerce_single_product_image_gallery_classes',
    array (
        'woocommerce-product-gallery',
        $woocommerce_product_page_gallery_thumbnails_adaptive_height,
        $woocommerce_product_page_gallery_lightbox,
        'woocommerce-product-gallery--' . (count($gallery_image_ids) > 0 ? 'with-images' : 'without-images'),
        'woocommerce-product-gallery--columns-' . absint($columns),
        'images',
    )
);

$render_data = [
    'wrapper_classes' => $wrapper_classes,
    'featured_image_id' => $featured_image_id,
    'gallery_image_ids' => $gallery_image_ids,
    'columns' => $columns,
];

?>

<?php if (get_theme_mod('woocommerce_product_page_gallery_type',
        'woocommerce-product-gallery-type-2') === 'woocommerce-product-gallery-type-3') { ?>
    <?php echo growtype_wc_include_view('partials.single-product.gallery.type-3', $render_data) ?>
<?php } else { ?>
    <?php echo growtype_wc_include_view('partials.single-product.gallery.type-1', $render_data) ?>
<?php }
