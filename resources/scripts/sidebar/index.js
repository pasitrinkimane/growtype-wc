function sidebar() {
    /**
     * Collapse child categories
     */
    jQuery('.widget .product-categories[data-children-collapse="true"] .cat-parent').map(function (index, element) {
        if (jQuery(element).hasClass('current-cat-parent') || jQuery(element).hasClass('current-cat')) {
            jQuery(element).find('> a').after('<span class="btn btn-collapse"></span>');
        }
    });

    jQuery('.widget .product-categories[data-children-collapse="true"] .cat-parent .btn-collapse').click(function (event) {
        event.preventDefault();
        if (jQuery(this).parent().hasClass('is-collapsed')) {
            jQuery(this).parent().find('.children').slideDown()
            jQuery(this).parent().removeClass('is-collapsed')
        } else {
            jQuery(this).parent().find('.children').slideUp()
            jQuery(this).parent().addClass('is-collapsed')
        }
    });
}

export {sidebar};


