import { productSlider } from "./components/sliders/product";
import { productsSlider } from "./components/sliders/products"
import { productGalleryExtend } from "./components/product-gallery"
import { inputQuantity } from "./components/input-quantity"
import { countdown } from "./components/countdown"
import { productVariation } from "./components/product-variation"
import { selectCart } from "./components/select-cart";
import { message } from "./components/message";
import { justpurchased } from "./components/popup/justpurchased";
import { sidebar } from "./sidebar";

import { growtypeWcPaymentButton } from "./components/buttons/GrowtypeWcPaymentButton";
import { growtypeWcStripeProvider } from "./providers/stripe/growtypeWcStripeProvider";

window.growtype_wc = {}

jQuery(document).ready(() => {
    justpurchased();
    message();
    productSlider();
    productsSlider();
    productGalleryExtend();
    inputQuantity();
    productVariation();
    selectCart();
    countdown();
    sidebar();

    growtypeWcStripeProvider();
    growtypeWcPaymentButton();
});
