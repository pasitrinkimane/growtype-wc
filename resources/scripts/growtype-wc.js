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
const { accountDeleteConfirmation } = require('./components/accountDeleteConfirmation.cjs');

import { growtypeWcPaymentButton } from "./components/buttons/growtypeWcPaymentButton";
import { GrowtypeWcStripeProvider } from "./providers/stripe/growtypeWcStripeProvider";
import { GrowtypeWcPaypalProvider } from "./providers/paypal/growtypeWcPaypalProvider";

window.growtype_wc = {}

window.growtypeWcPaymentButton = growtypeWcPaymentButton;
window.GrowtypeWcStripeProvider = GrowtypeWcStripeProvider;
window.GrowtypeWcPaypalProvider = GrowtypeWcPaypalProvider;

// Initialize global event listeners immediately on script load
new GrowtypeWcStripeProvider();
new GrowtypeWcPaypalProvider();

jQuery(document).ready(() => {
    accountDeleteConfirmation();
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
