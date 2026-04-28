/**
 * Coinbase Payment Button Handler
 *
 * Self-registers with GrowtypeWcPaymentButton registry.
 */

function growtypeWcCoinbaseButtonHandler(container, { method, type, label }) {
    const config = window.growtype_wc_ajax || window.growtype_wc_params || {};
    const publicUrl = config.public_url || '';

    const btn = document.createElement('a');
    btn.href = '#';
    btn.className = 'btn-coinbase';
    btn.innerHTML = `
        ${label || 'Pay with Crypto'}
        <img src="${publicUrl}icons/payment-methods/coinbase.svg" alt="Coinbase" height="20" style="margin-left:8px;">
    `;
    container.replaceWith(btn);
}

export { growtypeWcCoinbaseButtonHandler };
