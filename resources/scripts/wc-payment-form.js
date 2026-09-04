/**
 * wc-payment-form.js
 *
 * Handles rendering the GWC payment form in any context:
 *
 *  1. Static embed  — <div class="gwc-payment-form-mount" data-product-id="...">
 *     Boots on DOMContentLoaded. No JS needed in the template.
 *
 *  2. Checkout button — .growtype-wc-checkout-button[data-product-id]
 *     On click: fetches the form HTML via AJAX, injects it into the modal,
 *     then dispatches the selected provider mount event.
 *
 *  3. Dynamic / AJAX — mount points added after page load are caught by
 *     MutationObserver and booted automatically.
 *
 *  4. AJAX context  — if window.growtype_wc_ajax is missing, fetches config
 *     from the REST endpoint before booting.
 */

import {
    debugPaymentForm,
    getContainerDebugSummary,
    isPaymentFormDebugEnabled,
    setPaymentFormDebug,
} from './util/payment-form-debug';

(function () {
    'use strict';

    // ── Config ────────────────────────────────────────────────────────────────

    var CONFIG_ENDPOINT = '/wp-json/gwc/v1/payment-config';
    var MOUNT_CLASS = 'gwc-payment-form-mount';
    var BOOTED_ATTR = 'data-gwc-booted';
    var BTN_CLASS = 'growtype-wc-checkout-button';

    function normalizeBoolean(value) {
        return ['yes', '1', 'true'].indexOf(String(value || '').toLowerCase()) !== -1;
    }

    function normalizeProviderFromPaymentMethod(paymentMethod) {
        var provider = String(paymentMethod || '').toLowerCase();

        if (provider.indexOf('gwc-') === 0) {
            provider = provider.slice(4);
        }

        if (provider.indexOf('growtype_wc_') === 0) {
            provider = provider.slice(12);
        }

        return provider.replace(/_/g, '-');
    }

    function getProviderConfig(provider) {
        var config = window.growtype_wc_ajax || {};
        return config && config[provider] ? config[provider] : {};
    }

    function getDefaultPaymentFormAction(provider) {
        var actions = {
            stripe: 'gwc_stripe_payment_form',
            paypal: 'gwc_payment_form',
        };

        return actions[provider] || '';
    }

    function getPaymentFormNonce(provider, explicitNonce) {
        if (explicitNonce) {
            return explicitNonce;
        }

        var providerConfig = getProviderConfig(provider);
        if (providerConfig.payment_form_nonce) {
            return providerConfig.payment_form_nonce;
        }

        if (providerConfig.nonce) {
            return providerConfig.nonce;
        }

        return window.growtype_wc_ajax && window.growtype_wc_ajax.nonce
            ? window.growtype_wc_ajax.nonce
            : '';
    }

    function getUrlParam(url, key) {
        try {
            return new URL(url, window.location.href).searchParams.get(key) || '';
        } catch (err) {
            return '';
        }
    }

    function isPaypalAddToCartLink(el) {
        if (!el || !el.matches || !el.matches('a.btn-addtocart[href]')) {
            return false;
        }

        return getUrlParam(el.getAttribute('href'), 'payment_method') === 'gwc-paypal';
    }

    // ── Config resolution ─────────────────────────────────────────────────────

    var _configPromise = null;

    /**
     * Show a loader spinner inside a container.
     */
    function showSpinner(container) {
        debugPaymentForm('show_spinner:start', {
            container: getContainerDebugSummary(container),
        });

        if (!container) return null;
        var loader = container.querySelector('.gwc-payment-form-mainloader');
        if (loader) {
            debugPaymentForm('show_spinner:reuse_existing_loader', {
                loader: getContainerDebugSummary(loader),
            });
            return loader;
        }

        loader = document.createElement('div');
        loader.className = 'gwc-payment-form-mainloader';
        loader.innerHTML = '<div class="gwc-hf-spinner"></div>';
        container.appendChild(loader);
        debugPaymentForm('show_spinner:created', {
            loader: getContainerDebugSummary(loader),
            container: getContainerDebugSummary(container),
        });
        return loader;
    }

    /**
     * Hide/remove a loader spinner inside a container or the loader element itself.
     */
    function hideSpinner(loaderOrContainer) {
        debugPaymentForm('hide_spinner:start', {
            target: getContainerDebugSummary(loaderOrContainer),
        });

        if (!loaderOrContainer) return;
        var loader = (loaderOrContainer.classList && loaderOrContainer.classList.contains('gwc-payment-form-mainloader'))
            ? loaderOrContainer
            : loaderOrContainer.querySelector('.gwc-payment-form-mainloader');

        if (loader) {
            loader.remove();
            debugPaymentForm('hide_spinner:removed');
        } else {
            debugPaymentForm('hide_spinner:not_found');
        }
    }

    /**
     * Remove the processing state from all payment/add-to-cart buttons.
     */
    function resetProcessingButtons() {
        debugPaymentForm('reset_processing_buttons:start');

        var processingBtns = document.querySelectorAll('.processing');
        Array.prototype.forEach.call(processingBtns, function (btn) {
            btn.classList.remove('processing');
        });
        if (window.jQuery) {
            window.jQuery('.processing').removeClass('processing');
        }
    }

    function renderFallbackCta(detail) {
        if (!detail || !detail.container || !detail.fallback) {
            debugPaymentForm('fallback_cta:blocked_missing_detail_or_fallback', {
                detail: detail,
            });
            return;
        }

        var container = document.querySelector(detail.container);

        if (!container) {
            debugPaymentForm('fallback_cta:blocked_missing_container', {
                detail: detail,
            });
            return;
        }

        var wrapper = container.closest('.gwc-payment-form') || container;
        wrapper.innerHTML = '';

        var link = document.createElement('a');
        link.href = detail.fallback;
        link.className = 'btn btn-primary w-100 gwc-payment-form-fallback-cta';
        link.style.cssText = 'min-height:48px;display:flex;align-items:center;justify-content:center;font-weight:700;';
        link.textContent = detail.label || 'Continue to secure checkout';

        var note = document.createElement('div');
        note.className = 'gwc-payment-form-fallback-note';
        note.style.cssText = 'margin-top:12px;text-align:center;font-size:13px;color:#667085;';
        note.textContent = 'Wallet checkout is unavailable in this browser. Continue with secure checkout instead.';

        wrapper.appendChild(link);
        wrapper.appendChild(note);
        debugPaymentForm('fallback_cta:rendered', {
            detail: detail,
            wrapper: getContainerDebugSummary(wrapper),
        });
    }

    document.addEventListener('growtype_wc_payment_express_ready', function (e) {
        var selector = e.detail && e.detail.container ? e.detail.container : '';
        debugPaymentForm('payment_event:express_ready', {
            detail: e.detail,
            container: selector ? getContainerDebugSummary(document.querySelector(selector)) : null,
        });
    });

    document.addEventListener('growtype_wc_payment_fallback', function (e) {
        var selector = e.detail && e.detail.container ? e.detail.container : '';
        debugPaymentForm('payment_event:fallback', {
            detail: e.detail,
            container: selector ? getContainerDebugSummary(document.querySelector(selector)) : null,
        });
        renderFallbackCta(e.detail);
    });

    document.addEventListener('growtype_wc_payment_reset', function (e) {
        debugPaymentForm('payment_event:reset', {
            detail: e.detail,
        });
    });

    /**
     * Bind close triggers and hidden state resets to the modal.
     */
    function bindModalEvents(modal) {
        if (modal.getAttribute('data-gwc-bound')) {
            debugPaymentForm('bind_modal_events:already_bound', {
                modal: getContainerDebugSummary(modal),
            });
            return;
        }
        debugPaymentForm('bind_modal_events:binding', {
            modal: getContainerDebugSummary(modal),
        });
        modal.setAttribute('data-gwc-bound', '1');

        // Bind close button
        var closeBtn = modal.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (window.bootstrap && window.bootstrap.Modal) {
                    var inst = window.bootstrap.Modal.getInstance(modal);
                    if (inst) inst.hide();
                } else if (window.jQuery && window.jQuery.fn.modal) {
                    window.jQuery(modal).modal('hide');
                } else {
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                    var backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }
                resetProcessingButtons();
            });
        }

        // Reset content when hidden
        modal.addEventListener('hidden.bs.modal', function () {
            debugPaymentForm('modal:hidden_reset_content', {
                modal: getContainerDebugSummary(modal),
            });

            var modalBody = modal.querySelector('.modal-body-payment-form');
            if (modalBody) {
                modalBody.innerHTML = '';
            }
            resetProcessingButtons();
        });
    }

    /**
     * Get the server-rendered payment form modal, or fetch its HTML from
     * Growtype_Wc_Payment_Form_Modal::ajax_render() when not in the DOM.
     * Returns a Promise that resolves with the modal element.
     */
    function getOrCreatePaymentFormModal() {
        var modalId = 'gwcPaymentFormModal';
        var modal = document.getElementById(modalId);
        if (modal) {
            debugPaymentForm('modal:existing_found', {
                modal: getContainerDebugSummary(modal),
            });
            bindModalEvents(modal);
            return Promise.resolve(modal);
        }

        debugPaymentForm('modal:not_found_fetching_shell');

        // Modal was not server-rendered — fetch the shell HTML from PHP.
        return resolveConfig().then(function () {
            var ajaxUrl = (window.growtype_wc_ajax && window.growtype_wc_ajax.url)
                ? window.growtype_wc_ajax.url
                : '/wp-admin/admin-ajax.php';

            debugPaymentForm('modal:shell_fetch:start', {
                ajaxUrl: ajaxUrl,
            });

            return fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'gwc_payment_form_modal' }).toString(),
            });
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            debugPaymentForm('modal:shell_fetch:response', {
                success: !!(res && res.success),
                dataKeys: res && res.data ? Object.keys(res.data) : [],
            });

            if (!res.success) {
                throw new Error('[wc-payment-form] gwc_payment_form_modal AJAX error');
            }

            var tmp = document.createElement('div');
            tmp.innerHTML = res.data.html;
            modal = tmp.firstElementChild;

            document.body.appendChild(modal);
            bindModalEvents(modal);
            debugPaymentForm('modal:shell_appended', {
                modal: getContainerDebugSummary(modal),
            });

            return modal;
        });
    }

    /**
     * Show the Bootstrap modal.
     */
    function showPaymentFormModal(modal) {
        debugPaymentForm('modal:show:start', {
            modal: getContainerDebugSummary(modal),
            hasBootstrap: !!(window.bootstrap && window.bootstrap.Modal),
            hasJqueryModal: !!(window.jQuery && window.jQuery.fn.modal),
        });

        if (window.bootstrap && window.bootstrap.Modal) {
            var bsModal = window.bootstrap.Modal.getInstance(modal) || new window.bootstrap.Modal(modal);
            bsModal.show();
            debugPaymentForm('modal:show:bootstrap');
        } else if (window.jQuery && window.jQuery.fn.modal) {
            window.jQuery(modal).modal('show');
            debugPaymentForm('modal:show:jquery');
        } else {
            modal.style.display = 'block';
            modal.classList.add('show');
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
            debugPaymentForm('modal:show:fallback_dom');
        }
    }

    /**
     * Returns a Promise that resolves with window.growtype_wc_ajax.
     * Fetches from REST if not already present.
     */
    function resolveConfig() {
        if (window.growtype_wc_ajax) {
            debugPaymentForm('config:existing_window_config', {
                hasUrl: !!window.growtype_wc_ajax.url,
                hasNonce: !!window.growtype_wc_ajax.nonce,
                hasStripe: !!window.growtype_wc_ajax.stripe,
                hasPaypal: !!window.growtype_wc_ajax.paypal,
            });
            return Promise.resolve(window.growtype_wc_ajax);
        }
        if (_configPromise) {
            debugPaymentForm('config:reuse_pending_request');
            return _configPromise;
        }
        debugPaymentForm('config:fetch:start', {
            endpoint: CONFIG_ENDPOINT,
        });
        _configPromise = fetch(CONFIG_ENDPOINT)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                window.growtype_wc_ajax = data;
                debugPaymentForm('config:fetch:success', {
                    hasUrl: !!data.url,
                    hasNonce: !!data.nonce,
                    hasStripe: !!data.stripe,
                    hasPaypal: !!data.paypal,
                });
                return data;
            })
            .catch(function (err) {
                debugPaymentForm('config:fetch:error', err);
                console.error('[wc-payment-form] Failed to fetch config:', err);
                return {};
            });
        return _configPromise;
    }

    // ── Mount point initializer ───────────────────────────────────────────────

    /**
     * Boot all unbooted .gwc-payment-form-mount elements inside `root`.
     */
    function initMounts(root) {
        var selector = '.' + MOUNT_CLASS + ':not([' + BOOTED_ATTR + '])';
        var mounts = root.querySelectorAll ? root.querySelectorAll(selector) : [];
        debugPaymentForm('init_mounts:start', {
            selector: selector,
            root: getContainerDebugSummary(root === document ? document.documentElement : root),
            found: mounts.length,
        });

        Array.prototype.forEach.call(mounts, function (el) {
            el.setAttribute(BOOTED_ATTR, '1');

            var productId = el.getAttribute('data-product-id') || '0';
            var methods = el.getAttribute('data-methods') || 'applepay,googlepay,paypal';
            var returnUrl = el.getAttribute('data-return-url') || '';
            var provider = (el.getAttribute('data-provider') || 'paypal').toLowerCase();
            var containerId = MOUNT_CLASS + '-' + productId + '-' + Math.random().toString(36).slice(2, 7);
            debugPaymentForm('init_mounts:boot_mount', {
                productId: productId,
                methods: methods,
                returnUrl: returnUrl,
                provider: provider,
                originalElement: getContainerDebugSummary(el),
                containerId: containerId,
            });

            // Promote the placeholder to an express container
            el.id = containerId;
            el.className = 'gwc-payment-form__express';
            el.setAttribute('data-method', methods);
            el.style.minHeight = '48px';
            debugPaymentForm('init_mounts:promoted_placeholder', {
                element: getContainerDebugSummary(el),
            });

            resolveConfig().then(function () {
                debugPaymentForm('init_mounts:dispatch_mount_express', {
                    provider: provider,
                    container: '#' + containerId,
                    productId: parseInt(productId, 10),
                    methods: methods,
                    returnUrl: returnUrl,
                });

                document.dispatchEvent(new CustomEvent('growtype_wc_payment_request', {
                    detail: {
                        provider: provider,
                        type: 'mount_express',
                        container: '#' + containerId,
                        productId: parseInt(productId, 10),
                        method: methods,
                        returnUrl: returnUrl,
                    }
                }));
            });
        });
    }

    // ── Checkout button → payment form transition ─────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.' + BTN_CLASS + '[data-product-id], .btn-show-paypal-card[data-product-id], a.btn-addtocart[href*="payment_method=gwc-paypal"], a.woocommerce-button.pay[href*="gwc_product_id="]');
        if (!btn) return;

        var isPaypalAddToCart = isPaypalAddToCartLink(btn);
        var isOrderPay = btn.matches('a.woocommerce-button.pay[href*="gwc_product_id="]');

        if (isPaypalAddToCart || isOrderPay) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }

        debugPaymentForm('checkout_click:matched_button', {
            button: getContainerDebugSummary(btn),
            productId: btn.getAttribute('data-product-id'),
            primaryPaymentMethod: btn.getAttribute('data-primary-payment-method'),
            methods: btn.getAttribute('data-methods'),
            isPaypalAddToCart: isPaypalAddToCart,
            isOrderPay: isOrderPay,
            hasInstantChargeUrl: !!btn.getAttribute('data-instant-charge-url'),
            instantCharge: btn.getAttribute('data-instant-charge'),
        });

        var paymentFormMode = (btn.getAttribute('data-payment-form-mode') || ((isPaypalAddToCart || isOrderPay) ? 'form' : '')).toLowerCase();
        var expressShowForm = (btn.getAttribute('data-express-show-form') || btn.getAttribute('data-stripe-express-show-form') || 'no').toLowerCase();
        var shouldShowPaymentForm = paymentFormMode
            ? paymentFormMode === 'form'
            : normalizeBoolean(expressShowForm);
        var instantUrl = btn.getAttribute('data-instant-charge-url');
        if (instantUrl && !shouldShowPaymentForm) {
            debugPaymentForm('checkout_click:instant_charge_redirect', {
                instantUrl: instantUrl,
                paymentFormMode: paymentFormMode,
                shouldShowPaymentForm: shouldShowPaymentForm,
            });
            e.preventDefault();
            btn.classList.add('processing');
            btn.disabled = true;
            if (!document.getElementById('gwc-spinner-style')) {
                var style = document.createElement('style');
                style.id = 'gwc-spinner-style';
                style.innerHTML = '@keyframes gwc-spin { to { transform: rotate(360deg); } }';
                document.head.appendChild(style);
            }
            btn.innerHTML = '<span class="gwc-hf-spinner" style="display:inline-block; vertical-align:middle; margin-right:8px; width:18px; height:18px; border:2px solid currentColor; border-top-color:transparent; border-radius:50%; animation:gwc-spin 0.6s linear infinite;"></span> Processing...';
            window.location.href = instantUrl;
            return;
        }

        if (btn.getAttribute('data-instant-charge') === '1' && !shouldShowPaymentForm) return;
        e.preventDefault();

        var productId = btn.getAttribute('data-product-id')
            || getUrlParam(btn.getAttribute('href'), 'add-to-cart')
            || getUrlParam(btn.getAttribute('href'), 'gwc_product_id');
        if (!productId) {
            debugPaymentForm('checkout_click:blocked_missing_product_id', {
                button: getContainerDebugSummary(btn),
                href: btn.getAttribute('href') || '',
            });
            resetProcessingButtons();
            return;
        }

        var methods = btn.getAttribute('data-methods') || ((isPaypalAddToCart || isOrderPay) ? 'applepay,googlepay,paypal' : 'applepay,googlepay');
        var returnUrl = btn.getAttribute('data-return-url') || '';
        var fallbackUrl = btn.getAttribute('data-fallback') || btn.getAttribute('href') || '';
        var buttonLabel = (btn.textContent || '').trim();
        var primaryMethod = (btn.getAttribute('data-primary-payment-method') || ((isPaypalAddToCart || isOrderPay) ? 'gwc-paypal' : '')).toLowerCase();
        var provider = (btn.getAttribute('data-provider') || ((isPaypalAddToCart || isOrderPay) ? 'paypal' : '') || normalizeProviderFromPaymentMethod(primaryMethod) || 'paypal').toLowerCase();
        var paymentFormAction = btn.getAttribute('data-payment-form-action') || getDefaultPaymentFormAction(provider);
        var paymentFormNonce = btn.getAttribute('data-payment-form-nonce') || '';

        debugPaymentForm('checkout_click:provider_resolved', {
            productId: productId,
            methods: methods,
            returnUrl: returnUrl,
            fallbackUrl: fallbackUrl,
            buttonLabel: buttonLabel,
            paymentFormMode: paymentFormMode,
            expressShowForm: expressShowForm,
            shouldShowPaymentForm: shouldShowPaymentForm,
            primaryMethod: primaryMethod,
            provider: provider,
            paymentFormAction: paymentFormAction,
            hasPaymentFormNonce: !!paymentFormNonce,
        });

        if (!shouldShowPaymentForm) {
            debugPaymentForm('checkout_click:redirecting_payment_form_disabled', {
                provider: provider,
                primaryMethod: primaryMethod,
                fallbackUrl: fallbackUrl,
            });

            if (fallbackUrl) {
                window.location.href = fallbackUrl;
            } else {
                resetProcessingButtons();
            }

            return;
        }
        
        var modalBody = btn.closest('.modal-body');

        // Detect if we are inside a non-payment modal (like Starter Pack modal)
        var existingModal = btn.closest('.modal');
        if (existingModal && existingModal.id !== 'gwcPaymentFormModal') {
            debugPaymentForm('checkout_click:inside_non_payment_modal', {
                modal: getContainerDebugSummary(existingModal),
            });
            modalBody = null; // force new modal flow

            if (window.bootstrap && window.bootstrap.Modal) {
                var inst = window.bootstrap.Modal.getInstance(existingModal);
                if (inst) inst.hide();
            } else if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery(existingModal).modal('hide');
            } else {
                existingModal.classList.remove('show');
                existingModal.style.display = 'none';
                var backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.remove();
            }
        }

        if (!modalBody) {
            debugPaymentForm('checkout_click:using_new_payment_modal_flow');
            getOrCreatePaymentFormModal().then(function (modal) {
                var body = modal.querySelector('.modal-body-payment-form');
                if (!body) {
                    debugPaymentForm('checkout_click:blocked_missing_modal_body_payment_form', {
                        modal: getContainerDebugSummary(modal),
                    });
                    return;
                }
                body.innerHTML = '';
                continueFlow(body, true, modal, existingModal);
            }).catch(function (err) {
                debugPaymentForm('checkout_click:modal_flow_error', err);
                console.error('[wc-payment-form] Could not get modal:', err);
                resetProcessingButtons();
            });
            return;
        }

        debugPaymentForm('checkout_click:using_existing_modal_body_flow', {
            modalBody: getContainerDebugSummary(modalBody),
        });
        continueFlow(modalBody, false, null, existingModal);

        function continueFlow(modalBody, isNewModal, modal, fromModal) {
            debugPaymentForm('continue_flow:start', {
                isNewModal: isNewModal,
                modalBody: getContainerDebugSummary(modalBody),
                modal: getContainerDebugSummary(modal),
                fromModal: getContainerDebugSummary(fromModal),
            });

            var loader;
            var modalEl = modal || document.getElementById('gwcPaymentFormModal');
            if (modalEl) {
                var modalTitle = modalEl.querySelector('.modal-title');
                if (modalTitle) {
                    modalTitle.innerHTML = 'Pay with Card';
                }
            }

            function fetchForm() {
                var ajaxUrl = (window.growtype_wc_ajax && window.growtype_wc_ajax.url)
                    ? window.growtype_wc_ajax.url
                    : '/wp-admin/admin-ajax.php';

                var nonce = getPaymentFormNonce(provider, paymentFormNonce);

                if (!paymentFormAction) {
                    debugPaymentForm('fetch_form:blocked_missing_payment_form_action', {
                        provider: provider,
                        primaryMethod: primaryMethod,
                        fallbackUrl: fallbackUrl,
                    });
                    hideSpinner(loader);

                    if (fallbackUrl) {
                        window.location.href = fallbackUrl;
                    } else {
                        resetProcessingButtons();
                    }

                    return;
                }

                var body = new URLSearchParams({
                    action: paymentFormAction,
                    nonce: nonce,
                    product_id: productId,
                    methods: methods,
                    provider: provider,
                    payment_method: primaryMethod,
                });
                debugPaymentForm('fetch_form:start', {
                    provider: provider,
                    action: paymentFormAction,
                    ajaxUrl: ajaxUrl,
                    hasNonce: !!nonce,
                    productId: productId,
                    methods: methods,
                });

                resolveConfig().then(function () {
                    debugPaymentForm('fetch_form:config_ready_before_request', {
                        provider: provider,
                        hasAjaxConfig: !!window.growtype_wc_ajax,
                    });

                    return fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    });
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        debugPaymentForm('fetch_form:response', {
                            provider: provider,
                            success: !!(res && res.success),
                            dataKeys: res && res.data ? Object.keys(res.data) : [],
                            containerId: res && res.data ? res.data.container_id : '',
                            productId: res && res.data ? res.data.product_id : '',
                            methods: res && res.data ? res.data.methods : '',
                            htmlLength: res && res.data && res.data.html ? res.data.html.length : 0,
                        });
                        hideSpinner(loader);

                        if (!res.success) {
                            debugPaymentForm('fetch_form:blocked_ajax_error', res);
                            console.error('[wc-payment-form] AJAX error:', res);
                            resetProcessingButtons();
                            return;
                        }

                        if (res.data && res.data.total_price && modalEl) {
                            var modalTitle = modalEl.querySelector('.modal-title');
                            if (modalTitle) {
                                modalTitle.innerHTML = 'Total: ' + res.data.total_price;
                            }
                        }

                        var wrap = document.createElement('div');
                        wrap.className = 'gwc-checkout-payment-form';
                        wrap.style.cssText = 'opacity:0; transition:opacity 0.25s;';
                        wrap.innerHTML = res.data.html; // Safe: no <script> tags from server
                        modalBody.appendChild(wrap);
                        debugPaymentForm('fetch_form:html_appended', {
                            wrap: getContainerDebugSummary(wrap),
                            modalBody: getContainerDebugSummary(modalBody),
                            mountContainer: getContainerDebugSummary(document.getElementById(res.data.container_id)),
                        });

                        // Fade in
                        requestAnimationFrame(function () {
                            requestAnimationFrame(function () {
                                wrap.style.opacity = '1';
                            });
                        });

                        if (res.data.mount_express === false) {
                            debugPaymentForm('fetch_form:subscription_redirect_only', {
                                productId: res.data.product_id,
                            });
                            return;
                        }

                        // Boot provider-specific express buttons
                        debugPaymentForm('fetch_form:dispatch_mount_express', {
                            provider: provider,
                            container: '#' + res.data.container_id,
                            productId: parseInt(res.data.product_id, 10),
                            methods: res.data.methods,
                            returnUrl: returnUrl,
                            fallback: fallbackUrl,
                            label: buttonLabel,
                            showForm: shouldShowPaymentForm,
                            paymentMethod: primaryMethod,
                            paymentFormAction: paymentFormAction,
                            mountContainer: getContainerDebugSummary(document.getElementById(res.data.container_id)),
                        });
                        document.dispatchEvent(new CustomEvent('growtype_wc_payment_request', {
                            detail: {
                                provider: provider,
                                type: 'mount_express',
                                container: '#' + res.data.container_id,
                                productId: parseInt(res.data.product_id, 10),
                                method: res.data.methods,
                                returnUrl: returnUrl,
                                fallback: fallbackUrl,
                                label: buttonLabel,
                                showForm: shouldShowPaymentForm,
                                paymentMethod: primaryMethod,
                                paymentFormAction: paymentFormAction,
                            }
                        }));
                    })
                    .catch(function (err) {
                        debugPaymentForm('fetch_form:error', err);
                        hideSpinner(loader);
                        console.error('[wc-payment-form] Fetch failed:', err);
                        resetProcessingButtons();
                    });
            }

            if (!isNewModal) {
                debugPaymentForm('continue_flow:existing_modal_fade_out_start', {
                    modalBody: getContainerDebugSummary(modalBody),
                });
                // Fade out existing modal body content
                var children = Array.from(modalBody.children);
                children.forEach(function (el) {
                    if (el.classList.contains('btn-close')) return;
                    el.style.transition = 'opacity 0.2s';
                    el.style.opacity = '0';
                    el.style.pointerEvents = 'none';
                });

                setTimeout(function () {
                    debugPaymentForm('continue_flow:existing_modal_fetch_after_fade');
                    children.forEach(function (el) {
                        if (el.classList.contains('btn-close')) return;
                        el.style.display = 'none';
                    });

                    loader = showSpinner(modalBody);
                    fetchForm();
                }, 220);
            } else {
                debugPaymentForm('continue_flow:new_modal_show_then_fetch', {
                    openDelay: fromModal ? 150 : 0,
                });
                // New modal flow: show spinner, open modal, fetch form
                loader = showSpinner(modalBody);
                var openDelay = fromModal ? 150 : 0;
                setTimeout(function () {
                    showPaymentFormModal(modal);
                    fetchForm();
                }, openDelay);
            }
        }
    }, true);

    // ── Boot on page load ─────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            debugPaymentForm('dom_ready:DOMContentLoaded');
            initMounts(document);
            openModalOnInstantChargeFail();
        });
    } else {
        debugPaymentForm('dom_ready:already_ready');
        initMounts(document);
        openModalOnInstantChargeFail();
    }

    /**
     * If the page was loaded after a failed instant charge (upsell_failed=1),
     * automatically open the payment modal so the user can pay with another method.
     * The instant-charge-url is cleared from the button so the modal path is taken.
     */
    function openModalOnInstantChargeFail() {
        var params = new URLSearchParams(window.location.search);
        if (!params.has('upsell_failed')) {
            debugPaymentForm('instant_charge_fail:no_url_flag');
            return;
        }

        var btn = document.querySelector('.' + BTN_CLASS + '[data-product-id]');
        if (!btn) {
            debugPaymentForm('instant_charge_fail:blocked_missing_checkout_button');
            return;
        }
        debugPaymentForm('instant_charge_fail:opening_modal', {
            button: getContainerDebugSummary(btn),
        });

        // Remove the instant-charge attributes so the normal modal flow is used
        btn.removeAttribute('data-instant-charge');
        btn.removeAttribute('data-instant-charge-url');

        // Small delay to let the page and any error alerts render first
        setTimeout(function () {
            btn.click();
        }, 600);
    }

    // ── MutationObserver — catch dynamically injected mounts ──────────────────

    if (window.MutationObserver) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return; // elements only
                    if (node.classList && node.classList.contains(MOUNT_CLASS)) {
                        debugPaymentForm('mutation_observer:mount_node_added', {
                            node: getContainerDebugSummary(node),
                        });
                        initMounts(node.parentElement || document);
                    } else if (node.querySelectorAll) {
                        var found = node.querySelectorAll('.' + MOUNT_CLASS).length;
                        if (found) {
                            debugPaymentForm('mutation_observer:mount_descendant_added', {
                                found: found,
                                node: getContainerDebugSummary(node),
                            });
                        }
                        initMounts(node);
                    }
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    // ── Public API ────────────────────────────────────────────────────────────

    window.gwcPaymentForm = {
        /** Manually boot mount points inside a given element (or document). */
        init: initMounts,
        showSpinner: showSpinner,
        hideSpinner: hideSpinner,
        setDebug: setPaymentFormDebug,
        isDebugEnabled: isPaymentFormDebugEnabled,
    };

})();
