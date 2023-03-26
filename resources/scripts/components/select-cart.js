function selectCart() {
    (function ($) {
        window.growtypeWcCartSelect = jQuery('.cart select');

        /**
         * Disable empty options
         */
        jQuery('.cart select option')
            .filter(function () {
                return !this.value || $.trim(this.value).length === 0 || $.trim(this.text).length === 0;
            });

        window.growtypeWcSelectCartArgs = {
            disable_search_threshold: 20
        };

        if (window.growtypeWcCartSelect.length > 0 && window.growtypeWcCartSelect.chosen) {
            window.growtypeWcCartSelect.chosen(window.growtypeWcSelectCartArgs);
        }
    })(jQuery);
}

export {selectCart};
