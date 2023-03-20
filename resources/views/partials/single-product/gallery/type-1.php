<div class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class',
    $wrapper_classes))); ?>"
     data-columns="<?php echo esc_attr($columns); ?>"
     data-thumbnail-width="<?php echo growtype_wc_get_product_gallery_sizes()['thumbnail']['width'] ?>"
     data-thumbnail-height="<?php echo growtype_wc_get_product_gallery_sizes()['thumbnail']['height'] ?>" style="opacity: 0; transition: opacity .25s ease-in-out;">
    <figure class="woocommerce-product-gallery__wrapper">
        <?php if ($featured_image_id) {
            $html = wc_get_gallery_image_html($featured_image_id, true);
        } else {
            $html = '<div class="woocommerce-product-gallery__image--placeholder">';
            $html .= sprintf('<img src="%s" alt="%s" class="wp-post-image" />',
                esc_url(wc_placeholder_img_src('woocommerce_single')),
                esc_html__('Awaiting product image', 'growtype-wc'));
            $html .= '</div>';
        }
        echo apply_filters('woocommerce_single_product_image_thumbnail_html', $html, $featured_image_id);
        do_action('woocommerce_product_thumbnails');
        ?>
    </figure>
</div>
