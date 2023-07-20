import {sorting} from "./widgets/sorting";
import {price} from "./widgets/price";
import {categories} from "./widgets/categories";
import {meta} from "./widgets/meta";

window.growtypeWc['widgets'] = {};

document.addEventListener('filterProductsByPrice', setWidgetsParams)
document.addEventListener('filterProductsByCategories', setWidgetsParams)

function setWidgetsParams() {
    const searchParams = new URLSearchParams(window.location.search)

    woocommerce_params_widgets.min_price = searchParams.get('min_price');
    woocommerce_params_widgets.max_price = searchParams.get('max_price');

    /**
     * Set current orderby value
     */
    woocommerce_params_widgets.orderby = searchParams.get('orderby');

    let categoryId = '';
    if (typeof jQuery('body')[0] !== undefined && jQuery('body')[0].className.match(/term-\d+/) !== null) {
        categoryId = jQuery('body')[0].className.match(/term-\d+/)[0];
        categoryId = categoryId.replace("term-", "");
    }

    if (categoryId.length > 0) {
        woocommerce_params_widgets.categories_ids = [categoryId];
    }
}

setWidgetsParams();

jQuery(document).ready(() => {

    /**
     * Catalog widgets
     */
    if ($(window).width() < 641) {
        $('.btn-catalog-filters').on('click', function () {
            $('.sidebar-shop').fadeIn();
        });

        $('.sidebar-shop').click(function () {
            $(this).fadeOut();
        });

        $('.sidebar-shop .widget, .sidebar-shop .widget-header').click(function () {
            event.stopPropagation();
        });
    }

    sorting();
    price();
    categories();
    meta();
});
