export function paymentFormLoaded(params) {
    return new CustomEvent("growtypeWcPaymentFormLoaded", {
        detail: params
    });
}
