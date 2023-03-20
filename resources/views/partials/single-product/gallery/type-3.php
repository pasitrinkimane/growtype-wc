<div class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class',
    $wrapper_classes))); ?>" data-columns="<?php echo esc_attr($columns); ?>"
>
    <div class="woocommerce-product-gallery__wrapper">
        <?php if ($featured_image_id) {
            $shop_single_img = wp_get_attachment_metadata($featured_image_id)['sizes']['shop_single'] ?? null;
            ?>
            <div class="img-primary">
                <figure itemprop="associatedMedia" itemscope itemtype="http://schema.org/ImageObject">
                    <div class="e-img-wrapper">
                        <a href="<?php echo wp_get_attachment_url($featured_image_id) ?>" class="e-img-wrapper-inner">
                            <div class="wp-post-image" data-index="0"
                                 style="background: url('<?php echo wp_get_attachment_image_url($featured_image_id, 'medium') ?>');"
                                 data-src="<?php echo wp_get_attachment_url($featured_image_id) ?>"
                                 data-large_image="<?php echo wp_get_attachment_url($featured_image_id) ?>"
                                 data-large_image_width="<?php echo $shop_single_img['width'] ?? '400' ?>"
                                 data-large_image_height="<?php echo $shop_single_img['height'] ?? '400' ?>"></div>
                        </a>
                    </div>
                </figure>
            </div>
            <?php if (count($gallery_image_ids) > 0) { ?>
                <div class="img-secondary">
                    <?php foreach ($gallery_image_ids as $key => $image_id) { ?>
                        <figure style="<?php echo $key > 1 ? 'display:none;' : '' ?>" itemprop="associatedMedia" itemscope itemtype="http://schema.org/ImageObject">
                            <div class="e-img-wrapper">
                                <a href="<?php echo wp_get_attachment_url($image_id) ?>" class="e-img-wrapper-inner">
                                    <div class="wp-post-image"
                                         data-index="<?php echo $key + 1 ?>"
                                         style="background: url('<?php echo wp_get_attachment_image_url($image_id, 'thumbnail') ?>');"
                                         data-src="<?php echo wp_get_attachment_url($image_id) ?>"
                                         data-large_image="<?php echo wp_get_attachment_url($image_id) ?>"
                                         data-large_image_width="<?php echo wp_get_attachment_metadata($image_id)['width'] ?? '' ?>"
                                         data-large_image_height="<?php echo wp_get_attachment_metadata($image_id)['height'] ?? '' ?>"></div>
                                </a>
                            </div>
                        </figure>
                    <?php } ?>
                </div>
                <?php if (count($gallery_image_ids) > 2) { ?>
                    <button class="btn btn-gallery" data-index="3"><?php echo __('Show more photos', 'growtype-wc') ?></button>
                    <?php
                }
            }
        } else {
            $html = '<div class="woocommerce-product-gallery__image--placeholder">';
            $html .= sprintf('<img src="%s" alt="%s" class="wp-post-image" />',
                esc_url(wc_placeholder_img_src('woocommerce_single')),
                esc_html__('Awaiting product image', 'growtype-wc'));
            $html .= '</div>';
        } ?>
    </div>
</div>
