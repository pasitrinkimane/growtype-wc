function selectCart() {
    (function ($) {
        window.growtype_wc_cart_select = jQuery('.cart select');

        window.growtype_wc_select_cart_args = {
            disable_search_threshold: 20
        };

        $(document).ready(function () {
            /**
             * Disable empty options
             */
            jQuery('.cart select option')
                .filter(function () {
                    return !this.value || $.trim(this.value).length === 0 || $.trim(this.text).length === 0;
                });

            if (window.growtype_wc_cart_select.length > 0 && window.growtype_wc_cart_select.chosen) {
                window.growtype_wc_cart_select.chosen(window.growtype_wc_select_cart_args);
            }
        });

        /**
         * Update state select after checkout update
         */
        $(document).on('updated_checkout cfw_updated_checkout', function (e, data) {
            setTimeout(function () {
                $('select').trigger("chosen:updated");
            }, 1000)
        });

        /**
         * Update select on ajax complete
         */
        if ($('body').hasClass('woocommerce-page')) {
            $(document).on("ajaxComplete", function () {
                $('select').trigger("chosen:updated");
            });
        }
    })(jQuery);
}

export {selectCart};
