function selectCart() {
    (function($) {
        window.growtypeWcCartSelect = jQuery('.cart select');

        window.growtypeWcSelectCartArgs = {
            disable_search_threshold: 20
        };

        if (window.growtypeWcCartSelect.length > 0 && window.growtypeWcCartSelect.chosen) {
            window.growtypeWcCartSelect.chosen(window.growtypeWcSelectCartArgs);
        }
    })(jQuery);
}

export {selectCart};
