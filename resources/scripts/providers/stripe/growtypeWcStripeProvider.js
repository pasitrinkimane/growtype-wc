/**
 * Stripe Payment Provider
 * Handles interactions with Stripe.js for the Universal Payment Button.
 */

class GrowtypeWcStripeProvider {
    constructor() {
        this.stripe = null;
        this.elements = null;
        this.paymentRequest = null;
        this.pendingClientSecret = null;
        this.intentPromise = null;

        this.init();
    }

    init() {
        document.addEventListener('growtype_wc_payment_request', (e) => {
            if (e.detail.provider === 'stripe') {
                if (e.detail.type === 'mount_express') {
                    this.mountExpressCheckout(e.detail);
                } else {
                    this.handlePaymentRequest(e.detail);
                }
            }
        });
    }

    async mountExpressCheckout(detail) {
        const config = await this.getConfig();

        if (!config || !this.initStripe(config)) {
            this.handleFallback(detail);
            return;
        }

        try {
            const productId = detail.productId || this.getProductIdFromPage();

            // 1. Fetch only payment info (no order created yet)
            const infoResponse = await this.fetchPaymentInfo(config, productId);

            if (!infoResponse || !infoResponse.success) throw new Error('Failed to fetch payment info');

            const { amount, currency } = infoResponse.data;

            // 2. Initialize Elements in Deferred Mode
            const elements = this.stripe.elements({
                mode: 'payment',
                amount,
                currency,
                setup_future_usage: 'off_session',
                appearance: { theme: 'stripe' }
            });

            const elementOptions = {
                buttonTheme: {
                    applePay: 'white-outline'
                },
                paymentMethods: {
                    applePay: 'never',
                    googlePay: 'never',
                    paypal: 'never',
                    amazonPay: 'never',
                    link: 'never',
                    klarna: 'never'
                }
            };

            if (detail.method) {
                const requested = detail.method.split(',').map(m => m.trim().toLowerCase());

                // Map of all possible user inputs to Stripe internal keys
                const inputToStripeMap = {
                    'apple': 'applePay',
                    'applepay': 'applePay',
                    'google': 'googlePay',
                    'googlepay': 'googlePay',
                    'paypal': 'paypal',
                    'amazon': 'amazonPay',
                    'amazonpay': 'amazonPay',
                    'link': 'link',
                    'klarna': 'klarna'
                };

                // Apply requested ones
                Object.keys(inputToStripeMap).forEach(inputKey => {
                    const stripeKey = inputToStripeMap[inputKey];
                    if (requested.includes(inputKey)) {
                        elementOptions.paymentMethods[stripeKey] = 'auto';
                    }
                });

                // Construct order correctly
                elementOptions.paymentMethodOrder = [];
                requested.forEach(r => {
                    const stripeKey = inputToStripeMap[r];
                    if (stripeKey && !elementOptions.paymentMethodOrder.includes(stripeKey)) {
                        elementOptions.paymentMethodOrder.push(stripeKey);
                    }
                });
            }

            const expressCheckout = elements.create('expressCheckout', elementOptions);

            // Listeners should be registered BEFORE mounting for maximum reliability
            expressCheckout.on('ready', (event) => {
                // Remove ONLY the spinner, not the container content
                const targetEl = document.querySelector(detail.container);
                const parentEl = targetEl ? targetEl.parentElement : null;
                if (parentEl) {
                    const spinner = parentEl.querySelector('.stripe-express-spinner');
                    if (spinner) spinner.remove();
                }

                if (event.availablePaymentMethods) {
                    const available = Object.keys(event.availablePaymentMethods).filter(m => event.availablePaymentMethods[m]);

                    if (available.length === 0) {
                        this.handleFallback(detail);
                    }
                } else {
                    this.handleFallback(detail);
                }

                // Signal that we are ready and spinner is gone
                document.dispatchEvent(new CustomEvent('growtype_wc_payment_express_ready', {
                    detail: {
                        container: detail.container,
                        provider: 'stripe'
                    }
                }));
            });

            expressCheckout.on('click', (event) => {
                this.intentPromise = this.fetchIntent(config, productId, event.expressPaymentType);

                // Resolve immediately to avoid 1s timeout and maximize responsiveness
                event.resolve();
            });

            expressCheckout.on('confirm', async (event) => {
                this.showLoader();

                try {
                    // Wait for the intent to be created if it was started in 'click'
                    if (!this.intentPromise) {
                        this.intentPromise = this.fetchIntent(config, productId);
                    }

                    const intentResponse = await this.intentPromise;

                    if (!intentResponse || !intentResponse.success) {
                        throw new Error(intentResponse?.data?.message || 'Failed to create order intent');
                    }

                    this.pendingClientSecret = intentResponse.data.clientSecret;
                    this.orderId = intentResponse.data.order_id;
                    this.successUrl = intentResponse.data.success_url || config.success_url;

                    // Submit elements first
                    const { error: submitError } = await elements.submit();
                    if (submitError) {
                        console.error('Elements submit error:', submitError);
                        this.hideLoader();
                        return;
                    }

                    const { error, paymentIntent } = await this.stripe.confirmPayment({
                        elements,
                        clientSecret: this.pendingClientSecret,
                        confirmParams: {
                            return_url: this.successUrl,
                        },
                        redirect: 'if_required'
                    });

                    if (error) {
                        this.hideLoader();
                        // Important: let the element know it failed so it can show its own error or reset
                        // For Express Checkout, event.complete('fail') is not used in confirm, 
                        // but we should handle the UI state.
                    } else if (paymentIntent && (paymentIntent.status === 'succeeded' || paymentIntent.status === 'processing')) {

                        // Signal element that we are done with its part
                        // event.complete(); // Some elements require this, expressCheckout usually doesn't in confirm handler

                        await this.finalizeOrder(config, this.orderId, paymentIntent.id);

                        window.location.href = this.successUrl;
                    } else {
                        console.log('Payment in status:', paymentIntent ? paymentIntent.status : 'unknown');
                        this.hideLoader();
                    }
                } catch (err) {
                    console.error('Error in confirm handler:', err);
                    this.hideLoader();
                }
            });

            expressCheckout.on('error', (err) => {
                this.handleStripeError(err);

                const targetEl = document.querySelector(detail.container);
                const parentEl = targetEl ? targetEl.parentElement : null;
                if (parentEl) {
                    const spinner = parentEl.querySelector('.stripe-express-spinner');
                    if (spinner) spinner.remove();
                }
                this.handleFallback(detail);
            });

            expressCheckout.mount(detail.container);

        } catch (err) {
            console.error('GrowtypeWcStripeProvider Mount Error:', err);
            this.handleFallback(detail);
        }
    }

    async getConfig() {
        const mainConfig = window.growtype_wc_ajax;
        const stripeConfig = mainConfig && mainConfig.stripe ? mainConfig.stripe : null;

        const config = {
            ajax_url: mainConfig ? mainConfig.url : '',
            nonce: mainConfig ? mainConfig.nonce : '',
            publishable_key: stripeConfig ? stripeConfig.publishable_key : '',
            test_mode: stripeConfig ? stripeConfig.test_mode : false,
            success_url: stripeConfig ? stripeConfig.success_url : ''
        };

        return config;
    }

    initStripe(config) {
        if (!config || !config.publishable_key) {
            console.error('GrowtypeWcStripeProvider: Publishable key is missing');
            return false;
        }

        if (!this.stripe) {
            if (typeof Stripe === 'undefined') {
                console.error('GrowtypeWcStripeProvider: Stripe.js not loaded');
                return false;
            }
            try {
                this.stripe = Stripe(config.publishable_key);
            } catch (e) {
                console.error('GrowtypeWcStripeProvider: Stripe initialization failed', e);
                return false;
            }
        }
        return true;
    }

    async finalizeOrder(config, orderId, paymentIntentId) {
        return jQuery.ajax({
            url: config.ajax_url,
            method: 'POST',
            data: {
                action: 'growtype_wc_finalize_order',
                order_id: orderId,
                payment_intent_id: paymentIntentId,
                nonce: config.nonce || ''
            }
        });
    }

    async fetchPaymentInfo(config, productId) {
        return jQuery.ajax({
            url: config.ajax_url,
            method: 'POST',
            data: {
                action: 'growtype_wc_get_payment_info',
                product_id: productId,
                nonce: config.nonce || ''
            }
        });
    }

    async fetchIntent(config, productId, paymentMethodType = '') {
        return jQuery.ajax({
            url: config.ajax_url,
            method: 'POST',
            data: {
                action: 'growtype_wc_create_payment_intent',
                product_id: productId,
                payment_method_type: paymentMethodType,
                nonce: config.nonce || ''
            }
        });
    }

    async handlePaymentRequest(detail) {
        console.log('GrowtypeWcStripeProvider: Handling manual request', detail);
        const config = await this.getConfig();
        if (!config || !this.initStripe(config)) {
            this.handleFallback(detail);
            return;
        }

        try {
            const productId = detail.productId || this.getProductIdFromPage();
            const response = await this.fetchIntent(config, productId);

            if (!response.success) throw new Error(response.data.message);

            const { clientSecret, amount, currency, label, success_url } = response.data;
            const finalSuccessUrl = success_url || config.success_url;

            const pr = this.stripe.paymentRequest({
                country: 'US',
                currency: currency || 'usd',
                total: { label: label || 'Total', amount: amount || 0 },
                requestPayerName: true,
                requestPayerEmail: true,
            });

            const result = await pr.canMakePayment();
            if (result && (result.applePay || result.googlePay)) {
                pr.on('cancel', () => document.dispatchEvent(new CustomEvent('growtype_wc_payment_reset')));
                pr.on('paymentmethod', async (ev) => {
                    const { error } = await this.stripe.confirmPayment({
                        clientSecret,
                        confirmParams: { return_url: finalSuccessUrl },
                        redirect: 'if_required'
                    });
                    if (error) {
                        ev.complete('fail');
                        document.dispatchEvent(new CustomEvent('growtype_wc_payment_reset'));
                    } else {
                        ev.complete('success');
                        window.location.href = finalSuccessUrl;
                    }
                });
                await pr.show();
            } else {
                this.handleFallback(detail);
            }
        } catch (err) {
            this.handleStripeError(err);
            console.error('GrowtypeWcStripeProvider Error:', err);
            this.handleFallback(detail);
        }
    }

    handleFallback(detail) {
        console.info('GrowtypeWcStripeProvider: handleFallback triggered', detail);

        document.dispatchEvent(new CustomEvent('growtype_wc_payment_fallback', {
            detail: {
                container: detail.container,
                fallback: detail.fallback
            }
        }));

        if (detail.fallback) {
            // Optional: If we want immediate redirect on pure fallback calls
            // window.location.href = detail.fallback;
        } else {
            document.dispatchEvent(new CustomEvent('growtype_wc_payment_reset'));
        }
    }

    getProductIdFromPage() {
        // Attempt to find product ID from standard locations if not passed
        // 1. Add-to-cart in URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('add-to-cart')) return urlParams.get('add-to-cart');

        // 2. Global variable?
        if (window.growtype_wc_product_id) return window.growtype_wc_product_id;

        return 0;
    }

    showLoader() {
        if (document.getElementById('growtype-wc-payment-loader')) return;

        const loader = document.createElement('div');
        loader.id = 'growtype-wc-payment-loader';
        loader.style.cssText = `
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100vh !important;
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(5px) !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            z-index: 999999 !important;
            transition: opacity 0.3s ease !important;
        `;

        loader.innerHTML = `
            <div style="display: flex;flex-direction: column;">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem; margin-bottom: 1rem; position:relative;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div style="font-family: inherit; font-weight: 600; color: #1a1a1a;">Processing payment...</div>
            </div>
        `;

        document.body.appendChild(loader);
    }

    hideLoader() {
        const loader = document.getElementById('growtype-wc-payment-loader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 300);
        }
    }

    handleStripeError(err) {
        if (!err) return;

        const message = err.message || '';
        if (message.includes('Another PaymentRequest UI is already showing')) {
            alert('Another PaymentRequest UI is already showing in a different tab or window. Please close it before continuing.');
        }
    }
}

// Initialize
function growtypeWcStripeProvider() {
    new GrowtypeWcStripeProvider();
}

export { growtypeWcStripeProvider };
