/**
 * Stripe Payment Button Handler
 *
 * Self-registers with GrowtypeWcPaymentButton registry.
 * Handles both express (Stripe Elements) and standard (card) button types.
 */

function growtypeWcStripeButtonHandler(container, { method, type, label }) {
    let fallbackUrl = container.dataset.fallback || container.getAttribute('data-fallback');
    const returnUrl = container.dataset.returnUrl || container.getAttribute('data-return-url') || '';

    // If container is an anchor and no fallback set, use href
    if (!fallbackUrl && container.tagName === 'A') {
        fallbackUrl = container.getAttribute('href');
    }

    // Extract Product ID — prefer data-product-id, fall back to add-to-cart param in fallback URL
    let productId = container.dataset.productId;
    if (!productId && fallbackUrl) {
        try {
            const url = new URL(fallbackUrl, window.location.origin);
            const params = new URLSearchParams(url.search);
            if (params.has('add-to-cart')) {
                productId = params.get('add-to-cart');
            }
        } catch (e) {
            console.warn('[StripeButtonHandler] Invalid fallback URL for productId extraction');
        }
    }

    // Build fallback element (used if express checkout fails)
    const el = document.createElement(fallbackUrl ? 'a' : 'button');
    if (fallbackUrl) el.href = fallbackUrl;
    if (!fallbackUrl) el.type = 'button';
    el.className = container.className || '';
    if (productId) el.dataset.productId = productId;
    if (returnUrl) {
        el.dataset.returnUrl = returnUrl;
        el.setAttribute('data-return-url', returnUrl);
    }
    if (fallbackUrl) {
        el.dataset.fallback = fallbackUrl;
        el.setAttribute('data-fallback', fallbackUrl);
    }
    if (label) el.textContent = label;
    el.style.cssText = container.style.cssText || '';

    if (type === 'express') {
        const mountId = `stripe-express-${Math.floor(Math.random() * 1000000)}`;
        const spinnerId = `${mountId}-spinner`;
        const extraClass = container.dataset.providerExtraClass;

        const mountPoint = document.createElement('div');
        mountPoint.id = mountId;
        mountPoint.className = 'growtype-wc-payment-button-providers stripe-express-checkout-container';
        mountPoint.style.cssText = 'position:relative;min-height:50px;display:flex;justify-content:center;align-items:center;';
        mountPoint.innerHTML = `
            <div class="stripe-express-element-target w-100"></div>
            <div id="${spinnerId}" class="stripe-express-spinner" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                <div class="spinner-border text-primary" role="status" style="width:1.5rem;height:1.5rem;">
                    <span class="visually-hidden">Loading payment...</span>
                </div>
            </div>
        `;

        container.replaceWith(mountPoint);

        const hideSpinner = () => {
            document.getElementById(spinnerId)?.remove();
        };

        document.addEventListener('growtype_wc_payment_fallback', (e) => {
            if (e.detail.container.includes(mountId)) {
                console.log('[StripeButtonHandler] Fallback triggered for', mountId);
                document.getElementById(mountId)?.replaceWith(el);
            }
        });

        document.addEventListener('growtype_wc_payment_express_ready', (e) => {
            const eventId = typeof e.detail.container === 'string'
                ? e.detail.container.replace('#', '').split(' ')[0]
                : '';
            if (eventId === mountId || e.detail.container.includes(mountId)) {
                hideSpinner();
                const mountEl = document.getElementById(mountId);
                if (mountEl && extraClass) mountEl.classList.add(extraClass);
            }
        });

        // Fallback: remove spinner after 8s in case SDK never resolves
        setTimeout(hideSpinner, 8000);

        document.dispatchEvent(new CustomEvent('growtype_wc_payment_request', {
            detail: {
                provider: 'stripe',
                type: 'mount_express',
                method,
                container: `#${mountId} .stripe-express-element-target`,
                productId,
                label,
                returnUrl,
                fallback: fallbackUrl
            }
        }));

        return;
    }

    // Standard card button
    el.classList.add('btn-card');
    el.textContent = label || 'Pay with Card';
    container.replaceWith(el);
}

export { growtypeWcStripeButtonHandler };
