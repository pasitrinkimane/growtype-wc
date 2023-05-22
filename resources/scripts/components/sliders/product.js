function productSlider() {
    (function ($) {

        /**
         * Check if product gallery exists
         */
        if ($('.woocommerce-product-gallery').length === 0) {
            return;
        }

        let controlNavSliderArgs = $('.woocommerce-product-gallery').attr('data-nav-slider-params');
        controlNavSliderArgs = JSON.parse(controlNavSliderArgs);

        $(document).ready(function () {
            jQuery('.woocommerce-product-gallery .flex-direction-nav').appendTo('.woocommerce-product-gallery .flex-viewport');

            /**
             * Thumbnail index
             */
            jQuery('.single .woocommerce-product-gallery').attr('data-slide-index', 0)

            /**
             * Hide variation image on thumbnail click
             */
            if (jQuery('.woocommerce-product-gallery').data('flexslider')) {
                jQuery('.woocommerce-product-gallery').data('flexslider').vars.before = function (slide, index) {
                    // $('.woocommerce-product-gallery .featured-img-wrapper').fadeOut(300).promise().done(function () {
                    //     $(this).delay(200).fadeIn()
                    // })
                };

                jQuery('.woocommerce-product-gallery').data('flexslider').vars.after = function (slide) {
                    $('.single .woocommerce-product-gallery').attr('data-slide-index', slide.currentSlide)
                };
            }

            /**
             * Thumbnail width
             */
            if (screen.width < 1024) {
                return false;
            }

            let woocommerceProductGallery = jQuery('.woocommerce-product-gallery');
            woocommerceProductGallery.find('.flex-control-nav img').attr('width', woocommerceProductGallery.attr('data-thumbnail-width'));
            woocommerceProductGallery.find('.flex-control-nav img').attr('height', woocommerceProductGallery.attr('data-thumbnail-height'));

            if (woocommerceProductGallery.hasClass('woocommerce-product-gallery-adaptive-height-enabled') &&
                jQuery('body').hasClass('woocommerce-product-gallery-type-2')) {
                setTimeout(function () {
                    let viewportDimentions = jQuery('.woocommerce-product-gallery .flex-viewport');
                    let viewportHeight = viewportDimentions.height();
                    let navHeight = jQuery('.woocommerce-product-gallery .flex-control-nav img').length * jQuery('.woocommerce-product-gallery').attr('data-thumbnail-height');
                    if (navHeight > viewportHeight) {
                        let heightSteps = 'woocommerce-product-gallery-height-small';
                        if (viewportHeight > 400) {
                            heightSteps = 'woocommerce-product-gallery-height-medium';
                        } else if (viewportHeight > 600) {
                            heightSteps = 'woocommerce-product-gallery-height-large';
                        }

                        controlNavSliderArgs['vertical'] = true;

                        jQuery('.woocommerce-product-gallery')
                            .addClass(heightSteps)
                            .find('.flex-control-nav')
                            .slick(controlNavSliderArgs)
                    }
                    jQuery('.woocommerce-product-gallery__wrapper').resize()
                }, 100)
            } else {
                setTimeout(function () {
                    if (jQuery('body').hasClass('woocommerce-product-gallery-type-2')) {
                        if (jQuery('.woocommerce .flex-control-nav li').length > 5) {

                            controlNavSliderArgs.slidesToShow = 4;

                            jQuery(".woocommerce .flex-control-nav")
                                .slick(controlNavSliderArgs)
                        }
                    } else {
                        if (jQuery('.woocommerce .flex-control-nav li').length > 4) {

                            controlNavSliderArgs.slidesToShow = 4;

                            jQuery(".woocommerce .flex-control-nav")
                                .slick(controlNavSliderArgs)

                            // jQuery(".woocommerce .flex-control-nav").on('beforeChange', function (event, {slideCount: count}, currentSlide, nextSlide) {
                            //     console.log(count, currentSlide, nextSlide, 'currentSlide, nextSlide')
                            // });
                        }
                    }
                    jQuery('.woocommerce-product-gallery__wrapper').resize()
                }, 100)
            }
        })
    })(jQuery);
}

export {productSlider};


