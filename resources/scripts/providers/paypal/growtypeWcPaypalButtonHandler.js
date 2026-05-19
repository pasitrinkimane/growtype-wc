/**
 * PayPal Payment Button Handler
 *
 * Self-registers with GrowtypeWcPaymentButton registry.
 * Handles express (Smart Buttons) and standard (branded link) button types.
 */

import { GrowtypeWcPaypalProvider } from './growtypeWcPaypalProvider';

function growtypeWcPaypalButtonHandler(container, { method, type, label }) {
    const fallbackUrl = container.dataset.fallback || '';
    const returnUrl = container.dataset.returnUrl || '';

    // Extract Product ID — prefer data-product-id, fall back to add-to-cart param in fallback URL
    let productId = container.dataset.productId;
    if (!productId && fallbackUrl) {
        try {
            const url = new URL(fallbackUrl, window.location.origin);
            const params = new URLSearchParams(url.search);
            if (params.has('add-to-cart')) {
                productId = params.get('add-to-cart');
                console.log('[PaypalButtonHandler] productId extracted from fallback URL:', productId);
            }
        } catch (e) {
            console.warn('[PaypalButtonHandler] Invalid fallback URL for productId extraction');
        }
    }

    if (!productId) {
        console.warn('[PaypalButtonHandler] No productId found for PayPal express button — container:', container);
    }

    if (type === 'express') {
        const mountId = `paypal-express-${Math.floor(Math.random() * 1000000)}`;
        const spinnerId = `${mountId}-spinner`;

        const mountPoint = document.createElement('div');
        mountPoint.id = mountId;
        mountPoint.className = 'growtype-wc-payment-button-providers paypal-express-checkout-container w-100';
        mountPoint.style.cssText = 'position:relative;min-height:50px;';
        
        container.replaceWith(mountPoint);

        GrowtypeWcPaypalProvider.showSpinner(`#${mountId}`, 'Loading payment options...', false);

        const hideSpinner = () => {
            console.log(mountId, '--------- hideSpinner called --------- ');
            GrowtypeWcPaypalProvider.hideSpinner(`#${mountId}`);
        };

        // Hide spinner when payment method is ready
        document.addEventListener('growtype_wc_payment_express_ready', (e) => {
            console.log('[PaypalButtonHandler] growtype_wc_payment_express_ready event received:', e.detail);
            if (e.detail.container && e.detail.container.includes(mountId)) {
                console.log('[PaypalButtonHandler] Matching container found, hiding spinner:', mountId);
                hideSpinner();
            }
        });

        // Fallback: remove spinner after 8s in case SDK never resolves
        setTimeout(hideSpinner, 8000);

        document.dispatchEvent(new CustomEvent('growtype_wc_payment_request', {
            detail: {
                provider: 'paypal',
                type: 'mount_express',
                method,
                container: `#${mountId}`,
                productId,
                label,
                returnUrl,
                fallback: fallbackUrl
            }
        }));

        return;
    }

    // Standard branded PayPal link
    const config = window.growtype_wc_ajax || window.growtype_wc_params || {};
    const publicUrl = config.public_url || '';

    const btn = document.createElement('a');
    btn.href = fallbackUrl || '#';
    btn.className = 'btn-paypal';
    btn.innerHTML = `
        ${label || 'Pay with PayPal'}
        <img src="${publicUrl}icons/payment-methods/paypal.svg" alt="PayPal" height="20" style="margin-left:8px;">
    `;
    container.replaceWith(btn);
}

export { growtypeWcPaypalButtonHandler };
