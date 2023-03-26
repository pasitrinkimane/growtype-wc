function productVariation() {
    if (!jQuery('body').hasClass('single-product')) {
        return;
    }

    /**
     * Set global values
     * @type {{}}
     */
    window.growtypeWc = {}

    let variation_form = jQuery('.variations_form.cart')
    let product_id = variation_form.data('product_id');

    window.growtypeWc['productVariations'] = window['product_variations_' + product_id + ''];

    jQuery(document).ready(function ($) {

        /**
         * Initial select
         */
        selectVariation()

        /**
         * Radio select
         */
        jQuery('.variations input[type="radio"]').change(function () {
            jQuery(this).closest('.options').find('.option').removeClass('is-active');
            jQuery(this).closest('.option').addClass('is-active');

            /**
             * Remove select disabled
             */
            selectVariation($(this))
        });

        /**
         * Disable empty select options
         */
        $('.variations-single select option[value=""]').attr('disabled', true);
        $('.variations-single select').trigger("chosen:updated");

        /**
         * Select
         */
        window.growtypeWcCartSelect.change(function (e) {
            selectVariation($(this));
        });
    });

    /**
     * @param $this
     * @returns {boolean}
     */
    function selectVariation(selectedOption = null) {
        jQuery('.variations_form button[type="submit"]').attr('disabled', true);

        /**
         * Reset next options
         */
        resetAvailableOptions(selectedOption);

        /**
         * Disable not available options
         */
        selectAvailableOptions(selectedOption);

        let selectedOptions = {};
        $('.child-variation').each(function (index, element) {
            let variationAtribute;
            let variationValue;

            if ($(element).find('input[type="radio"]:checked').length > 0) {
                variationAtribute = $(element).find('input[type="radio"]:checked').attr('name');
                variationValue = $(element).find('input[type="radio"]:checked').val();
            } else if ($(element).find('select').length > 0) {
                variationAtribute = $(element).find('select').attr('name');
                variationValue = $(element).find('select option:selected').val();
            }

            selectedOptions[variationAtribute] = variationValue;
        });

        if (Object.entries(selectedOptions).length > 0) {
            let selectedVariation;

            window.growtypeWc['productVariations'].map(function (parent) {
                let validValue = true;

                Object.entries(selectedOptions).map(function (variation) {
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
            } else {
                jQuery('.product .summary .price').hide();
            }
        }

        selectDefaultOptions(selectedOption);
    }

    function selectDefaultOptions(selectedOption) {
        let selectedOptionParent = $(selectedOption).closest('.variations-single');

        selectedOptionParent.nextAll('.variations-single').each(function (index, element) {

            let defaultValue = $(element).attr('data-default-value');

            if ($(element).find('select').length > 0) {
                if ($(element).find('select').val() === '' || $(element).find('select').val() === null) {
                    if (!$(element).find('select option[value="' + defaultValue + '"]').attr('disabled')) {
                        $(element).find('select').val(defaultValue)
                    } else {
                        let defaultValue = $(element).find('select option[value!=""][selected!="selected"]').val()

                        $(element).find('select').val(defaultValue)
                    }

                    $(element).find('select').trigger("chosen:updated");
                    $(element).find('select').trigger("change");
                }
            } else if ($(element).find('input[type="radio"]').length > 0) {
                if (!$(element).find('input[type="radio"]').attr('checked')) {
                    if (!$(element).find('input[type="radio"][value="' + defaultValue + '"]').closest('.option').hasClass('is-disabled')) {
                        $(element).find('input[type="radio"][value="' + defaultValue + '"]').first().trigger('click')
                    } else {
                        $(element).find('.option:not(.is-disabled) input[type="radio"]').first().trigger('click')
                    }
                }
            }
        })
    }

    function resetAvailableOptions(selectedOption) {
        if (selectedOption) {
            selectedOption.closest('.variations-single').nextAll('.variations-single').each(function (index, element) {
                let activeOption = getActiveOptionDetails($(element));
                let defaultValue = $(element).attr('data-default-value');

                if (activeOption['type'] === 'select') {
                    $(element).find('select').val('');
                    $(element).find('select').trigger("chosen:updated");
                } else {
                    $(element).find('.options .option')
                        .removeClass('is-active')
                        .removeClass('is-disabled');

                    $(element).find('input').prop('checked', false)
                }
            })
        }
    }

    function selectAvailableOptions(selectedOption) {
        let parentVariation = $('.variations-single.parent-variation');

        if (selectedOption === null) {
            let activeOption = getActiveOptionDetails(parentVariation);

            if (activeOption['option'].val() !== '') {
                selectedOption = activeOption['option'];
            }
        }

        if (!selectedOption || selectedOption.val() === '') {
            parentVariation.nextAll('.variations-single').addClass('is-disabled');
            return;
        }

        let selectedOptionParent = $(selectedOption).closest('.variations-single');

        selectedOptionParent.next('.variations-single').removeClass('is-disabled');

        let activeOption = getActiveOptionDetails(selectedOptionParent);

        let selectedVariations = getSelectedVariations();

        let validVariationValues = [];
        selectedVariations.map(function (element, index) {
            Object.entries(element['attributes']).map(function (element, index) {
                if (validVariationValues[element[0]]) {
                    validVariationValues[element[0]].push(element[1]);
                } else {
                    validVariationValues[element[0]] = [element[1]];
                }
            })
        });

        selectedOptionParent.nextAll('.variations-single').each(function (index, element) {
            if (activeOption['option'].length === 0 || activeOption['option'].val() === '') {
                $(element).next('.variations-single').addClass('is-disabled')

                return;
            } else {
                let activeVariationType = formatVariationTypeAttribute($(element).attr('data-type'))
                let variationValues = validVariationValues[activeVariationType] !== undefined ? validVariationValues[activeVariationType] : null;
                let defaultOptionValue = $(element).attr('data-default-value');

                if (variationValues) {
                    /**
                     * Disable select options
                     */
                    if ($(element).find('select').length > 0) {
                        $(element).find('select option[value!=""]').attr('disabled', false);

                        /**
                         * Disable empty option
                         */
                        $(element).find('select option').map(function (index, option) {
                            if ($(option).val() !== '' && !variationValues.includes($(option).val())) {
                                $(option).attr('disabled', true)
                            }
                        });

                        $(element).next('.variations-single').removeClass('is-disabled');

                        $(element).find('select').trigger("chosen:updated");
                    }

                    /**
                     * Disable radio options
                     */
                    if ($(element).find('input[type="radio"]').length > 0) {
                        $(element).find('.option').map(function (index, option) {
                            if (!variationValues.includes($(option).find('input').val())) {
                                $(option).addClass('is-disabled');
                            }
                        });
                    }
                }
            }
        })
    }

    let initialVariationsInit = true;

    function getSelectedVariations(activeOption) {
        let parentVariations = getParentVariations();

        if (!initialVariationsInit) {
            $('.variations .variations-single').each(function (index, element) {
                if (!$(element).hasClass('parent-variation')) {
                    let activeOption = getActiveOptionDetails($(element));
                    let variationType = formatVariationTypeAttribute($(element).attr('data-type'));

                    if (activeOption['option'].length > 0 && activeOption['option'].val() !== '') {
                        parentVariations = parentVariations.filter(function (variation) {
                            return variation.attributes[variationType] === activeOption['option'].val();
                        })
                    }
                }
            });
        }

        initialVariationsInit = false;

        return parentVariations;
    }

    function getParentVariations() {
        let parentVariation = $('.variations-single.parent-variation');
        let parentVariationActiveOption = getActiveOptionDetails(parentVariation);
        let parentVariationType = formatVariationTypeAttribute(parentVariation.attr('data-type'));

        let validParentVariations = [];
        Object.entries(window.growtypeWc['productVariations']).map(function (variation) {
            Object.entries(variation[1]['attributes']).map(function (element, index) {
                if (element[0] === parentVariationType && element[1] === parentVariationActiveOption['option'].val()) {
                    validParentVariations.push(variation[1])
                }
            })
        });

        return validParentVariations;
    }

    function formatVariationTypeAttribute(type) {
        return 'attribute_' + type;
    }

    function getActiveOptionDetails(element) {
        let selectOption = element.find('select option:checked');
        let radioOption = element.find('input:checked');

        return selectOption.length > 0 ? {
            'type': 'select',
            'option': selectOption
        } : {
            'type': 'radio',
            'option': radioOption
        };
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


