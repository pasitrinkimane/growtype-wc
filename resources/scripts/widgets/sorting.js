import updateURLParameter from '../util/update-url-parameter';

function sorting() {
    const filterProductsByOrderEvent = new Event('filterProductsByOrder');

    document.addEventListener('filterProductsByPrice', widgetOrderInit)
    document.addEventListener('filterProductsByCategories', widgetOrderInit)

    function widgetOrderInit() {

        if (jQuery('.woocommerce-ordering .chosen-container').length === 0 && typeof jQuery.fn.chosen === 'function') {
            setTimeout(function () {
                jQuery('.woocommerce-ordering select').chosen(window.selectArgs);
            }, 200)
        }

        jQuery('.woocommerce-ordering').submit(function (e) {
            e.preventDefault();
        });

        /**
         * Initiate select
         */

        jQuery('.woocommerce-ordering select').change(function (e) {
            const orderingName = jQuery(this).attr('name')
            const orderingValue = jQuery(this).val()
            const current_products_group = jQuery('.products').attr('data-group');
            const current_products_base = jQuery('.products').attr('data-base');

            if (jQuery(this).val().length > 0) {
                woocommerce_params_widgets.orderby = jQuery(this).val();
            }

            /**
             * Replace window location params
             */
            window.history.replaceState('', '', updateURLParameter(window.location.href, orderingName, orderingValue));

            jQuery('.woocommerce-pagination .page-numbers').each(function (index, element) {
                if (typeof jQuery(element).attr('href') !== 'undefined') {
                    let regex = new RegExp('(' + orderingName + '=)[^\&]+');
                    jQuery(element).attr('href', jQuery(element).attr('href').replace(regex, '$1' + orderingValue));
                }
            });

            /**
             * Get products
             */
            $.ajax({
                url: growtype_wc_ajax.url,
                type: "post",
                data: {
                    orderby: woocommerce_params_widgets.orderby,
                    action: 'filter_products',
                    categories_ids: woocommerce_params_widgets.categories_ids,
                    page_nr: growtype_params.page_nr,
                    products_group: current_products_group,
                    min_price: woocommerce_params_widgets.min_price,
                    max_price: woocommerce_params_widgets.max_price,
                    base: current_products_base,
                },
                beforeSend: function () {
                    /**
                     * Spinner add
                     */
                    jQuery('.products').append("<span class='spinner-border'><div></div><div></div></span>").addClass('is-loading');
                },
                success: function (data) {
                    /**
                     * Spinner remove
                     */
                    jQuery('.products .spinner-border').remove();

                    jQuery('.products').removeClass('is-loading').html("").append(data.products).promise().done(function () {
                        document.dispatchEvent(filterProductsByOrderEvent);
                    });

                    if (jQuery('.woocommerce-pagination').length > 0) {
                        jQuery('.woocommerce-pagination').replaceWith(data.pagination);
                    } else {
                        jQuery('.products').after(data.pagination);
                    }

                    if (growtype_params.page_nr > 1 && data.pagination.length === 0) {
                        window.location = current_products_base + '?orderby=' + woocommerce_params_widgets.orderby;
                    }
                }
            });
        });
    }

    widgetOrderInit();
}

export {sorting};


