function productSlider() {
    (function ($) {
        jQuery('.woocommerce-product-gallery .flex-direction-nav').appendTo('.woocommerce-product-gallery .flex-viewport')

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
                    jQuery('.woocommerce-product-gallery')
                        .addClass(heightSteps)
                        .find('.flex-control-nav')
                        .slick({
                            infinite: false,
                            autoplay: false,
                            slidesToShow: 3,
                            centerMode: false,
                            arrows: true,
                            slidesToScroll: 1,
                            dots: false,
                            vertical: true,
                            responsive: [{
                                breakpoint: 500,
                                settings: {
                                    slidesToShow: 4,
                                },
                            }],
                        })
                }
                jQuery('.woocommerce-product-gallery__wrapper').resize()
            }, 100)
        } else {
            setTimeout(function () {
                if (jQuery('body').hasClass('woocommerce-product-gallery-type-2')) {
                    if (jQuery('.woocommerce .flex-control-nav li').length > 5) {
                        jQuery(".woocommerce .flex-control-nav").slick({
                            infinite: false,
                            autoplay: false,
                            slidesToShow: 4,
                            centerMode: false,
                            arrows: true,
                            slidesToScroll: 1,
                            dots: false,
                            vertical: true,
                            responsive: [{
                                breakpoint: 500,
                                settings: {
                                    slidesToShow: 4,
                                },
                            }],
                        })
                    }
                } else {
                    if (jQuery('.woocommerce .flex-control-nav li').length > 4) {
                        jQuery(".woocommerce .flex-control-nav").slick({
                            infinite: false,
                            autoplay: false,
                            slidesToShow: 4,
                            centerMode: false,
                            arrows: true,
                            slidesToScroll: 1,
                            dots: false,
                            responsive: [{
                                breakpoint: 500,
                                settings: {
                                    slidesToShow: 4,
                                },
                            }],
                        })

                        jQuery(".woocommerce .flex-control-nav").on('beforeChange', function (event, {slideCount: count}, currentSlide, nextSlide) {
                            console.log(count, currentSlide, nextSlide, 'currentSlide, nextSlide')
                        });
                    }
                }
                jQuery('.woocommerce-product-gallery__wrapper').resize()
            }, 100)
        }
    })(jQuery);
}

export {productSlider};


