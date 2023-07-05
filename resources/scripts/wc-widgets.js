import {sorting} from "./widgets/sorting";
import {price} from "./widgets/price";
import {categories} from "./widgets/categories";
import {meta} from "./widgets/meta";

window.growtypeWc['widgets'] = {};

jQuery(document).ready(() => {
    sorting();
    price();
    categories();
    meta();
});
