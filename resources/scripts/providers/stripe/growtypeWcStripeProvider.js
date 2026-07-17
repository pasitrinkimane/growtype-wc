/**
 * Stripe Payment Provider
 * Handles interactions with Stripe.js for the Universal Payment Button.
 */

import {
    debugPaymentForm,
    getContainerDebugSummary,
} from '../../util/payment-form-debug';

class GrowtypeWcStripeProvider {
    constructor() {
        this.stripe = null;
        this.elements = null;
        this.paymentRequest = null;
        this.pendingClientSecret = null;
        this.intentPromise = null;
        this.mountedPaymentElementContainers = new Set();

        this.init();
    }

    debug(step, payload = {}) {
        debugPaymentForm('stripe_provider:' + step, payload);
    }

    getConfigDebugSummary(config) {
        return {
            hasConfig: !!config,
            hasAjaxUrl: !!(config && config.ajax_url),
            hasNonce: !!(config && config.nonce),
            enabled: !!(config && config.enabled),
            hasPublishableKey: !!(config && config.publishable_key),
            testMode: !!(config && config.test_mode),
            hasSuccessUrl: !!(config && config.success_url),
        };
    }

    getContainerDebugSummary(selector) {
        const container = selector ? document.querySelector(selector) : null;
        return Object.assign({ selector }, getContainerDebugSummary(container));
    }

    getIntentResponseDebugSummary(response) {
        if (!response) {
            return null;
        }

        const data = response.data || {};

        return {
            success: !!response.success,
            data: {
                hasClientSecret: !!data.clientSecret,
                order_id: data.order_id,
                success_url: data.success_url,
                amount: data.amount,
                currency: data.currency,
                label: data.label,
                message: data.message,
            },
        };
    }

    shouldShowPaymentForm(detail) {
        return detail && (detail.showForm === true || detail.showForm === 'yes' || detail.showForm === '1' || detail.showForm === 'true');
    }

    init() {
        this.debug('init:listening_for_payment_requests');

        document.addEventListener('growtype_wc_payment_request', (e) => {
            this.debug('payment_request:event_received', {
                detail: e.detail,
            });

            if (e.detail.provider === 'stripe') {
                this.debug('payment_request:stripe_request_matched', {
                    type: e.detail.type,
                });

                if (e.detail.type === 'mount_express') {
                    this.mountExpressCheckout(e.detail);
                } else {
                    this.handlePaymentRequest(e.detail);
                }
            } else {
                this.debug('payment_request:ignored_non_stripe_provider', {
                    provider: e.detail.provider,
                    type: e.detail.type,
                });
            }
        });
    }

    async mountExpressCheckout(detail) {
        this.debug('mount_express:start', {
            detail,
            container: this.getContainerDebugSummary(detail.container),
        });

        const config = await this.getConfig();
        this.debug('mount_express:config_resolved', this.getConfigDebugSummary(config));

        if (!config || !config.enabled) {
            this.debug('mount_express:blocked_stripe_disabled', this.getConfigDebugSummary(config));
            console.log('GrowtypeWcStripeProvider: Stripe is disabled, falling back.');
            this.handleFallback(detail, 'stripe_disabled_or_missing_config', this.getConfigDebugSummary(config));
            return;
        }

        if (!await this.initStripe(config)) {
            this.debug('mount_express:blocked_stripe_initialization_failed');
            this.handleFallback(detail, 'stripe_initialization_failed', this.getConfigDebugSummary(config));
            return;
        }

        try {
            const productId = detail.productId || this.getProductIdFromPage();
            this.debug('mount_express:product_resolved', {
                requestedProductId: detail.productId,
                resolvedProductId: productId,
            });

            // 1. Fetch only payment info (no order created yet)
            this.debug('mount_express:fetch_payment_info:start', {
                productId,
            });
            const infoResponse = await this.fetchPaymentInfo(config, productId);
            this.debug('mount_express:fetch_payment_info:response', {
                success: !!(infoResponse && infoResponse.success),
                data: infoResponse ? infoResponse.data : null,
            });

            if (!infoResponse || !infoResponse.success) {
                this.debug('mount_express:blocked_payment_info_failed', {
                    response: infoResponse,
                });
                throw new Error('Failed to fetch payment info');
            }

            const { amount, currency } = infoResponse.data;
            this.debug('mount_express:payment_info_parsed', {
                amount,
                currency,
                hasAmount: typeof amount !== 'undefined',
                hasCurrency: !!currency,
            });

            // 2. Initialize Elements in Deferred Mode
            this.debug('mount_express:elements_create:start', {
                mode: 'payment',
                amount,
                currency,
                setup_future_usage: 'off_session',
            });
            const elements = this.stripe.elements({
                mode: 'payment',
                amount,
                currency,
                setup_future_usage: 'off_session',
                appearance: { theme: 'stripe' }
            });
            this.debug('mount_express:elements_create:done');

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
                this.debug('mount_express:requested_methods_parsed', {
                    raw: detail.method,
                    requested,
                });

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
                        elementOptions.paymentMethods[stripeKey] = ['applePay', 'googlePay'].includes(stripeKey)
                            ? 'always'
                            : 'auto';
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
            this.debug('mount_express:element_options_resolved', elementOptions);

            const expressCheckout = elements.create('expressCheckout', elementOptions);
            this.debug('mount_express:express_checkout_create:done');

            // Listeners should be registered BEFORE mounting for maximum reliability
            expressCheckout.on('ready', (event) => {
                this.debug('mount_express:ready_event', {
                    availablePaymentMethods: event.availablePaymentMethods || null,
                    container: this.getContainerDebugSummary(detail.container),
                });

                // Remove ONLY the spinner, not the container content
                const targetEl = document.querySelector(detail.container);
                const parentEl = targetEl ? targetEl.parentElement : null;
                if (parentEl) {
                    const spinner = parentEl.querySelector('.stripe-express-spinner');
                    if (spinner) spinner.remove();
                }

                if (event.availablePaymentMethods) {
                    const available = Object.keys(event.availablePaymentMethods).filter(m => event.availablePaymentMethods[m]);
                    this.debug('mount_express:ready_available_methods', {
                        available,
                        all: event.availablePaymentMethods,
                    });

                    if (available.length === 0) {
                        this.mountInlinePaymentElement(detail, config, productId, 'no_available_payment_methods', {
                            availablePaymentMethods: event.availablePaymentMethods,
                        });
                    } else if (this.shouldShowPaymentForm(detail)) {
                        this.mountInlinePaymentElement(detail, config, productId, 'express_show_form_enabled', {
                            availablePaymentMethods: event.availablePaymentMethods,
                        }, {
                            preserveExpress: true,
                        });
                    }
                } else {
                    this.mountInlinePaymentElement(detail, config, productId, 'ready_event_missing_available_payment_methods', {
                        event,
                    });
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
                this.debug('mount_express:click_event', {
                    expressPaymentType: event.expressPaymentType,
                    productId,
                    returnUrl: detail.returnUrl || '',
                });
                this.intentPromise = this.fetchIntent(config, productId, event.expressPaymentType, detail.returnUrl || '');

                // Resolve immediately to avoid 1s timeout and maximize responsiveness
                event.resolve();
                this.debug('mount_express:click_event_resolved');
            });

            expressCheckout.on('confirm', async (event) => {
                this.debug('mount_express:confirm_event:start', {
                    hasIntentPromise: !!this.intentPromise,
                    productId,
                    returnUrl: detail.returnUrl || '',
                });
                this.showLoader();

                try {
                    // Wait for the intent to be created if it was started in 'click'
                    if (!this.intentPromise) {
                        this.debug('mount_express:confirm_event:create_intent_without_click');
                        this.intentPromise = this.fetchIntent(config, productId, '', detail.returnUrl || '');
                    }

                    const intentResponse = await this.intentPromise;
                    this.debug('mount_express:confirm_event:intent_response', this.getIntentResponseDebugSummary(intentResponse));

                    if (!intentResponse || !intentResponse.success) {
                        this.debug('mount_express:confirm_event:blocked_intent_failed', {
                            response: intentResponse,
                        });
                        throw new Error(intentResponse?.data?.message || 'Failed to create order intent');
                    }

                    this.pendingClientSecret = intentResponse.data.clientSecret;
                    this.orderId = intentResponse.data.order_id;
                    this.successUrl = intentResponse.data.success_url || config.success_url;
                    this.debug('mount_express:confirm_event:intent_parsed', {
                        hasClientSecret: !!this.pendingClientSecret,
                        orderId: this.orderId,
                        successUrl: this.successUrl,
                    });

                    // Submit elements first
                    const { error: submitError } = await elements.submit();
                    if (submitError) {
                        this.debug('mount_express:confirm_event:elements_submit_error', submitError);
                        console.error('Elements submit error:', submitError);
                        this.hideLoader();
                        return;
                    }
                    this.debug('mount_express:confirm_event:elements_submit_success');

                    const { error, paymentIntent } = await this.stripe.confirmPayment({
                        elements,
                        clientSecret: this.pendingClientSecret,
                        confirmParams: {
                            return_url: this.successUrl,
                        },
                        redirect: 'if_required'
                    });

                    if (error) {
                        this.debug('mount_express:confirm_event:confirm_payment_error', error);
                        this.hideLoader();
                        // Important: let the element know it failed so it can show its own error or reset
                        // For Express Checkout, event.complete('fail') is not used in confirm, 
                        // but we should handle the UI state.
                    } else if (paymentIntent && (paymentIntent.status === 'succeeded' || paymentIntent.status === 'processing')) {
                        this.debug('mount_express:confirm_event:payment_intent_success', {
                            status: paymentIntent.status,
                            paymentIntentId: paymentIntent.id,
                        });

                        // Signal element that we are done with its part
                        // event.complete(); // Some elements require this, expressCheckout usually doesn't in confirm handler

                        await this.finalizeOrder(config, this.orderId, paymentIntent.id);
                        this.debug('mount_express:confirm_event:order_finalized', {
                            orderId: this.orderId,
                        });

                        window.location.href = this.successUrl;
                    } else {
                        this.debug('mount_express:confirm_event:unexpected_payment_status', {
                            status: paymentIntent ? paymentIntent.status : 'unknown',
                            paymentIntent,
                        });
                        console.log('Payment in status:', paymentIntent ? paymentIntent.status : 'unknown');
                        this.hideLoader();
                    }
                } catch (err) {
                    this.debug('mount_express:confirm_event:error', err);
                    console.error('Error in confirm handler:', err);
                    this.hideLoader();
                }
            });

            expressCheckout.on('error', (err) => {
                this.debug('mount_express:error_event', {
                    error: err,
                    container: this.getContainerDebugSummary(detail.container),
                });
                this.handleStripeError(err);

                const targetEl = document.querySelector(detail.container);
                const parentEl = targetEl ? targetEl.parentElement : null;
                if (parentEl) {
                    const spinner = parentEl.querySelector('.stripe-express-spinner');
                    if (spinner) spinner.remove();
                }
                this.mountInlinePaymentElement(detail, config, productId, 'stripe_express_error_event', err);
            });

            this.debug('mount_express:before_mount', {
                container: this.getContainerDebugSummary(detail.container),
            });

            const mountTarget = document.querySelector(detail.container);
            if (!mountTarget) {
                this.handleFallback(detail, 'mount_express_missing_container_before_mount', {
                    container: detail.container,
                });
                return;
            }

            if (mountTarget.childNodes.length > 0) {
                this.debug('mount_express:clearing_mount_target_children', {
                    container: this.getContainerDebugSummary(detail.container),
                });
                mountTarget.innerHTML = '';
            }

            expressCheckout.mount(detail.container);
            this.debug('mount_express:after_mount_called', {
                container: this.getContainerDebugSummary(detail.container),
            });

        } catch (err) {
            this.debug('mount_express:error', {
                error: err,
                container: this.getContainerDebugSummary(detail.container),
            });
            console.error('GrowtypeWcStripeProvider Mount Error:', err);
            this.handleFallback(detail, 'mount_express_exception', err);
        }
    }

    async mountInlinePaymentElement(detail, config, productId, reason = 'express_unavailable', context = {}, options = {}) {
        this.debug('inline_payment_element:start', {
            reason,
            detail,
            productId,
            context,
            options,
            container: this.getContainerDebugSummary(detail.container),
        });

        const expressEl = document.querySelector(detail.container);

        if (!expressEl) {
            this.handleFallback(detail, 'inline_payment_element_missing_container', {
                reason,
                context,
            });
            return;
        }

        let targetEl = expressEl;
        let mountKey = detail.container;

        if (options.preserveExpress) {
            mountKey = detail.container + ':payment_form';
            targetEl = expressEl.parentElement ? expressEl.parentElement.querySelector('.gwc-stripe-inline-payment-element') : null;

            if (!targetEl) {
                targetEl = document.createElement('div');
                targetEl.className = 'gwc-stripe-inline-payment-element';
                targetEl.style.cssText = 'margin-top:16px;min-height:120px;';
                expressEl.insertAdjacentElement('afterend', targetEl);
            }
        }

        if (this.mountedPaymentElementContainers.has(mountKey)) {
            this.debug('inline_payment_element:already_mounted', {
                container: mountKey,
            });
            return;
        }

        this.mountedPaymentElementContainers.add(mountKey);
        targetEl.innerHTML = '';
        targetEl.classList.remove('StripeElement');
        targetEl.classList.add('gwc-stripe-inline-payment-element');
        targetEl.style.minHeight = '120px';

        const paymentElementContainer = document.createElement('div');
        paymentElementContainer.className = 'gwc-stripe-payment-element';

        const messageEl = document.createElement('div');
        messageEl.className = 'gwc-stripe-payment-message';
        messageEl.style.cssText = 'display:none;margin-top:12px;color:#b42318;font-size:14px;';

        const submitBtn = document.createElement('button');
        submitBtn.type = 'button';
        submitBtn.className = 'btn btn-primary gwc-stripe-payment-submit w-100';
        submitBtn.style.cssText = 'margin-top:16px;min-height:48px;font-weight:700;';
        submitBtn.textContent = detail.label || 'Pay now';

        targetEl.appendChild(paymentElementContainer);
        targetEl.appendChild(messageEl);
        targetEl.appendChild(submitBtn);

        try {
            this.debug('inline_payment_element:fetch_intent:start', {
                productId,
                returnUrl: detail.returnUrl || '',
            });

            const intentResponse = await this.fetchIntent(config, productId, 'card', detail.returnUrl || '');
            this.debug('inline_payment_element:fetch_intent:response', this.getIntentResponseDebugSummary(intentResponse));

            if (!intentResponse || !intentResponse.success) {
                throw new Error(intentResponse?.data?.message || 'Failed to create Stripe payment form intent');
            }

            const clientSecret = intentResponse.data.clientSecret;
            const orderId = intentResponse.data.order_id;
            const successUrl = intentResponse.data.success_url || config.success_url;

            if (!clientSecret) {
                throw new Error('Stripe client secret is missing');
            }

            const elements = this.stripe.elements({
                clientSecret,
                appearance: { theme: 'stripe' },
            });
            const paymentElement = elements.create('payment', {
                layout: 'tabs',
            });

            paymentElement.on('ready', () => {
                this.debug('inline_payment_element:ready', {
                    container: this.getContainerDebugSummary(detail.container),
                });
            });

            paymentElement.on('loaderror', (event) => {
                this.debug('inline_payment_element:loaderror', event);
            });

            paymentElement.mount(paymentElementContainer);
            this.debug('inline_payment_element:mount_called', {
                container: this.getContainerDebugSummary(detail.container),
            });

            submitBtn.addEventListener('click', async () => {
                this.debug('inline_payment_element:submit:start', {
                    orderId,
                    successUrl,
                });

                submitBtn.disabled = true;
                submitBtn.classList.add('processing');
                submitBtn.textContent = 'Processing...';
                messageEl.style.display = 'none';
                messageEl.textContent = '';

                try {
                    const { error: submitError } = await elements.submit();

                    if (submitError) {
                        throw submitError;
                    }

                    const { error, paymentIntent } = await this.stripe.confirmPayment({
                        elements,
                        clientSecret,
                        confirmParams: {
                            return_url: successUrl,
                        },
                        redirect: 'if_required',
                    });

                    if (error) {
                        throw error;
                    }

                    if (paymentIntent && (paymentIntent.status === 'succeeded' || paymentIntent.status === 'processing')) {
                        this.debug('inline_payment_element:payment_success', {
                            orderId,
                            paymentIntentId: paymentIntent.id,
                            status: paymentIntent.status,
                        });

                        await this.finalizeOrder(config, orderId, paymentIntent.id);
                        window.location.href = successUrl;
                        return;
                    }

                    throw new Error('Unexpected Stripe payment status: ' + (paymentIntent ? paymentIntent.status : 'unknown'));
                } catch (err) {
                    this.debug('inline_payment_element:submit:error', err);
                    messageEl.textContent = err.message || 'Payment failed. Please try again.';
                    messageEl.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('processing');
                    submitBtn.textContent = detail.label || 'Pay now';
                }
            });
        } catch (err) {
            this.debug('inline_payment_element:error', err);
            targetEl.innerHTML = '';
            this.mountedPaymentElementContainers.delete(mountKey);
            this.handleFallback(detail, 'inline_payment_element_exception', err);
        }
    }

    async getConfig() {
        const mainConfig = window.growtype_wc_ajax;
        const stripeConfig = mainConfig && mainConfig.stripe ? mainConfig.stripe : null;

        const config = {
            ajax_url: mainConfig ? mainConfig.url : '',
            nonce: mainConfig ? mainConfig.nonce : '',
            enabled: stripeConfig ? stripeConfig.enabled === true : false,
            publishable_key: stripeConfig ? stripeConfig.publishable_key : '',
            test_mode: stripeConfig ? stripeConfig.test_mode : false,
            success_url: stripeConfig ? stripeConfig.success_url : ''
        };

        return config;
    }

    async initStripe(config) {
        this.debug('init_stripe:start', this.getConfigDebugSummary(config));

        if (!config || !config.publishable_key) {
            this.debug('init_stripe:blocked_missing_publishable_key');
            console.error('GrowtypeWcStripeProvider: Publishable key is missing');
            return false;
        }

        if (!this.stripe) {
            if (typeof Stripe === 'undefined') {
                this.debug('init_stripe:stripe_global_missing_loading_script');
                try {
                    await this.loadStripeScript();
                } catch (e) {
                    this.debug('init_stripe:load_script_failed', e);
                    console.error('GrowtypeWcStripeProvider: Stripe.js not loaded', e);
                    return false;
                }
            }
            try {
                this.stripe = Stripe(config.publishable_key);
                this.debug('init_stripe:stripe_instance_created');
            } catch (e) {
                this.debug('init_stripe:stripe_instance_failed', e);
                console.error('GrowtypeWcStripeProvider: Stripe initialization failed', e);
                return false;
            }
        } else {
            this.debug('init_stripe:reuse_existing_instance');
        }
        this.debug('init_stripe:success');
        return true;
    }

    loadStripeScript() {
        if (typeof Stripe !== 'undefined') {
            this.debug('load_stripe_script:already_loaded');
            return Promise.resolve(true);
        }

        this.debug('load_stripe_script:append_script');
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://js.stripe.com/v3/';
            script.async = true;
            script.onload = () => {
                console.log('GrowtypeWcStripeProvider: Stripe.js loaded dynamically');
                resolve(true);
            };
            script.onerror = () => reject(new Error('Stripe.js failed to load.'));
            document.head.appendChild(script);
        });
    }

    async finalizeOrder(config, orderId, paymentIntentId) {
        this.debug('finalize_order:start', {
            orderId,
            paymentIntentId,
            hasAjaxUrl: !!(config && config.ajax_url),
            hasNonce: !!(config && config.nonce),
        });

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
        this.debug('fetch_payment_info:request', {
            productId,
            hasAjaxUrl: !!(config && config.ajax_url),
            hasNonce: !!(config && config.nonce),
        });

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

    async fetchIntent(config, productId, paymentMethodType = '', returnUrl = '') {
        this.debug('fetch_intent:request', {
            productId,
            paymentMethodType,
            returnUrl,
            hasAjaxUrl: !!(config && config.ajax_url),
            hasNonce: !!(config && config.nonce),
        });

        return jQuery.ajax({
            url: config.ajax_url,
            method: 'POST',
            data: {
                action: 'growtype_wc_create_payment_intent',
                product_id: productId,
                payment_method_type: paymentMethodType,
                return_url: returnUrl,
                nonce: config.nonce || ''
            }
        });
    }

    async handlePaymentRequest(detail) {
        console.log('GrowtypeWcStripeProvider: Handling manual request', detail);
        this.debug('manual_request:start', { detail });
        const config = await this.getConfig();
        this.debug('manual_request:config_resolved', this.getConfigDebugSummary(config));

        if (!config || !config.enabled) {
            this.debug('manual_request:blocked_stripe_disabled', this.getConfigDebugSummary(config));
            console.log('GrowtypeWcStripeProvider: Stripe is disabled, falling back.');
            this.handleFallback(detail, 'stripe_disabled_or_missing_config', this.getConfigDebugSummary(config));
            return;
        }

        if (!await this.initStripe(config)) {
            this.debug('manual_request:blocked_stripe_initialization_failed');
            this.handleFallback(detail, 'stripe_initialization_failed', this.getConfigDebugSummary(config));
            return;
        }

        try {
            const productId = detail.productId || this.getProductIdFromPage();
            this.debug('manual_request:product_resolved', {
                requestedProductId: detail.productId,
                resolvedProductId: productId,
            });
            const response = await this.fetchIntent(config, productId, '', detail.returnUrl || '');
            this.debug('manual_request:fetch_intent_response', this.getIntentResponseDebugSummary(response));

            if (!response.success) {
                this.debug('manual_request:blocked_intent_failed', response);
                throw new Error(response.data.message);
            }

            const { clientSecret, amount, currency, label, success_url } = response.data;
            const finalSuccessUrl = success_url || config.success_url;
            this.debug('manual_request:intent_parsed', {
                hasClientSecret: !!clientSecret,
                amount,
                currency,
                label,
                finalSuccessUrl,
            });

            const pr = this.stripe.paymentRequest({
                country: 'US',
                currency: currency || 'usd',
                total: { label: label || 'Total', amount: amount || 0 },
                requestPayerName: true,
                requestPayerEmail: true,
            });

            const result = await pr.canMakePayment();
            this.debug('manual_request:can_make_payment_result', result || null);
            if (result && (result.applePay || result.googlePay)) {
                pr.on('cancel', () => document.dispatchEvent(new CustomEvent('growtype_wc_payment_reset')));
                pr.on('paymentmethod', async (ev) => {
                    this.debug('manual_request:paymentmethod_event');
                    const { error } = await this.stripe.confirmPayment({
                        clientSecret,
                        confirmParams: { return_url: finalSuccessUrl },
                        redirect: 'if_required'
                    });
                    if (error) {
                        this.debug('manual_request:confirm_payment_error', error);
                        ev.complete('fail');
                        document.dispatchEvent(new CustomEvent('growtype_wc_payment_reset'));
                    } else {
                        this.debug('manual_request:confirm_payment_success');
                        ev.complete('success');
                        window.location.href = finalSuccessUrl;
                    }
                });
                await pr.show();
                this.debug('manual_request:payment_request_show_called');
            } else {
                this.handleFallback(detail, 'can_make_payment_returned_no_wallets', result || null);
            }
        } catch (err) {
            this.debug('manual_request:error', err);
            this.handleStripeError(err);
            console.error('GrowtypeWcStripeProvider Error:', err);
            this.handleFallback(detail, 'manual_request_exception', err);
        }
    }

    handleFallback(detail, reason = 'unspecified', context = {}) {
        this.debug('fallback:triggered', {
            reason,
            detail,
            context,
            container: this.getContainerDebugSummary(detail ? detail.container : ''),
        });
        console.info('GrowtypeWcStripeProvider: handleFallback triggered', detail);

        document.dispatchEvent(new CustomEvent('growtype_wc_payment_fallback', {
            detail: {
                container: detail.container,
                fallback: detail.fallback,
                reason,
                context,
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
        if (urlParams.has('add-to-cart')) {
            const productId = urlParams.get('add-to-cart');
            this.debug('get_product_id_from_page:from_url', { productId });
            return productId;
        }

        // 2. Global variable?
        if (window.growtype_wc_product_id) {
            this.debug('get_product_id_from_page:from_global', {
                productId: window.growtype_wc_product_id,
            });
            return window.growtype_wc_product_id;
        }

        this.debug('get_product_id_from_page:not_found');
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

export { GrowtypeWcStripeProvider };
