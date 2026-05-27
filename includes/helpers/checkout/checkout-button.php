<?php

if (!function_exists('growtype_wc_checkout_button')) {
    /**
     * Render a "Continue to Payment" CTA button.
     *
     * On click, wc-payment-form.js fetches the payment form HTML via AJAX,
     * injects it into the modal body, and boots the PayPal SDK.
     * No form HTML is pre-rendered — everything is loaded on demand.
     *
     * Usage:
     *   echo growtype_wc_checkout_button('Claim My Credits Now!', 131, ['applePay', 'googlePay']);
     *
     * @param string      $button_text Label shown on the CTA button.
     * @param int         $product_id  WooCommerce product ID.
     * @param array       $methods     Express wallet methods (passed to payment form on click).
     * @param string|null $return_url  Post-payment redirect URL. Emitted as data-return-url so
     *                                 wc-payment-form.js can forward it through the payment chain.
     * @return string Rendered HTML.
     */
    function growtype_wc_checkout_button(
        string  $button_text,
        int     $product_id,
        array   $methods    = ['applePay', 'googlePay'],
        ?string $return_url = null
    ): string {
        if (class_exists('Growtype_Wc_Payment')) {
            Growtype_Wc_Payment::$should_render_modal = true;
        }

        $methods_str = implode(',', array_map('strtolower', $methods));

        $instant_charge_url = (class_exists('Growtype_Wc_Payment') && Growtype_Wc_Payment::user_can_repeat_purchase())
            ? Growtype_Wc_Payment::get_repeat_purchase_url($product_id, (string)($return_url ?? ''))
            : '';

        ob_start(); ?>
        <div class="gwc-checkout-btn-wrap">
            <button type="button"
                    class="growtype-wc-checkout-button btn btn-primary btn-lg w-100"
                    style="font-size:1.1rem; min-height:50px;font-weight:bold;"
                    data-product-id="<?php echo esc_attr($product_id); ?>"
                    data-methods="<?php echo esc_attr($methods_str); ?>"
                    <?php if (!empty($return_url)) : ?>
                        data-return-url="<?php echo esc_url($return_url); ?>"
                    <?php endif; ?>
                    <?php if (!empty($instant_charge_url)) : ?>
                        data-instant-charge="1"
                        data-instant-charge-url="<?php echo esc_url($instant_charge_url); ?>"
                    <?php endif; ?>>
                <?php echo esc_html($button_text ?: __('Continue to Payment', 'growtype-wc')); ?>
            </button>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
