(function ($) {
    document.addEventListener('filterProductsByOrder', wishlistInit)
    document.addEventListener('filterProductsByPrice', wishlistInit)

    "use strict";
    let loadingAnimation = "<span class='spinner-border'><div></div><div></div></span>";
    let wishlist_container = jQuery('.wishlist main .content');

    Array.prototype.unique = function () {
        return this.filter(function (value, index, self) {
            return self.indexOf(value) === index;
        });
    }

    function isInArray(value, array) {
        return array.indexOf(value) > -1;
    }

    function onWishlistComplete(target, title) {
        setTimeout(function () {
            target
                .removeClass('is-loading')
                .attr('title', title);
        }, 500);
    }

    function highlightWishlist(wishlistIds, title) {
        jQuery('.wishlist-toggle').each(function () {
            let $this = jQuery(this);
            let currentProduct = $this.data('product');
            currentProduct = currentProduct.toString();
            if (isInArray(currentProduct, wishlistIds)) {
                $this.addClass('is-active').attr('title', title);
            }
        });
    }

    let shopName = growtype_wc_ajax.shop_name + '-wishlist',
        inWishlist = growtype_wc_ajax.in_wishlist_text,
        restUrl = growtype_wc_ajax.rest_url,
        wishlistIds = new Array,
        ls = sessionStorage.getItem(shopName),
        loggedIn = (jQuery('body').hasClass('logged-in')) ? true : false,
        userData = '';

    if (loggedIn) {
        // Fetch current user data
        fetchWishlistUserData();
    } else {
        if (typeof (ls) != 'undefined' && ls != null) {
            ls = ls.split(',');
            ls = ls.unique();
            wishlistIds = ls;
        }

        if (wishlist_container.length > 0) {
            fetchWishlistUserData();
        } else {
            wishlistInit();
        }
    }

    function fetchWishlistUserData() {

        wishlist_container.append(loadingAnimation);

        $.ajax({
            type: 'POST',
            url: growtype_wc_ajax.url,
            data: {
                'action': 'fetch_user_data',
                'dataType': 'json',
                'wishlist_ids': wishlistIds
            },
            success: function (data) {
                wishlist_container.find('.spinner-border').remove();

                userData = JSON.parse(data);
                wishlistIds = userData['wishlist_ids'];

                if (wishlist_container.length > 0) {
                    if (wishlist_container.find('.products').length === 0) {
                        wishlist_container.hide().html(userData['wishlist']).fadeIn();
                    } else {
                        wishlist_container.html(userData['wishlist']);
                    }
                }

                if (loggedIn) {
                    sessionStorage.removeItem(shopName);
                }

                wishlistInit();

            },
            error: function () {
            }
        });
    }

    function wishListTrigger($this) {
        $this.on('click', function (e) {
            e.preventDefault();
            if (!jQuery(this).hasClass('is-loading')) {
                jQuery(this).addClass('is-loading');

                if (jQuery(this).hasClass('is-active')) {
                    jQuery(this).removeClass('is-active');

                    if (jQuery('body').hasClass('wishlist')) {
                        jQuery(this).closest('.product').fadeOut().promise().done(function () {
                            jQuery(this).remove();
                        });
                    }

                    for (let i = wishlistIds.length - 1; i >= 0; i--) {
                        if (wishlistIds[i] == jQuery(this).data('product')) {
                            wishlistIds.splice(i, 1);
                        }
                    }
                } else {
                    jQuery(this).addClass('is-active')
                    wishlistIds.push(jQuery(this).data('product').toString());
                }

                wishlistIds = wishlistIds.unique().filter(function (v) {
                    return v !== ''
                });

                if (wishlistIds.length === 0 && wishlist_container.length > 0) {
                    wishlist_container.append(loadingAnimation);
                }

                jQuery('.e-wishlist').attr('data-amount', wishlistIds.length)

                if (loggedIn) {
                    $.ajax({
                        type: 'POST',
                        url: growtype_wc_ajax.url,
                        data: {
                            action: 'user_wishlist_update',
                            user_id: userData['user_id'],
                            wishlist_ids: wishlistIds.join(','),
                        }
                    })
                        .done(function (response) {
                            if (wishlistIds.length === 0) {
                                fetchWishlistUserData();
                            }
                        })
                        .fail(function (data) {
                            alert(growtype_wc_ajax.error_text);
                        });
                } else {
                    sessionStorage.setItem(shopName, wishlistIds.toString());
                    if (wishlistIds.length === 0) {
                        fetchWishlistUserData();
                    }
                }

                onWishlistComplete($this, inWishlist);
            }
        });
    }

    wishListTrigger(jQuery('.wishlist-toggle'));

    function wishlistInit() {
        wishlistIds = wishlistIds.filter(function (el) {
            return el != "";
        });
        jQuery('.e-wishlist').attr('data-amount', wishlistIds.length)
        jQuery('.wishlist-toggle').each(function () {
            let $this = jQuery(this);

            if (!loggedIn) {
                let currentProduct = $this.data('product');
                currentProduct = currentProduct.toString();
                if (!loggedIn && isInArray(currentProduct, wishlistIds)) {
                    $this.addClass('is-active').attr('title', inWishlist);
                }
            }

            wishListTrigger($this);
        });
    }

    setTimeout(function () {

        if (wishlistIds.length) {

            restUrl += '?include=' + wishlistIds.join(',');
            restUrl += '&per_page=' + wishlistIds.length;

            $.ajax({
                dataType: 'json',
                url: restUrl
            })
                .done(function (response) {

                })
                .fail(function (response) {
                    alert(growtype_wc_ajax.no_wishlist_text);
                })
        }

    }, 1000);
})(jQuery);
