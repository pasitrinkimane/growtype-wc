import {productSlider} from "./components/sliders/product";
import {productsSlider} from "./components/sliders/products"
import {productGalleryExtend} from "./components/product-gallery"
import {inputQuantity} from "./components/input-quantity"
import {countdown} from "./components/countdown"
import {productVariation} from "./components/product-variation"
import {selectCart} from "./components/select-cart";
import {sidebar} from "./sidebar";

jQuery(document).ready(() => {
    productSlider();
    productsSlider();
    productGalleryExtend();
    inputQuantity();
    productVariation();
    selectCart();
    countdown();
    sidebar();
});
