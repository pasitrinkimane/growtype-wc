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
 *     then dispatches the PayPal mount event.
 *
 *  3. Dynamic / AJAX — mount points added after page load are caught by
 *     MutationObserver and booted automatically.
 *
 *  4. AJAX context  — if window.growtype_wc_ajax is missing, fetches config
 *     from the REST endpoint before booting.
 */

(function () {
    'use strict';

    // ── Config ────────────────────────────────────────────────────────────────

    var CONFIG_ENDPOINT = '/wp-json/gwc/v1/payment-config';
    var MOUNT_CLASS = 'gwc-payment-form-mount';
    var BOOTED_ATTR = 'data-gwc-booted';
    var BTN_CLASS = 'growtype-wc-checkout-button';

    // ── Config resolution ─────────────────────────────────────────────────────

    var _configPromise = null;

    /**
     * Show a loader spinner inside a container.
     */
    function showSpinner(container) {
        if (!container) return null;
        var loader = container.querySelector('.gwc-payment-form-mainloader');
        if (loader) return loader;

        loader = document.createElement('div');
        loader.className = 'gwc-payment-form-mainloader';
        loader.innerHTML = '<div class="gwc-hf-spinner"></div>';
        container.appendChild(loader);
        return loader;
    }

    /**
     * Hide/remove a loader spinner inside a container or the loader element itself.
     */
    function hideSpinner(loaderOrContainer) {
        if (!loaderOrContainer) return;
        var loader = (loaderOrContainer.classList && loaderOrContainer.classList.contains('gwc-payment-form-mainloader'))
            ? loaderOrContainer
            : loaderOrContainer.querySelector('.gwc-payment-form-mainloader');

        if (loader) {
            loader.remove();
        }
    }

    /**
     * Remove the processing state from all payment/add-to-cart buttons.
     */
    function resetProcessingButtons() {
        var processingBtns = document.querySelectorAll('.processing');
        Array.prototype.forEach.call(processingBtns, function (btn) {
            btn.classList.remove('processing');
        });
        if (window.jQuery) {
            window.jQuery('.processing').removeClass('processing');
        }
    }

    /**
     * Bind close triggers and hidden state resets to the modal.
     */
    function bindModalEvents(modal) {
        if (modal.getAttribute('data-gwc-bound')) return;
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
            bindModalEvents(modal);
            return Promise.resolve(modal);
        }

        // Modal was not server-rendered — fetch the shell HTML from PHP.
        return resolveConfig().then(function () {
            var ajaxUrl = (window.growtype_wc_ajax && window.growtype_wc_ajax.url)
                ? window.growtype_wc_ajax.url
                : '/wp-admin/admin-ajax.php';

            return fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'gwc_payment_form_modal' }).toString(),
            });
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error('[wc-payment-form] gwc_payment_form_modal AJAX error');
            }

            var tmp = document.createElement('div');
            tmp.innerHTML = res.data.html;
            modal = tmp.firstElementChild;

            document.body.appendChild(modal);
            bindModalEvents(modal);

            return modal;
        });
    }

    /**
     * Show the Bootstrap modal.
     */
    function showPaymentFormModal(modal) {
        if (window.bootstrap && window.bootstrap.Modal) {
            var bsModal = window.bootstrap.Modal.getInstance(modal) || new window.bootstrap.Modal(modal);
            bsModal.show();
        } else if (window.jQuery && window.jQuery.fn.modal) {
            window.jQuery(modal).modal('show');
        } else {
            modal.style.display = 'block';
            modal.classList.add('show');
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    }

    /**
     * Returns a Promise that resolves with window.growtype_wc_ajax.
     * Fetches from REST if not already present.
     */
    function resolveConfig() {
        if (window.growtype_wc_ajax) {
            return Promise.resolve(window.growtype_wc_ajax);
        }
        if (_configPromise) {
            return _configPromise;
        }
        _configPromise = fetch(CONFIG_ENDPOINT)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                window.growtype_wc_ajax = data;
                return data;
            })
            .catch(function (err) {
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

        Array.prototype.forEach.call(mounts, function (el) {
            el.setAttribute(BOOTED_ATTR, '1');

            var productId = el.getAttribute('data-product-id') || '0';
            var methods = el.getAttribute('data-methods') || 'applepay,googlepay,paypal';
            var containerId = MOUNT_CLASS + '-' + productId + '-' + Math.random().toString(36).slice(2, 7);

            // Promote the placeholder to an express container
            el.id = containerId;
            el.className = 'gwc-payment-form__express';
            el.setAttribute('data-method', methods);
            el.style.minHeight = '48px';

            resolveConfig().then(function () {
                document.dispatchEvent(new CustomEvent('growtype_wc_payment_request', {
                    detail: {
                        provider: 'paypal',
                        type: 'mount_express',
                        container: '#' + containerId,
                        productId: parseInt(productId, 10),
                        method: methods,
                    }
                }));
            });
        });
    }

    // ── Checkout button → payment form transition ─────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.' + BTN_CLASS + '[data-product-id], .btn-show-paypal-card[data-product-id]');
        if (!btn) return;

        var instantUrl = btn.getAttribute('data-instant-charge-url');
        if (instantUrl) {
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

        if (btn.getAttribute('data-instant-charge') === '1') return;
        e.preventDefault();

        var productId = btn.getAttribute('data-product-id');
        var methods = btn.getAttribute('data-methods') || 'applepay,googlepay';
        
        var modalBody = btn.closest('.modal-body');

        // Detect if we are inside a non-payment modal (like Starter Pack modal)
        var existingModal = btn.closest('.modal');
        if (existingModal && existingModal.id !== 'gwcPaymentFormModal') {
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
            getOrCreatePaymentFormModal().then(function (modal) {
                var body = modal.querySelector('.modal-body-payment-form');
                if (!body) return;
                body.innerHTML = '';
                continueFlow(body, true, modal, existingModal);
            }).catch(function (err) {
                console.error('[wc-payment-form] Could not get modal:', err);
                resetProcessingButtons();
            });
            return;
        }

        continueFlow(modalBody, false, null, existingModal);

        function continueFlow(modalBody, isNewModal, modal, fromModal) {
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

                var nonce = (window.growtype_wc_ajax
                    && window.growtype_wc_ajax.paypal
                    && window.growtype_wc_ajax.paypal.payment_form_nonce)
                    ? window.growtype_wc_ajax.paypal.payment_form_nonce
                    : '';

                var body = new URLSearchParams({
                    action: 'gwc_payment_form',
                    nonce: nonce,
                    product_id: productId,
                    methods: methods,
                });

                resolveConfig().then(function () {
                    return fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    });
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        hideSpinner(loader);

                        if (!res.success) {
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

                        // Fade in
                        requestAnimationFrame(function () {
                            requestAnimationFrame(function () {
                                wrap.style.opacity = '1';
                            });
                        });

                        // Boot the PayPal express buttons
                        document.dispatchEvent(new CustomEvent('growtype_wc_payment_request', {
                            detail: {
                                provider: 'paypal',
                                type: 'mount_express',
                                container: '#' + res.data.container_id,
                                productId: parseInt(res.data.product_id, 10),
                                method: res.data.methods,
                            }
                        }));
                    })
                    .catch(function (err) {
                        hideSpinner(loader);
                        console.error('[wc-payment-form] Fetch failed:', err);
                        resetProcessingButtons();
                    });
            }

            if (!isNewModal) {
                // Fade out existing modal body content
                var children = Array.from(modalBody.children);
                children.forEach(function (el) {
                    if (el.classList.contains('btn-close')) return;
                    el.style.transition = 'opacity 0.2s';
                    el.style.opacity = '0';
                    el.style.pointerEvents = 'none';
                });

                setTimeout(function () {
                    children.forEach(function (el) {
                        if (el.classList.contains('btn-close')) return;
                        el.style.display = 'none';
                    });

                    loader = showSpinner(modalBody);
                    fetchForm();
                }, 220);
            } else {
                // New modal flow: show spinner, open modal, fetch form
                loader = showSpinner(modalBody);
                var openDelay = fromModal ? 150 : 0;
                setTimeout(function () {
                    showPaymentFormModal(modal);
                    fetchForm();
                }, openDelay);
            }
        }
    });

    // ── Boot on page load ─────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initMounts(document);
            openModalOnInstantChargeFail();
        });
    } else {
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
        if (!params.has('upsell_failed')) return;

        var btn = document.querySelector('.' + BTN_CLASS + '[data-product-id]');
        if (!btn) return;

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
                        initMounts(node.parentElement || document);
                    } else if (node.querySelectorAll) {
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
    };

})();
