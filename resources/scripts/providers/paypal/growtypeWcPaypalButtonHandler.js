/**
 * PayPal Payment Button Handler
 *
 * Self-registers with GrowtypeWcPaymentButton registry.
 * Handles express (Smart Buttons) and standard (branded link) button types.
 */

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
        mountPoint.innerHTML = `
            <div id="${spinnerId}" style="
                position:absolute;top:0;left:0;width:100%;height:100%;
                display:flex;align-items:center;justify-content:center;
                background:transparent;z-index:10;pointer-events:none;
            ">
                <div class="spinner-border text-primary" role="status" style="width:1.5rem;height:1.5rem;">
                    <span class="visually-hidden">Loading payment...</span>
                </div>
            </div>
        `;

        container.replaceWith(mountPoint);

        const hideSpinner = () => {
            document.getElementById(spinnerId)?.remove();
        };

        // Hide spinner when payment method is ready
        document.addEventListener('growtype_wc_payment_express_ready', (e) => {
            if (e.detail.container && e.detail.container.includes(mountId)) {
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
