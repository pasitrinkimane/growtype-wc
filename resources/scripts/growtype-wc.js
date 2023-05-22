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

// jQuery(window).load(function() {
//     jQuery('.woocommerce-product-gallery').flexslider({
//         animation: "slide",
//         direction: "horizontal",
//         slideshowSpeed: 70000,
//         smoothHeight: true,
//         animationSpeed: 500,
//         touch: true,
//         start: function (){
//             console.log('testas 1')
//         },
//         before: function (){
//             console.log('testas 2')
//         },
//         after: function (){
//             console.log('testas 3')
//         },
//     });
// });
