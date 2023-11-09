import {productSlider} from "./components/sliders/product";
import {productsSlider} from "./components/sliders/products"
import {productGalleryExtend} from "./components/product-gallery"
import {inputQuantity} from "./components/input-quantity"
import {countdown} from "./components/countdown"
import {productVariation} from "./components/product-variation"
import {selectCart} from "./components/select-cart";
import {message} from "./components/message";
import {sidebar} from "./sidebar";

window.growtypeWc = {}

jQuery(document).ready(() => {
    message();
    productSlider();
    productsSlider();
    productGalleryExtend();
    inputQuantity();
    productVariation();
    selectCart();
    countdown();
    sidebar();
});
