import { productSlider } from "./components/sliders/product";
import { productsSlider } from "./components/sliders/products"
import { productGalleryExtend } from "./components/product-gallery"
import { inputQuantity } from "./components/input-quantity"
import { countdown } from "./components/countdown"
import { productVariation } from "./components/product-variation"
import { selectCart } from "./components/select-cart";
import { message } from "./components/message";
import { justpurchased } from "./components/popup/justpurchased";
import { upsellModal } from "./components/upsell-modal";
import { sidebar } from "./sidebar";

import { growtypeWcPaymentButton } from "./components/buttons/growtypeWcPaymentButton";
import { growtypeWcStripeProvider } from "./providers/stripe/growtypeWcStripeProvider";
import { growtypeWcPaypalProvider } from "./providers/paypal/growtypeWcPaypalProvider";

window.growtype_wc = {}

window.growtypeWcPaymentButton = growtypeWcPaymentButton;
window.growtypeWcStripeProvider = growtypeWcStripeProvider;
window.growtypeWcPaypalProvider = growtypeWcPaypalProvider;

jQuery(document).ready(() => {
    growtypeWcStripeProvider();
    growtypeWcPaypalProvider();
    growtypeWcPaymentButton();

    justpurchased();
    upsellModal();
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

jQuery(document).on('growtypeModalLoaded shown.bs.modal', () => {
    growtypeWcPaymentButton();
});
