function normalizeDebugFlag(value) {
    if (typeof value === 'boolean') {
        return value;
    }

    if (value === null || typeof value === 'undefined') {
        return null;
    }

    value = String(value).toLowerCase();

    if (['1', 'true', 'yes', 'on'].indexOf(value) !== -1) {
        return true;
    }

    if (['0', 'false', 'no', 'off'].indexOf(value) !== -1) {
        return false;
    }

    return null;
}

function isPaymentFormDebugEnabled() {
    var params = new URLSearchParams(window.location.search);
    var queryDebug = normalizeDebugFlag(params.get('gwc_payment_form_debug'));

    if (queryDebug !== null) {
        return queryDebug;
    }

    var globalDebug = normalizeDebugFlag(window.gwcPaymentFormDebug);

    if (globalDebug !== null) {
        return globalDebug;
    }

    var configDebug = normalizeDebugFlag(
        window.growtype_wc_ajax && window.growtype_wc_ajax.payment_form_debug
    );

    if (configDebug !== null) {
        return configDebug;
    }

    try {
        var storedDebug = normalizeDebugFlag(window.localStorage.getItem('gwcPaymentFormDebug'));

        if (storedDebug !== null) {
            return storedDebug;
        }
    } catch (err) {
        return false;
    }

    return false;
}

function debugPaymentForm(step, payload) {
    if (!isPaymentFormDebugEnabled()) {
        return;
    }

    console.debug('[wc-payment-form debug]', step, payload || {});
}

function getContainerDebugSummary(el) {
    if (!el) {
        return {
            exists: false,
        };
    }

    return {
        exists: true,
        id: el.id || '',
        className: el.className || '',
        childElementCount: el.children ? el.children.length : 0,
        childNodeCount: el.childNodes ? el.childNodes.length : 0,
        visibleText: (el.textContent || '').trim().slice(0, 120),
    };
}

function setPaymentFormDebug(enabled, persist) {
    window.gwcPaymentFormDebug = !!enabled;

    if (persist) {
        try {
            window.localStorage.setItem('gwcPaymentFormDebug', enabled ? '1' : '0');
        } catch (err) {}
    }
}

export {
    debugPaymentForm,
    getContainerDebugSummary,
    isPaymentFormDebugEnabled,
    setPaymentFormDebug,
};
