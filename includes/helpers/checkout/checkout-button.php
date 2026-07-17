<?php

if (!function_exists('growtype_wc_checkout_button_bool_attr')) {
    function growtype_wc_checkout_button_bool_attr($value): string
    {
        return in_array(strtolower((string)$value), ['yes', '1', 'true'], true) ? 'yes' : 'no';
    }
}

if (!function_exists('growtype_wc_checkout_button_provider_from_method')) {
    function growtype_wc_checkout_button_provider_from_method(string $payment_method): string
    {
        $payment_method = sanitize_key($payment_method);
        $provider = $payment_method;

        if (strpos($provider, 'gwc-') === 0) {
            $provider = substr($provider, 4);
        }

        if (strpos($provider, 'growtype_wc_') === 0) {
            $provider = substr($provider, 12);
        }

        $provider = str_replace('_', '-', $provider);

        return apply_filters(
            'growtype_wc_checkout_button_provider',
            $provider ?: 'paypal',
            $payment_method
        );
    }
}

if (!function_exists('growtype_wc_checkout_button_provider_option_keys')) {
    function growtype_wc_checkout_button_provider_option_keys(string $payment_method, string $provider): array
    {
        $payment_method = sanitize_key($payment_method);
        $provider = sanitize_key($provider);

        $option_keys = array_filter(array_unique([
            'woocommerce_' . $payment_method . '_settings',
            'woocommerce_growtype_wc_' . $provider . '_settings',
            'woocommerce_' . $provider . '_settings',
        ]));

        return apply_filters(
            'growtype_wc_checkout_button_provider_option_keys',
            $option_keys,
            $payment_method,
            $provider
        );
    }
}

if (!function_exists('growtype_wc_checkout_button_provider_settings')) {
    function growtype_wc_checkout_button_provider_settings(string $payment_method, string $provider): array
    {
        foreach (growtype_wc_checkout_button_provider_option_keys($payment_method, $provider) as $option_key) {
            $settings = get_option($option_key, []);
            if (is_array($settings) && !empty($settings)) {
                return $settings;
            }
        }

        return [];
    }
}

if (!function_exists('growtype_wc_checkout_button_payment_form_mode')) {
    function growtype_wc_checkout_button_payment_form_mode(
        string $provider,
        string $payment_method,
        array $provider_settings,
        int $product_id,
        array $methods
    ): string
    {
        $default = $provider === 'paypal' ? 'yes' : 'no';

        $should_render = growtype_wc_checkout_button_bool_attr(
            $provider_settings['show_payment_form'] ?? $default
        );

        $should_render = growtype_wc_checkout_button_bool_attr(
            apply_filters(
                'growtype_wc_checkout_button_express_show_form',
                $should_render,
                $payment_method,
                $product_id,
                $methods,
                $provider
            )
        );

        return $should_render === 'yes' ? 'form' : 'redirect';
    }
}

if (!function_exists('growtype_wc_checkout_button_payment_form_meta')) {
    function growtype_wc_checkout_button_payment_form_meta(string $provider, string $payment_method, int $product_id, array $methods): array
    {
        $provider = sanitize_key($provider);

        $actions = [
            'stripe' => 'gwc_stripe_payment_form',
            'paypal' => 'gwc_payment_form',
        ];

        $nonce_actions = [
            'stripe' => 'growtype_wc_ajax_nonce',
            'paypal' => 'gwc_payment_form_render',
        ];

        $meta = [
            'action' => $actions[$provider] ?? '',
            'nonce' => isset($nonce_actions[$provider]) ? wp_create_nonce($nonce_actions[$provider]) : '',
        ];

        $meta = apply_filters(
            'growtype_wc_checkout_button_payment_form_meta',
            $meta,
            $provider,
            $payment_method,
            $product_id,
            $methods
        );

        return [
            'action' => is_array($meta) ? sanitize_key($meta['action'] ?? '') : '',
            'nonce' => is_array($meta) ? (string)($meta['nonce'] ?? '') : '',
        ];
    }
}

if (!function_exists('growtype_wc_checkout_button')) {
    /**
     * Render a "Continue to Payment" CTA button.
     *
     * On click, wc-payment-form.js fetches the payment form HTML via AJAX,
     * injects it into the modal body, and boots the selected payment provider.
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

        $primary_method = 'gwc-stripe';
        if (class_exists('Growtype_Wc_Payment_Settings')) {
            $primary_method = Growtype_Wc_Payment_Settings::get_primary_method_id();
        } else {
            $setting = get_option('growtype_wc_primary_payment_method', 'auto');
            if ($setting === 'gwc-paypal' || $setting === 'gwc-stripe') {
                $primary_method = $setting;
            } elseif (
                function_exists('growtype_wc_payment_method_is_enabled') &&
                class_exists('Growtype_Wc_Payment_Gateway_Stripe') &&
                growtype_wc_payment_method_is_enabled(
                    Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID
                )
            ) {
                $primary_method = 'gwc-stripe';
            } else {
                $primary_method = 'gwc-paypal';
            }
        }
        $primary_method = apply_filters(
            'growtype_wc_checkout_button_primary_payment_method',
            $primary_method,
            $product_id,
            $methods,
            $return_url
        );
        $primary_method = sanitize_key((string)$primary_method);

        $fallback_url = add_query_arg(
            [
                'add-to-cart' => $product_id,
                'payment_method' => $primary_method,
            ],
            wc_get_checkout_url()
        );
        if (!empty($return_url)) {
            $fallback_url = add_query_arg(
                [
                    'growtype_return_after_payment_url' => rawurlencode((string)$return_url),
                ],
                $fallback_url
            );
        }

        $provider = growtype_wc_checkout_button_provider_from_method($primary_method);
        $provider_settings = growtype_wc_checkout_button_provider_settings($primary_method, $provider);
        $payment_form_mode = growtype_wc_checkout_button_payment_form_mode(
            $provider,
            $primary_method,
            $provider_settings,
            $product_id,
            $methods
        );
        $should_render_payment_form = $payment_form_mode === 'form' ? 'yes' : 'no';
        $payment_form_meta = growtype_wc_checkout_button_payment_form_meta($provider, $primary_method, $product_id, $methods);
        $instant_charge_url = ($payment_form_mode === 'redirect' && class_exists('Growtype_Wc_Payment') && Growtype_Wc_Payment::user_can_repeat_purchase())
            ? Growtype_Wc_Payment::get_repeat_purchase_url($product_id, (string)($return_url ?? ''))
            : '';

        ob_start(); ?>
        <div class="gwc-checkout-btn-wrap">
            <button type="button"
                    class="growtype-wc-checkout-button btn btn-primary btn-lg w-100"
                    style="font-size:1.1rem; min-height:50px;font-weight:bold;"
                    data-product-id="<?php echo esc_attr($product_id); ?>"
                    data-methods="<?php echo esc_attr($methods_str); ?>"
                    data-primary-payment-method="<?php echo esc_attr($primary_method); ?>"
                    data-provider="<?php echo esc_attr($provider); ?>"
                    data-fallback="<?php echo esc_url($fallback_url); ?>"
                    data-payment-form-mode="<?php echo esc_attr($payment_form_mode); ?>"
                    data-express-show-form="<?php echo esc_attr($should_render_payment_form); ?>"
                    data-payment-form-action="<?php echo esc_attr($payment_form_meta['action']); ?>"
                    data-payment-form-nonce="<?php echo esc_attr($payment_form_meta['nonce']); ?>"
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
