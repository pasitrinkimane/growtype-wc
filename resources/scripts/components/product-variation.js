function productVariation() {
    if (!jQuery('body').hasClass('single-product')) {
        return;
    }

    jQuery(document).ready(function ($) {

        /**
         * Initial select
         */
        select_variation()

        /**
         * Radio select
         */
        jQuery('.variations input[type="radio"]').change(function () {
            jQuery(this).closest('.options').find('.option').removeClass('is-active');
            jQuery(this).closest('.option').addClass('is-active');

            select_variation()
        });

        /**
         * Select
         */
        window.growtypeWcCartSelect.change(function (e) {
            select_variation();
        });
    });

    /**
     * @param $this
     * @returns {boolean}
     */
    function select_variation() {
        let variation_form = jQuery('.variations_form.cart')
        let product_id = variation_form.data('product_id');
        let product_variations = window['product_variations_' + product_id + ''];

        jQuery('.variations_form button[type="submit"]').attr('disabled', true);

        let selectedVariations = {};
        $('.variation-child').each(function (index, element) {
            let variationAtribute;
            let variationValue;

            if ($(element).find('input[type="radio"]:checked').length > 0) {
                variationAtribute = $(element).find('input[type="radio"]:checked').attr('name');
                variationValue = $(element).find('input[type="radio"]:checked').attr('data-category');
            } else if ($(element).find('select').length > 0) {
                variationAtribute = $(element).find('select').attr('name');
                variationValue = $(element).find('select option:selected').val();
            }

            selectedVariations[variationAtribute] = variationValue;
        });

        if (Object.entries(selectedVariations).length > 0) {
            let selectedVariation;

            product_variations.map(function (parent) {
                let validValue = true;

                Object.entries(selectedVariations).map(function (variation) {
                    let variationAtribute = variation[0];
                    let variationValue = variation[1];

                    if (parent.attributes[variationAtribute] !== variationValue) {
                        validValue = false;
                    }
                });

                if (validValue) {
                    selectedVariation = parent;
                }
            });

            if (selectedVariation) {
                variation_form.find('.variation_id').val(selectedVariation['variation_id']);
                update_price(selectedVariation);
                update_featured_image(selectedVariation);

                jQuery('.variations_form button[type="submit"]')
                    .removeClass('disabled')
                    .removeClass('wc-variation-selection-needed')
                    .attr('disabled', false)
            }
        }
    }

    /**
     * Update frontend product price
     */
    function update_price(variation) {
        if (variation['price_html'].length > 0) {
            jQuery('.product .summary .price').replaceWith(variation['price_html']);
        }

        jQuery('.product .summary .price').show();
    }

    /**
     * Update frontend product description
     */
    function update_description(variation) {
        if (variation['variation_description'].length > 0) {
            jQuery('.variations-single-description .variations-single-description-content').html(variation['variation_description']).closest('.variations-single-description').fadeIn();
        } else {
            jQuery('.variations-single-description .variations-single-description-content').html('').closest('.variations-single-description').fadeOut();
        }
    }

    let gallery_featured_image = null;
    let gallery_current_image = gallery_featured_image;

    function update_featured_image(variation) {
        let img_full = variation['image']['full_src'];
        let imgSrc = variation['image']['src'];
        let imgSrcset = variation['image']['srcset'];

        var thumbnailExists = false;
        if (jQuery('.flex-control-nav img').length > 0) {
            jQuery('.flex-control-nav img').each(function (index, element) {
                if (jQuery(element).attr('src') === variation['image']['gallery_thumbnail_src']) {
                    thumbnailExists = true;
                    jQuery(element).trigger('click');
                }
            });
        }

        Object.entries(variation['attributes']).map(function (attribute, index,) {
            $('.featured-img-wrapper').attr('data-' + attribute[0], attribute[1])
        });

        if (gallery_current_image !== imgSrc) {
            gallery_current_image = imgSrc;

            /**
             * For custom gallery variation preview
             */
            if ($('body').hasClass('woocommerce-product-gallery-type-5')) {
                populateWithVariationImage(variation)
            } else {
                $('.woocommerce-product-gallery__image .wp-post-image')
                    .attr('data-large_image', img_full)
                    .attr('src', imgSrc)
                    .attr('srcset', imgSrcset)
            }
        }
    }

    function populateWithVariationImage(variation) {
        let imgSrc = variation['image']['src'];

        $('.woocommerce-product-gallery__image .img-variation').remove();

        if (gallery_featured_image !== gallery_current_image) {
            let img_variation = '<img class="img-variation" src="' + imgSrc + '">'

            $(img_variation).insertAfter('.woocommerce-product-gallery__image .wp-post-image');
        }

        $('.flex-control-nav .featured-img-wrapper').remove()

        let mainImage = $('.woocommerce-product-gallery .featured-img-wrapper')

        $('.woocommerce-product-gallery .flex-control-nav li').map(function (index, element) {
            $(element).append(mainImage.clone())
        });
    }
}

export {productVariation};


