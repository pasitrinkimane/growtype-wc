/**
 * PayPal Payment Provider
 * Handles interactions with PayPal JS SDK for Smart Payment Buttons.
 */

class GrowtypeWcPaypalProvider {
    constructor() {
        this.sdkLoaded = false;
        console.log('[PayPal] GrowtypeWcPaypalProvider: initialized');
        this.init();
    }

    init() {
        document.addEventListener('growtype_wc_payment_request', (e) => {
            if (e.detail.provider === 'paypal') {
                console.log('[PayPal] Payment request received:', e.detail);
                if (e.detail.type === 'mount_express') {
                    this.mountExpressCheckout(e.detail);
                }
            }
        });
    }

    async getConfig() {
        const config = window.growtype_wc_ajax || window.growtype_wc_params || {};
        const paypalConfig = config.paypal || null;
        console.log('[PayPal] getConfig:', paypalConfig);
        return paypalConfig;
    }

    async loadPaypalSdk(config, requestedMethods = []) {
        if (this.sdkLoaded) {
            console.log('[PayPal] SDK already loaded, skipping.');
            return true;
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            const configAjax = window.growtype_wc_ajax || window.growtype_wc_params || {};
            const clientId = config.client_id;
            const currency = configAjax.currency || 'USD';
            const merchantId = config.merchant_id;

            const components = ['buttons'];
            const requested = requestedMethods.map(m => m.toLowerCase());
            if (requested.includes('applepay')) components.push('applepay');
            if (requested.includes('googlepay')) components.push('googlepay');

            let src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(clientId)}&currency=${encodeURIComponent(currency)}&components=${components.join(',')}`;

            if (merchantId) {
                src += `&merchant-id=${encodeURIComponent(merchantId)}`;
            } else {
                console.warn(
                    '[PayPal] merchant_id is not set. Google Pay confirmOrder will fail without it.\n' +
                    'Set the Merchant ID in WooCommerce → Settings → Payments → PayPal → ' +
                    (config.test_mode ? 'Merchant id - Test' : 'Merchant id - Live')
                );
            }

            console.log('[PayPal] Loading SDK:', src);

            script.src = src;
            script.async = true;
            script.onload = () => {
                this.sdkLoaded = true;
                console.log('[PayPal] SDK loaded successfully.');
                resolve(true);
            };
            script.onerror = () => {
                console.error('[PayPal] Failed to load SDK script:', src);
                reject(new Error('Failed to load PayPal SDK'));
            };
            document.head.appendChild(script);
        });
    }

    async mountExpressCheckout(detail) {
        console.log('[PayPal] mountExpressCheckout:', detail);
        const config = await this.getConfig();

        if (!config || !config.enabled) {
            console.warn('[PayPal] PayPal is disabled or config missing — falling back.');
            this.handleFallback(detail);
            return;
        }

        try {
            const requestedMethods = detail.method
                ? detail.method.split(',').map(m => m.trim().toLowerCase())
                : [];
            console.log('[PayPal] Requested methods:', requestedMethods);

            await this.loadPaypalSdk(config, requestedMethods);

            if (!window.paypal) {
                throw new Error('PayPal SDK not available after load');
            }

            const hasExpressMethods = requestedMethods.includes('applepay') || requestedMethods.includes('googlepay');

            let anyExpressMounted = false;

            if (hasExpressMethods) {
                console.log('[PayPal] Express methods requested — attempting to mount in parallel:', requestedMethods);

                // Run express method mounting in parallel and track which ones succeed
                const results = await Promise.all([
                    requestedMethods.includes('googlepay') && window.paypal.Googlepay
                        ? this.mountGooglePay(detail, config)
                        : Promise.resolve(false),
                    requestedMethods.includes('applepay') && window.paypal.Applepay
                        ? this.mountApplePay(detail, config)
                        : Promise.resolve(false),
                ]);

                anyExpressMounted = results.some(Boolean);
                console.log('[PayPal] Express mount results — googlePay:', results[0], '| applePay:', results[1], '| anyMounted:', anyExpressMounted);
            }

            // Render standard PayPal Buttons if:
            //  - No express methods were requested, OR
            //  - Express methods were requested but none successfully mounted (fallback)
            if (window.paypal.Buttons && (!hasExpressMethods || !anyExpressMounted)) {
                console.log('[PayPal] Rendering PayPal Buttons' + (hasExpressMethods ? ' (express fallback — no wallet available)' : '') + ' in:', detail.container);
                paypal.Buttons({
                    createOrder: () => {
                        console.log('[PayPal] Buttons: createOrder called, productId:', detail.productId);
                        return this.createOrder(detail.productId, 'paypal');
                    },
                    onApprove: (data) => {
                        console.log('[PayPal] Buttons: onApprove, orderID:', data.orderID);
                        return this.captureOrder(data.orderID, this.wcOrderId);
                    },
                    onCancel: () => {
                        console.log('[PayPal] Buttons: payment cancelled by user.');
                    },
                    onError: (err) => {
                        console.error('[PayPal] Buttons: SDK error:', err);
                    }
                }).render(detail.container);
            } else if (hasExpressMethods && anyExpressMounted) {
                console.log('[PayPal] Skipping standard PayPal Buttons — express method(s) successfully mounted.');
            }

            // Signal that we're ready
            console.log('[PayPal] Express checkout mounted, dispatching ready event.');
            document.dispatchEvent(new CustomEvent('growtype_wc_payment_express_ready', {
                detail: { container: detail.container }
            }));

        } catch (error) {
            console.error('[PayPal] Error in mountExpressCheckout:', error);
            this.handleFallback(detail);
        }
    }

    /**
     * Dynamically load the Google Pay JS library if not already present.
     */
    loadGooglePaySdk() {
        return new Promise((resolve, reject) => {
            if (typeof google !== 'undefined' && google.payments && google.payments.api) {
                console.log('[PayPal/GooglePay] Google Pay SDK already loaded.');
                resolve();
                return;
            }
            console.log('[PayPal/GooglePay] Loading Google Pay SDK from pay.google.com...');
            const script = document.createElement('script');
            script.src = 'https://pay.google.com/gp/p/js/pay.js';
            script.async = true;
            script.onload = () => {
                console.log('[PayPal/GooglePay] Google Pay SDK loaded successfully.');
                resolve();
            };
            script.onerror = () => {
                console.error('[PayPal/GooglePay] Failed to load Google Pay SDK.');
                reject(new Error('Failed to load Google Pay SDK'));
            };
            document.head.appendChild(script);
        });
    }

    async mountGooglePay(detail, config) {
        // Capture productId as integer immediately — close over it in onClick, not detail
        const productId = parseInt(detail.productId, 10) || 0;
        console.log('[PayPal/GooglePay] mountGooglePay — productId:', productId, '| detail:', detail);

        if (!productId) {
            console.error('[PayPal/GooglePay] No valid productId — cannot mount Google Pay button.');
            return false;
        }

        try {
            await this.loadGooglePaySdk();

            const googlepay = paypal.Googlepay();
            const configAjax = window.growtype_wc_ajax || window.growtype_wc_params || {};

            console.log('[PayPal/GooglePay] Fetching Google Pay config from PayPal...');
            let gpConfig;
            try {
                gpConfig = await googlepay.config();
                console.log('[PayPal/GooglePay] gpConfig received (full):', JSON.parse(JSON.stringify(gpConfig)));
            } catch (configErr) {
                console.warn('[PayPal/GooglePay] Google Pay config failed — not available for this merchant/account.', configErr);
                return false;
            }

            if (!gpConfig.isEligible) {
                console.warn('[PayPal/GooglePay] gpConfig.isEligible is false — Google Pay not supported for this merchant.');
                return false;
            }

            const { allowedPaymentMethods, merchantInfo, apiVersion, apiVersionMinor, countryCode: gpCountryCode } = gpConfig;
            console.log('[PayPal/GooglePay] allowedPaymentMethods[0] tokenizationSpecification:',
                JSON.parse(JSON.stringify(allowedPaymentMethods[0]?.tokenizationSpecification || {})));

            const environment = config.test_mode ? 'TEST' : 'PRODUCTION';
            console.log('[PayPal/GooglePay] Creating PaymentsClient, environment:', environment);

            const paymentsClient = new google.payments.api.PaymentsClient({ environment });

            console.log('[PayPal/GooglePay] Calling isReadyToPay...');
            const isReadyToPay = await paymentsClient.isReadyToPay({
                apiVersion,
                apiVersionMinor,
                allowedPaymentMethods,
            });

            console.log('[PayPal/GooglePay] isReadyToPay result:', isReadyToPay);

            if (!isReadyToPay.result) {
                console.warn('[PayPal/GooglePay] Device/browser not ready for Google Pay.');
                return false;
            }

            // Create a dedicated container for the Google Pay button
            const gpContainer = document.createElement('div');
            gpContainer.id = `paypal-google-pay-${Math.floor(Math.random() * 1000000)}`;
            gpContainer.className = 'paypal-google-pay-container';
            const parentEl = document.querySelector(detail.container);
            if (parentEl) {
                parentEl.appendChild(gpContainer);
                console.log('[PayPal/GooglePay] Container appended to DOM, id:', gpContainer.id);
            } else {
                console.error('[PayPal/GooglePay] Parent container not found in DOM:', detail.container);
                return false;
            }

            const button = paymentsClient.createButton({
                onClick: async () => {
                    console.log('[PayPal/GooglePay] Button clicked — productId:', productId);
                    let orderId = null;
                    try {
                        console.log('[PayPal/GooglePay] Step 1: Creating WC + PayPal order...');
                        orderId = await this.createOrder(productId, 'paypal');
                        console.log('[PayPal/GooglePay] Step 1 done — orderId:', orderId);

                        // Use gpConfig.countryCode as the authoritative value — it reflects the
                        // merchant's PayPal-registered country, which must match for confirmOrder.
                        const countryCode = gpCountryCode
                            || (configAjax.paypal && configAjax.paypal.country_code)
                            || 'US';

                        const paymentDataRequest = {
                            apiVersion,
                            apiVersionMinor,
                            allowedPaymentMethods,
                            merchantInfo,
                            transactionInfo: {
                                totalPriceStatus: 'FINAL',
                                totalPrice: this.orderAmount || '0.00',
                                currencyCode: this.orderCurrency || configAjax.currency || 'USD',
                                countryCode,
                            }
                        };

                        console.log('[PayPal/GooglePay] Step 2: paymentDataRequest:', JSON.parse(JSON.stringify(paymentDataRequest)));
                        const paymentData = await paymentsClient.loadPaymentData(paymentDataRequest);
                        console.log('[PayPal/GooglePay] Step 2 done — full paymentMethodData:',
                            JSON.parse(JSON.stringify(paymentData.paymentMethodData)));

                        console.log('[PayPal/GooglePay] Step 3: Confirming order with PayPal...');
                        const { status } = await googlepay.confirmOrder({
                            orderId,
                            paymentMethodData: paymentData.paymentMethodData
                        });
                        console.log('[PayPal/GooglePay] Step 3 done — confirmOrder status:', status);

                        if (status === 'APPROVED') {
                            console.log('[PayPal/GooglePay] Step 4: Capturing order...');
                            await this.captureOrder(orderId, this.wcOrderId);
                        } else {
                            console.warn('[PayPal/GooglePay] Order not APPROVED, status:', status, '— redirecting to PayPal.');
                            this._redirectToPaypal(detail, orderId);
                        }
                    } catch (clickErr) {
                        // Google Pay sheet dismissed / cancelled by the user — do nothing.
                        // loadPaymentData rejects with statusCode 'CANCELED' when the user
                        // closes the sheet without completing payment.
                        if (clickErr?.statusCode === 'CANCELED') {
                            console.log('[PayPal/GooglePay] User cancelled Google Pay sheet — no action taken.');
                            return;
                        }

                        const isSandboxValidationError = clickErr?.message === 'APPROVE_GOOGLE_PAY_VALIDATION_ERROR';

                        if (isSandboxValidationError && config.test_mode) {
                            console.warn(
                                '%c[PayPal/GooglePay] ⚠️ SANDBOX LIMITATION DETECTED',
                                'color: orange; font-weight: bold; font-size: 14px;'
                            );
                            console.warn(
                                '[PayPal/GooglePay] APPROVE_GOOGLE_PAY_VALIDATION_ERROR\n\n' +
                                'This is a KNOWN PayPal sandbox limitation — Google Pay cannot\n' +
                                'process real tokens on non-production domains (localhost / test).\n\n' +
                                '✅ This WILL work correctly in production.\n' +
                                '➡️  Falling back to PayPal standard checkout for sandbox testing.\n' +
                                'PayPal Debug ID: ' + (clickErr?.paypalDebugId || 'n/a')
                            );
                        } else {
                            console.error('[PayPal/GooglePay] ❌ Payment error:', clickErr?.message, clickErr);
                        }

                        console.log('[PayPal/GooglePay] Redirecting to PayPal fallback — orderId:', orderId);

                        this._redirectToPaypal(detail, orderId);
                    }
                }
            });

            document.getElementById(gpContainer.id)?.appendChild(button);
            console.log('[PayPal/GooglePay] Google Pay button mounted successfully.');
            return true;

        } catch (err) {
            console.error('[PayPal/GooglePay] Google Pay mount error:', err);
            return false;
        }
    }

    async mountApplePay(detail, config) {
        console.log('[PayPal/ApplePay] mountApplePay called — detail:', detail);

        // Apple Pay is only available in Safari / WebKit environments
        if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
            console.log('[PayPal/ApplePay] ApplePaySession not available — not Safari or no cards.');
            return false;
        }

        try {
            const applepay = paypal.Applepay();
            const configAjax = window.growtype_wc_ajax || window.growtype_wc_params || {};

            console.log('[PayPal/ApplePay] Fetching Apple Pay config...');
            const apConfig = await applepay.config();
            console.log('[PayPal/ApplePay] config response:', apConfig);

            if (!apConfig.isEligible) {
                console.log('[PayPal/ApplePay] Not eligible on this device/browser.');
                return false;
            }

            // ── Render the Apple Pay button ───────────────────────────────────
            const apContainer = document.createElement('div');
            apContainer.className = 'paypal-apple-pay-container';

            const apBtn = document.createElement('apple-pay-button');
            apBtn.setAttribute('buttonstyle', 'black');
            apBtn.setAttribute('type', 'buy');
            apBtn.setAttribute('locale', document.documentElement.lang || 'en');
            apContainer.appendChild(apBtn);

            const parentEl = document.querySelector(detail.container);
            if (!parentEl) {
                console.error('[PayPal/ApplePay] Parent container not found:', detail.container);
                return false;
            }
            parentEl.appendChild(apContainer);
            console.log('[PayPal/ApplePay] Apple Pay button mounted.');

            // ── Wire up click → ApplePaySession ──────────────────────────────
            apBtn.addEventListener('click', async () => {
                console.log('[PayPal/ApplePay] Button clicked — productId:', detail.productId);

                try {
                    // Step 1: create PayPal + WC order to get the amount
                    console.log('[PayPal/ApplePay] Step 1: Creating order...');
                    let orderId = null;
                    orderId = await this.createOrder(detail.productId, 'paypal');
                    console.log('[PayPal/ApplePay] Step 1 done — orderId:', orderId, '| amount:', this.orderAmount);

                    const paymentRequest = {
                        countryCode: apConfig.countryCode || (configAjax.paypal && configAjax.paypal.country_code) || 'US',
                        currencyCode: this.orderCurrency || configAjax.currency || 'USD',
                        merchantCapabilities: apConfig.merchantCapabilities || ['supports3DS'],
                        supportedNetworks: apConfig.supportedNetworks || ['visa', 'masterCard', 'amex', 'discover'],
                        total: {
                            label: configAjax.shop_name || 'Total',
                            amount: this.orderAmount || '0.00',
                            type: 'final',
                        },
                    };

                    console.log('[PayPal/ApplePay] Step 2: Starting ApplePaySession...', paymentRequest);
                    const session = new ApplePaySession(4, paymentRequest);

                    // Step 2a: validate merchant with PayPal
                    session.onvalidatemerchant = async (event) => {
                        console.log('[PayPal/ApplePay] onvalidatemerchant — validationURL:', event.validationURL);
                        try {
                            const validationData = await applepay.validateMerchant({
                                validationUrl: event.validationURL,
                                displayName: configAjax.shop_name || 'Store',
                            });
                            console.log('[PayPal/ApplePay] Merchant validated.');
                            session.completeMerchantValidation(validationData.merchantSession);
                        } catch (err) {
                            console.error('[PayPal/ApplePay] Merchant validation failed:', err);
                            session.abort();
                        }
                    };

                    // Step 2b: user authorised payment
                    session.onpaymentauthorized = async (event) => {
                        console.log('[PayPal/ApplePay] onpaymentauthorized — token received.');
                        try {
                            console.log('[PayPal/ApplePay] Step 3: Confirming order with PayPal...');
                            const { status } = await applepay.confirmOrder({
                                orderId,
                                token: event.payment.token,
                                billingContact: event.payment.billingContact,
                                shippingContact: event.payment.shippingContact,
                            });
                            console.log('[PayPal/ApplePay] Step 3 done — status:', status);

                            if (status === 'APPROVED') {
                                session.completePayment(ApplePaySession.STATUS_SUCCESS);
                                console.log('[PayPal/ApplePay] Step 4: Capturing order...');
                                await this.captureOrder(orderId, this.wcOrderId);
                            } else {
                                session.completePayment(ApplePaySession.STATUS_FAILURE);
                                console.warn('[PayPal/ApplePay] Order not APPROVED, status:', status, '— redirecting to PayPal.');
                                this._redirectToPaypal(detail, orderId);
                            }
                        } catch (err) {
                            console.error('[PayPal/ApplePay] confirmOrder failed — redirecting to PayPal:', err);
                            session.completePayment(ApplePaySession.STATUS_FAILURE);
                            this._redirectToPaypal(detail, orderId);
                        }
                    };

                    session.oncancel = () => {
                        console.log('[PayPal/ApplePay] Session cancelled by user — redirecting to PayPal.');
                        this._redirectToPaypal(detail, orderId);
                    };

                    session.begin();

                } catch (clickErr) {
                    console.error('[PayPal/ApplePay] Payment error during onClick:', clickErr);
                    this._redirectToPaypal(detail, null);
                }
            });

            return true;

        } catch (err) {
            console.error('[PayPal/ApplePay] Apple Pay mount error:', err);
            return false;
        }
    }

    /**
     * Redirect the user to PayPal checkout as a fallback when an express
     * wallet payment (Google Pay / Apple Pay) fails or is cancelled.
     *
     * Priority:
     *   1. orderId present  — direct PayPal checkoutnow (existing order already created)
     *   2. detail.fallback  — WC checkout URL (no orderId; creates fresh order)
     *   3. window.reload    — absolute last resort
     */
    _redirectToPaypal(detail, orderId = null) {
        const configAjax = window.growtype_wc_ajax || {};
        const isSandbox = configAjax.paypal?.test_mode;

        console.log('[PayPal] _redirectToPaypal called — orderId:', orderId, '| detail.fallback:', detail?.fallback, '| isSandbox:', isSandbox);

        // Priority 1: Direct PayPal redirect with existing orderId.
        // The order was already created in Step 1 — send the user straight to PayPal to complete it.
        if (orderId) {
            const base = isSandbox
                ? 'https://www.sandbox.paypal.com'
                : 'https://www.paypal.com';
            const url = `${base}/checkoutnow?token=${encodeURIComponent(orderId)}`;
            console.log('[PayPal] Redirecting to PayPal checkout (existing order):', url);
            window.location.href = url;
            return;
        }

        // Priority 2: WC checkout fallback — no orderId yet; creates a fresh order.
        const fallback = detail?.fallback;
        if (fallback) {
            this._redirectToFallback(fallback);
            return;
        }

        console.warn('[PayPal] No orderId or fallback — reloading current page.');
        window.location.reload();
    }

    /**
     * Redirect to the WC checkout fallback URL (vault-enabled fresh order flow).
     * Use this when you want to go directly to the fallback without the full
     * PayPal redirect priority chain.
     */
    _redirectToFallback(url) {
        console.log('[PayPal] Redirecting via WC checkout fallback (vault-enabled):', url);
        window.location.href = url;
    }

    async createOrder(productId, vaultSource = 'card') {
        const parsedId = parseInt(productId, 10) || 0;
        console.log('[PayPal] createOrder — productId:', parsedId, '(raw:', productId, ') | vaultSource:', vaultSource);

        if (!parsedId) {
            throw new Error('[PayPal] createOrder: productId is missing or invalid');
        }

        const config = await this.getConfig();
        const configAjax = window.growtype_wc_ajax || window.growtype_wc_params || {};

        console.log('[PayPal] createOrder — POSTing to:', configAjax.url, '| nonce set:', !!config.nonce);

        const response = await jQuery.ajax({
            url: configAjax.url,
            method: 'POST',
            data: {
                action: 'gwc_paypal_hosted_create_order',
                _ajax_nonce: config.nonce,
                product_id: parsedId,
                vault_source: vaultSource
            }
        });

        console.log('[PayPal] createOrder response:', response);

        if (response.success && response.data.orderID) {
            this.wcOrderId = response.data.wc_order_id;
            this.orderAmount = response.data.amount || '0.00';
            this.orderCurrency = response.data.currency_code || configAjax.currency || 'USD';
            console.log('[PayPal] Order created — orderID:', response.data.orderID,
                '| wcOrderId:', this.wcOrderId,
                '| amount:', this.orderAmount,
                '| currency:', this.orderCurrency);
            return response.data.orderID;
        } else {
            throw new Error(response.data.message || 'Failed to create PayPal order');
        }
    }

    async captureOrder(orderId, wcOrderId) {
        console.log('[PayPal] captureOrder — orderId:', orderId, '| wcOrderId:', wcOrderId);
        this.showLoader();

        const config = await this.getConfig();
        const configAjax = window.growtype_wc_ajax || window.growtype_wc_params || {};

        try {
            console.log('[PayPal] captureOrder — POSTing to:', configAjax.url);
            const response = await jQuery.ajax({
                url: configAjax.url,
                method: 'POST',
                data: {
                    action: 'gwc_paypal_hosted_capture_order',
                    _ajax_nonce: config.nonce,
                    paypal_order_id: orderId,
                    wc_order_id: wcOrderId
                }
            });

            console.log('[PayPal] captureOrder response:', response);

            if (response.success && response.data.redirect) {
                console.log('[PayPal] Capture successful — redirecting to:', response.data.redirect);
                window.location.href = response.data.redirect;
            } else {
                throw new Error(response.data.message || 'Payment capture failed');
            }
        } catch (error) {
            console.error('[PayPal] captureOrder error:', error);
            this.hideLoader();
            alert(error.message || 'Payment capture failed. Please try again.');
        }
    }

    handleFallback(detail) {
        console.log('[PayPal] handleFallback — container:', detail.container, '| fallback:', detail.fallback);
        document.dispatchEvent(new CustomEvent('growtype_wc_payment_fallback', {
            detail: {
                container: detail.container,
                fallback: detail.fallback
            }
        }));
    }

    showLoader() {
        if (document.getElementById('growtype-wc-payment-loader')) return;
        const loader = document.createElement('div');
        loader.id = 'growtype-wc-payment-loader';
        loader.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); z-index: 100000;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: #fff; font-family: -apple-system, system-ui, sans-serif;
            transition: opacity 0.3s;
        `;
        loader.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; gap: 20px;">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Processing...</span>
            </div>
            <div style="font-family: inherit; font-weight: 600; color: #fff;">Processing payment...</div>
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
}

function growtypeWcPaypalProvider() {
    new GrowtypeWcPaypalProvider();
}

export { growtypeWcPaypalProvider };
