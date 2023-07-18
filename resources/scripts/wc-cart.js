(function ($) {
    "use strict";
    let initialCartContentLoad = true;
    let loadingAnimation = "<span class='spinner-border'><div></div><div></div></span>";

    function loadCartContent() {
        jQuery('.b-shoppingcart .b-shoppingcart-content').html('');
        jQuery('.b-shoppingcart-inner').append(loadingAnimation);
        $.ajax({
            url: ajax_object.ajaxurl,
            type: "post",
            data: {
                action: 'growtype_load_cart'
            }
        }).done(function (data) {
            jQuery('.e-cart').attr('data-amount', data.cart_contents_count);
            jQuery('.b-shoppingcart .spinner-border').remove();
            jQuery('.b-shoppingcart').find('.e-loader').remove();
            jQuery('.b-shoppingcart .b-shoppingcart-inner .b-shoppingcart-main').remove();
            jQuery('.b-shoppingcart .b-shoppingcart-inner').append(data['fragments']['shopping_cart_content']);
            jQuery('.b-shoppingcart .shoppingcart-products-item').each(function (index, element) {
                initiateChangeProductQuantity(jQuery(element).data('cart_item_key'));
                initiateCartItemRemove(jQuery(element).data('cart_item_key'));
            });
        });
    }

    /**
     * Load cart details
     */
    function loadCartDetails() {
        $.ajax({
            url: ajax_object.ajaxurl,
            type: "post",
            data: {
                action: 'get_cart_details_ajax'
            }
        }).done(function (data) {
            jQuery('.e-cart').attr('data-amount', data.products_amount)
            if (data.products_amount > 0) {
                jQuery('.e-cart').removeClass('is-empty');
            } else {
                jQuery('.e-cart').addClass('is-empty');
            }
        });
    }

    jQuery(document).ready(function () {
        if (jQuery('body').hasClass('cart-enabled')) {
            loadCartDetails();
        }
    });

    function initiateChangeProductQuantity(cart_item_key) {
        let productQuantityChanged = false;
        jQuery('.b-shoppingcart .shoppingcart-products-item[data-cart_item_key="' + cart_item_key + '"] .arrow').click(function () {
            if (!productQuantityChanged) {
                productQuantityChanged = true;
                changeProductQuantity(jQuery(this));
                setTimeout(function () {
                    productQuantityChanged = false;
                }, 1500)
            }
        })
    }

    function changeProductQuantity(element, action) {
        let current_amount = element.closest('.product-changeQuantity').find('.amount');
        let current_amount_val = parseInt(current_amount.text());
        let initial_amount_val = current_amount_val;

        let product_id = element.closest('.product-changeQuantity').data('product_id');
        let product_sku = element.closest('.product-changeQuantity').data('product_sku');
        let variation_id = element.closest('.product-changeQuantity').data('variation_id');
        let cart_item_key = element.closest('.shoppingcart-products-item').data('cart_item_key');

        if (element.hasClass('arrow-left')) {
            if (current_amount_val == '1') {
                return false;
            } else {
                jQuery('input[name="cart[' + cart_item_key + '][qty]"]').closest('.quantity').find('.btn-down').click();
                current_amount_val = parseInt(current_amount_val) - 1;
            }
        }

        if (element.hasClass('arrow-right')) {
            jQuery('input[name="cart[' + cart_item_key + '][qty]"]').closest('.quantity').find('.btn-up').click();
            current_amount_val = parseInt(current_amount_val) + 1;
        }

        current_amount.text(current_amount_val);

        if (action !== 'ajax-no') {
            $.ajax({
                url: ajax_object.ajaxurl,
                type: "post",
                data: {
                    quantity: current_amount_val,
                    action: 'update_cart_ajax',
                    status: 'change_quantity',
                    product_sku: product_sku,
                    product_id: product_id,
                    variation_id: variation_id,
                    cart_item_key: cart_item_key,
                },
                beforeSend: function () {
                    jQuery('.e-cart').addClass('is-loading');
                },
                success: function (data) {
                    if (data == 0 || data.error) {
                        if (parseInt(current_amount.text()) > initial_amount_val) {
                            current_amount.text(initial_amount_val);
                        }
                        Swal.fire({
                            icon: 'info',
                            text: data.message,
                        });
                        return false;
                    }

                    if (data.quantity == 0) {
                        return false;
                    }

                    jQuery('.e-cart').attr('data-amount', data.cart_contents_count);
                    jQuery('.b-shoppingcart .e-subtotal_price').html(data.cart_subtotal);

                    jQuery('.b-shoppingcart .shoppingcart-products-item[data-cart_item_key=' + data.cart_item_key + ']').replaceWith(data['fragments']['shopping_cart_single_item']);

                    initiateChangeProductQuantity(data.cart_item_key);
                    initiateCartItemRemove(data.cart_item_key);
                },
                error: function (xhr) {
                },
                complete: function () {
                },
            })
        }
    }

    function addToCart(cart) {

        if (cart.find('button[type="submit"]').hasClass('disabled')) {
            return false;
        }

        cart.find('button[type="submit"]').append('<div class="spinner-border" role="status"></div>')

        const addToCartSuccessEvent = new Event('addToCartSuccess');
        let productIsGrouped = cart.hasClass('grouped_form');
        let serializedCartData = cart.serialize();

        serializedCartData = serializedCartData.replace("add-to-cart", "product_id");

        /**
         * Change default bid value
         */
        serializedCartData = serializedCartData.replace("bid_value", "bid_value_currency");

        let productData = serializedCartData + '&action=add_to_cart_ajax&status=add_to_cart';

        if (!productIsGrouped) {
            if (cart.find('button[type="submit"]').attr('value') !== undefined) {
                productData = productData + '&product_id=' + cart.find('button[type="submit"]').attr('value');
            }

            if (cart.find('button[type="submit"]').attr('product_sku') !== undefined) {
                productData = productData + '&product_sku=' + cart.find('button[type="submit"]').attr('product_sku');
            }

            /**
             * Set bid value
             */
            if (cart.find('button[type="submit"]').hasClass('bid_button')) {
                let bidValue = Number(cart.find('input[name="bid_value"]').val().replace(/[^0-9\.-]+/g, ""));

                if (!isNaN(bidValue)) {
                    productData = productData + '&bid_value=' + bidValue;
                } else {
                    alert('Something went wrong. Please contact our support.')
                    return false;
                }

                /**
                 * Submit bid without ajax
                 */
                cart.submit();
                return false;
            }
        }

        /**
         * Post data to backend
         */
        $.ajax({
            url: ajax_object.ajaxurl,
            type: "post",
            data: productData,
            beforeSend: function () {
                jQuery('.e-cart').addClass('is-loading');
                cart.find('button[type="submit"]').removeClass("is-added").addClass("is-loading");
            },
            success: function (data) {

                if (data.redirect_url) {
                    window.location = data.redirect_url;
                    return false;
                }

                document.dispatchEvent(addToCartSuccessEvent);

                loadCartContent();

                jQuery('.e-cart').addClass('is-scaling');

                setTimeout(function () {
                    jQuery('.e-cart').removeClass('is-scaling');
                }, 4500)

                setTimeout(function () {
                    jQuery("html, body").animate({scrollTop: 0}, 400);
                }, 500)

                cart.find('button[type="submit"]').removeClass("is-loading");
                cart.find('button[type="submit"]').find('.spinner-border').remove();

                setTimeout(function () {
                    jQuery('.e-cart').removeClass('is-loading');
                }, 1500)

                if (data == 0 || data.error) {
                    if (data.message) {
                        Swal.fire({
                            icon: 'info',
                            text: data.message,
                        });
                    }

                    return false;
                }

                if (data.quantity == 0) {
                    Swal.fire({
                        position: 'center',
                        icon: false,
                        title: 'Oops...',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 2500
                    });

                    return false;
                }

                if (jQuery('.b-shoppingcart .shoppingcart-products').length > 0) {
                    if (jQuery('.b-shoppingcart .shoppingcart-products-item[data-cart_item_key="' + data.cart_item_key + '"]').length > 0) {
                        jQuery('.b-shoppingcart .shoppingcart-products-item[data-cart_item_key="' + data.cart_item_key + '"]').replaceWith(data['fragments']['shopping_cart_single_item'])
                    } else {
                        jQuery('.b-shoppingcart .shoppingcart-products').append(data['fragments']['shopping_cart_single_item'])
                    }
                } else {
                    loadCartContent()
                }

                jQuery('.b-shoppingcart .e-subtotal_price').html(data.cart_subtotal);
                jQuery('.e-cart').attr('data-amount', data.cart_contents_count);

                var btn_text = cart.find('button[type="submit"]').text();
                cart.find('button[type="submit"]').removeClass("is-loading").addClass("is-added").text(data.response_text);

                setTimeout(function () {
                    cart.find('button[type="submit"]').text(btn_text);
                }, 1000);

                initiateChangeProductQuantity(data.cart_item_key);
                initiateCartItemRemove(data.cart_item_key);
            },
            error: function (xhr) { // if error occured
                cart.find('button[type="submit"]').removeClass("is-loading").addClass("is-added");
            },
            complete: function () {
            },

        })
    }

    function initiateCartItemRemove(cart_item_key) {
        jQuery('.b-shoppingcart .shoppingcart-products-item[data-cart_item_key="' + cart_item_key + '"] .e-remove').click(function (e) {
            e.preventDefault();
            removeItemFromCart(cart_item_key)
        });
    }

    function removeItemFromCart(cart_item_key) {
        $.ajax({
            url: ajax_object.ajaxurl,
            type: "post",
            data: {
                action: 'update_cart_ajax',
                status: 'remove_from_cart',
                cart_item_key: cart_item_key,
            },
            beforeSend: function () {
                jQuery('.b-shoppingcart .shoppingcart-products-item[data-cart_item_key=' + cart_item_key + ']').fadeOut();
            },
            success: function (data) {
                jQuery('input[name="cart[' + cart_item_key + '][qty]"]').closest('.cart_item').find('.remove').click();
                jQuery('.b-shoppingcart .shoppingcart-products-item[data-cart_item_key=' + data.cart_item_key + ']').remove();
                jQuery('.b-shoppingcart .e-subtotal_price').html(data.cart_subtotal);
                jQuery('.e-cart').attr('data-amount', data.cart_contents_count);
                if (data.cart_contents_count === 0) {
                    loadCartContent()
                }
            },
            error: function (xhr) { // if error occured
            },
            complete: function () {
            },
        })
    }

    /**
     * Disable buy button if empty selects
     */
    if (jQuery('.variations_form select').length > 0) {
        jQuery('.variations_form select').each(function (index, element) {
            if (jQuery(element).val().length === 0) {
                jQuery('.variations_form button[type="submit"]').attr('disabled', true);
            }
        });
    }

    jQuery('.e-cart').click(function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (jQuery('.b-shoppingcart').hasClass('is-active')) {
            jQuery('.b-shoppingcart').removeClass('is-active').addClass('is-pasive');
            jQuery('body', 'html').removeClass('stopscroll');
            jQuery('.b-shoppingcart-overlay').fadeOut();
        } else {
            jQuery('.b-shoppingcart').addClass('is-active').removeClass('is-pasive');
            jQuery('body', 'html').addClass('stopscroll');
            jQuery('.b-shoppingcart-overlay').fadeIn();

            if (initialCartContentLoad) {
                initialCartContentLoad = false;
                loadCartContent()
            }

        }
    });

    /**
     * CLOSE CART SUMMARY WINDOW
     */

    jQuery('.b-shoppingcart, .b-shoppingcart .e-btn--close').click(function () {
        jQuery('.b-shoppingcart').removeClass('is-active').addClass('is-pasive');
        jQuery('body', 'html').removeClass('stopscroll');
        jQuery('.b-shoppingcart-overlay').fadeOut();
    });

    jQuery('.b-shoppingcart .b-shoppingcart-inner').click(function (e) {
        e.stopPropagation()
    });

    /**
     * Add to cart
     */
    jQuery('.ajaxcart-enabled .product .cart[method="post"] button[type="submit"]').click(function (e) {
        e.preventDefault();
        addToCart(jQuery(this).closest('.cart[method="post"]'))
    });

    /**
     * Other event
     */
    jQuery("body").on('updated_cart_totals', function () {
        console.log('updated_cart_totals')
        loadCartContent();
    });

    jQuery("body").on('removed_from_cart', function () {
        console.log('removed_from_cart')
        loadCartContent();
    });

    jQuery("body").on('wc_cart_emptied', function () {
        console.log('wc_cart_emptied')
        loadCartContent();
    });

})(jQuery);
