/**
 * Universal Payment Button Component
 *
 * A provider-agnostic registry dispatcher.
 * Each payment provider registers its own button handler via:
 *   GrowtypeWcPaymentButton.register('providerName', handlerFn)
 *
 * Handler signature:
 *   handler(container: HTMLElement, context: { method, type, label }) => void
 *
 * Adding a new provider requires no changes to this file — just call register()
 * from the provider's own module before growtypeWcPaymentButton() is invoked.
 */

import { growtypeWcStripeButtonHandler }   from '../../providers/stripe/growtypeWcStripeButtonHandler';
import { growtypeWcPaypalButtonHandler }    from '../../providers/paypal/growtypeWcPaypalButtonHandler';
import { growtypeWcCoinbaseButtonHandler }  from '../../providers/coinbase/growtypeWcCoinbaseButtonHandler';

class GrowtypeWcPaymentButton {

    /**
     * Provider registry: { providerName: handlerFn }
     * Populated via GrowtypeWcPaymentButton.register() before init.
     */
    static _registry = {};

    /**
     * Register a button handler for a given provider key.
     * @param {string}   provider  Matches data-provider attribute value (e.g. 'stripe')
     * @param {Function} handler   fn(container, { method, type, label }) => void
     */
    static register(provider, handler) {
        if (typeof handler !== 'function') {
            console.error(`[PaymentButton] register('${provider}'): handler must be a function`);
            return;
        }
        GrowtypeWcPaymentButton._registry[provider] = handler;
        console.log(`[PaymentButton] Provider registered: '${provider}'`);
    }

    constructor() {
        this.init();
    }

    init() {
        const buttons = document.querySelectorAll('.growtype-wc-payment-button');
        if (!buttons.length) return;

        buttons.forEach(container => this._renderButton(container));
    }

    _renderButton(container) {
        // Prevent double initialization
        if (container.dataset.initialized === 'true') return;

        const provider = container.dataset.provider || container.getAttribute('payment-provider');
        const method   = container.dataset.method   || container.getAttribute('payment-method')  || '';
        const type     = container.dataset.type     || container.getAttribute('payment-type')    || 'standard';
        const label    = container.dataset.label    || container.getAttribute('data-label')      || '';

        // Mark as initialized before dispatch so re-entrant calls are safe
        container.dataset.initialized = 'true';

        const handler = GrowtypeWcPaymentButton._registry[provider];

        if (!handler) {
            console.warn(`[PaymentButton] No handler registered for provider '${provider}'. ` +
                `Registered: [${Object.keys(GrowtypeWcPaymentButton._registry).join(', ')}]`);
            return;
        }

        console.log(`[PaymentButton] Dispatching to '${provider}' handler — type: ${type}, method: ${method}`);
        handler(container, { method, type, label });
    }
}

// ─── Register built-in providers ─────────────────────────────────────────────
GrowtypeWcPaymentButton.register('stripe',   growtypeWcStripeButtonHandler);
GrowtypeWcPaymentButton.register('paypal',   growtypeWcPaypalButtonHandler);
GrowtypeWcPaymentButton.register('coinbase', growtypeWcCoinbaseButtonHandler);
// To add a new provider externally (e.g. from a child plugin):
//   import { growtypeWcPaymentButton } from '...';
//   GrowtypeWcPaymentButton.register('mollie', mollieHandler);

function growtypeWcPaymentButton() {
    new GrowtypeWcPaymentButton();
}

export { growtypeWcPaymentButton, GrowtypeWcPaymentButton };
