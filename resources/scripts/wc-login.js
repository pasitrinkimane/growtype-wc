(function ($) {

    if (window.location.hash.length > 0) {
        if (window.location.hash === jQuery('.u-column1 .e-register').attr('href')) {
            jQuery('.u-column1').hide();
            jQuery('.u-column2').fadeIn();
        }
    }

    jQuery('.e-switchform').click(function () {
        if (jQuery(this).closest('.u-column1').length > 0) {
            jQuery(this).closest('.u-column1').fadeOut(function () {
                jQuery('.u-column2').fadeIn();
            })
        } else {
            jQuery(this).closest('.u-column2').fadeOut(function () {
                jQuery('.u-column1').fadeIn();
            })
        }
    });

})(jQuery);
