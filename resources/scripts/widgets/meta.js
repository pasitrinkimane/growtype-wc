function meta() {
    window.growtypeWc['widgets']['meta'] = {};

    const filterProductsByMetaEvent = new Event('filterProductsByMeta');

    $('.widget_product_meta_filter .b-options-single').click(function () {

        let existingProducts = jQuery('body').find('.products');

        $(this).toggleClass('is-active');

        let metaKey = $('.widget_product_meta_filter .b-content').attr('data-key');

        window.growtypeWc['widgets']['meta']['values'] = [];
        $('.widget_product_meta_filter .b-options-single.is-active').map(function (index, element) {
            let value = $(element).attr('data-value');
            window.growtypeWc['widgets']['meta']['values'].push(value);
        })

        $.ajax({
            url: ajax_object.ajaxurl,
            type: "post",
            data: {
                action: 'filter_products',
                meta_key: metaKey,
                meta_values: JSON.stringify(window.growtypeWc['widgets']['meta']['values']),
                categories_ids: woocommerce_params_widgets.categories_ids,
            },
            beforeSend: function (xhr) {
                existingProducts.append("<span class='spinner-border'><div></div><div></div></span>").addClass('is-loading');
            },
            success: function (data) {
                jQuery('.products .spinner-border').remove();
                existingProducts.removeClass('is-loading');

                jQuery('.woocommerce-info').remove();

                existingProducts.html(data.products)

                if (jQuery('.woocommerce-pagination').length > 0) {
                    jQuery('.woocommerce-pagination').replaceWith(data.pagination);
                } else {
                    jQuery('.products').after(data.pagination);
                }

                document.dispatchEvent(filterProductsByMetaEvent);
            }
        });
    })
}

export {meta};
