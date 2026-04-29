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
    var MOUNT_CLASS     = 'gwc-payment-form-mount';
    var BOOTED_ATTR     = 'data-gwc-booted';
    var BTN_CLASS       = 'growtype-wc-checkout-button';

    // ── Config resolution ─────────────────────────────────────────────────────

    var _configPromise = null;

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
        var mounts   = root.querySelectorAll ? root.querySelectorAll(selector) : [];

        Array.prototype.forEach.call(mounts, function (el) {
            el.setAttribute(BOOTED_ATTR, '1');

            var productId   = el.getAttribute('data-product-id') || '0';
            var methods     = el.getAttribute('data-methods')    || 'applepay,googlepay,paypal';
            var containerId = MOUNT_CLASS + '-' + productId + '-' + Math.random().toString(36).slice(2, 7);

            // Promote the placeholder to an express container
            el.id        = containerId;
            el.className = 'gwc-payment-form__express';
            el.setAttribute('data-method', methods);
            el.style.minHeight = '48px';

            resolveConfig().then(function () {
                document.dispatchEvent(new CustomEvent('growtype_wc_payment_request', {
                    detail: {
                        provider:  'paypal',
                        type:      'mount_express',
                        container: '#' + containerId,
                        productId: parseInt(productId, 10),
                        method:    methods,
                    }
                }));
            });
        });
    }

    // ── Checkout button → payment form transition ─────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.' + BTN_CLASS + '[data-product-id]');
        if (!btn) return;
        e.preventDefault();

        var productId  = btn.getAttribute('data-product-id');
        var methods    = btn.getAttribute('data-methods') || 'applepay,googlepay';
        var modalBody  = btn.closest('.modal-body');
        if (!modalBody) return;

        var ajaxUrl = (window.growtype_wc_ajax && window.growtype_wc_ajax.url)
            ? window.growtype_wc_ajax.url
            : '/wp-admin/admin-ajax.php';

        var nonce = (window.growtype_wc_ajax
            && window.growtype_wc_ajax.paypal
            && window.growtype_wc_ajax.paypal.payment_form_nonce)
            ? window.growtype_wc_ajax.paypal.payment_form_nonce
            : '';

        // Show a loader while fetching
        var loader = document.createElement('div');
        loader.className = 'gwc-payment-form__loader';
        loader.innerHTML = '<div class="gwc-hf-spinner"></div>';
        loader.style.cssText = 'display:flex;align-items:center;justify-content:center;padding:40px 0;';

        // Fade out existing modal body content
        var children = Array.from(modalBody.children);
        children.forEach(function (el) {
            if (el.classList.contains('btn-close')) return;
            el.style.transition    = 'opacity 0.2s';
            el.style.opacity       = '0';
            el.style.pointerEvents = 'none';
        });

        setTimeout(function () {
            children.forEach(function (el) {
                if (el.classList.contains('btn-close')) return;
                el.style.display = 'none';
            });
            modalBody.appendChild(loader);

            // Fetch form HTML from the server
            var body = new URLSearchParams({
                action:     'gwc_payment_form',
                nonce:      nonce,
                product_id: productId,
                methods:    methods,
            });

            resolveConfig().then(function () {
                return fetch(ajaxUrl, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    body.toString(),
                });
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                loader.remove();

                if (!res.success) {
                    console.error('[wc-payment-form] AJAX error:', res);
                    return;
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
                        provider:  'paypal',
                        type:      'mount_express',
                        container: '#' + res.data.container_id,
                        productId: parseInt(res.data.product_id, 10),
                        method:    res.data.methods,
                    }
                }));
            })
            .catch(function (err) {
                loader.remove();
                console.error('[wc-payment-form] Fetch failed:', err);
            });
        }, 220);
    });

    // ── Boot on page load ─────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initMounts(document);
        });
    } else {
        initMounts(document);
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
    };

})();
