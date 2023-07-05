function price() {
    const filterProductsByPriceEvent = new Event('filterProductsByPrice');
    var price_values = 0;

    jQuery(".price_slider").on("slidestart", function (event, ui) {
        price_values = ui.values;
    });

    jQuery(".price_slider").on("slidestop", function (event, ui) {
        if (JSON.stringify(price_values) == JSON.stringify(ui.values)) {
            return false;
        }

        woocommerce_params_widgets.min_price = ui.values[0];
        woocommerce_params_widgets.max_price = ui.values[1];

        var filter = jQuery('.widget_price_filter form');
        var existing_products = jQuery('body').find('.products');
        var existing_main = jQuery('body').find('#main');

        $.ajax({
            url: filter.attr('action'),
            data: filter.serialize(), // form data
            type: filter.attr('method'), // POST
            beforeSend: function (xhr) {
                existing_products.append("<span class='spinner-border'><div></div><div></div></span>").addClass('is-loading');
            },
            success: function (data) {
                jQuery('.products .spinner-border').remove();
                existing_products.removeClass('is-loading');

                var filtered_products = jQuery(data).find('.products');
                var filtered_main = jQuery(data).find('#main');
                window.history.pushState('page-url', 'url', filter.attr('action') + '?' + filter.serialize());
                jQuery('.woocommerce-info').remove();
                if (filtered_products.html().length === 1) {
                    jQuery('#main').prepend(jQuery(data).find('.woocommerce-info'));
                }

                existing_main.replaceWith(filtered_main);
                document.dispatchEvent(filterProductsByPriceEvent);
            }
        });
    });

}

export {price};
