/**
 * Universal Payment Button Component
 * 
 * Automatically converts placeholder divs into functional payment buttons
 * based on provider and method configuration.
 */

class GrowtypeWcPaymentButton {
    constructor() {
        this.init();
    }

    init() {
        const buttons = document.querySelectorAll('.growtype-wc-payment-button');
        if (!buttons.length) return;

        buttons.forEach(container => {
            this.renderButton(container);
        });
    }

    renderButton(container) {
        // Prevent double initialization
        if (container.dataset.initialized === 'true') return;

        // Standard data- attributes with fallback to custom attributes
        const provider = container.dataset.provider || container.getAttribute('payment-provider');
        const method = container.dataset.method || container.getAttribute('payment-method');
        const type = container.dataset.type || container.getAttribute('payment-type') || 'standard';
        const label = container.dataset.label || container.getAttribute('data-label');

        // Mark as initialized
        container.dataset.initialized = 'true';

        switch (provider) {
            case 'stripe':
                this.renderStripeButton(container, method, type, label);
                break;
            case 'paypal':
                this.renderPaypalButton(container, method, type, label);
                break;
            case 'coinbase':
                this.renderCoinbaseButton(container, method, type, label);
                break;
            default:
                console.warn(`Growtype WC: Unknown payment provider '${provider}'`);
        }
    }

    renderStripeButton(container, method, type, label) {
        // Standard data- attributes with fallback to custom attributes
        let fallbackUrl = container.dataset.fallback || container.getAttribute('data-fallback');

        // If container is an anchor and no fallback set, use href
        if (!fallbackUrl && container.tagName === 'A') {
            fallbackUrl = container.getAttribute('href');
        }

        // Extract Product ID
        let productId = container.dataset.productId;
        if (!productId && fallbackUrl) {
            try {
                const url = new URL(fallbackUrl, window.location.origin);
                const params = new URLSearchParams(url.search);
                if (params.has('add-to-cart')) {
                    productId = params.get('add-to-cart');
                }
            } catch (e) {
                console.warn('GrowtypeWcPaymentButton: Invalid fallback URL for product ID extraction');
            }
        }


        // 1. Determine element type
        const el = document.createElement(fallbackUrl ? 'a' : 'button');
        if (fallbackUrl) el.href = fallbackUrl;
        if (!fallbackUrl) el.type = 'button';

        // 2. Base Classes & Styles - Inherit from container
        el.className = container.className || '';
        if (productId) el.dataset.productId = productId;
        if (label) el.textContent = label;

        el.style.cssText = container.style.cssText || '';

        if (type === 'express') {

            // New Express Checkout Element Mounting Point
            const mountId = `stripe-express-${Math.floor(Math.random() * 1000000)}`;
            const mountPoint = document.createElement('div');
            mountPoint.id = mountId;
            mountPoint.className = 'growtype-wc-payment-button-providers stripe-express-checkout-container';
            const extraClass = container.dataset.providerExtraClass;
            mountPoint.style.minHeight = '50px';
            mountPoint.style.display = 'flex';
            mountPoint.style.justifyContent = 'center';
            mountPoint.style.alignItems = 'center';
            mountPoint.style.position = 'relative';

            // Initial Spinner + Button Target
            mountPoint.innerHTML = `
                <div class="stripe-express-element-target w-100"></div>
                <div class="stripe-express-spinner spinner-border text-primary" role="status" style="width: 1.5rem; height: 1.5rem; position: absolute;">
                    <span class="visually-hidden">Loading...</span>
                </div>
            `;

            // Replace placeholder
            container.replaceWith(mountPoint);

            document.addEventListener('growtype_wc_payment_fallback', (e) => {
                if (e.detail.container.includes(mountId)) {
                    console.log('GrowtypeWcPaymentButton: Fallback triggered for', mountId);
                    const currentContainer = document.getElementById(mountId);
                    if (currentContainer) {
                        currentContainer.replaceWith(el);
                    }
                }
            });

            document.addEventListener('growtype_wc_payment_express_ready', (e) => {
                const eventContainerId = typeof e.detail.container === 'string' ? e.detail.container.replace('#', '').split(' ')[0] : '';
                if (eventContainerId === mountId || e.detail.container.includes(mountId)) {
                    const currentContainer = document.getElementById(mountId);
                    if (currentContainer && extraClass) {
                        currentContainer.classList.add(extraClass);
                    }
                }
            });

            document.dispatchEvent(new CustomEvent('growtype_wc_payment_request', {
                detail: {
                    provider: 'stripe',
                    type: 'mount_express',
                    method: method,
                    container: `#${mountId} .stripe-express-element-target`,
                    productId: productId,
                    label: label
                }
            }));

            return; // Exit early, Stripe Element handles everything
        } else {
            // Default Stripe Card Button
            el.classList.add('btn-card');
            if (label) el.textContent = label;
            if (!label) el.textContent = 'Pay with Card';
        }

        // Replace the placeholder container with the new functional button
        container.replaceWith(el);
    }

    renderPaypalButton(container, method, type, label) {
        const btn = document.createElement('a');
        btn.href = '#'; // Would be populated dynamically
        btn.className = 'btn-paypal';

        const labelText = label || 'Pay with PayPal';

        btn.innerHTML = `
            ${labelText}
            <img src="${growtype_wc_params.assets_url}/images/payment-icons/paypal.svg" alt="PayPal" height="20" style="margin-left:8px;">
        `;
        container.appendChild(btn);
    }

    renderCoinbaseButton(container, method, type, label) {
        const btn = document.createElement('a');
        btn.href = '#';
        btn.className = 'btn-coinbase';

        const labelText = label || 'Pay with Crypto';

        btn.innerHTML = `
            ${labelText}
            <img src="${growtype_wc_params.assets_url}/images/payment-icons/coinbase.svg" alt="Coinbase" height="20" style="margin-left:8px;">
        `;
        container.appendChild(btn);
    }
}

function growtypeWcPaymentButton() {
    new GrowtypeWcPaymentButton();
}

export { growtypeWcPaymentButton };


