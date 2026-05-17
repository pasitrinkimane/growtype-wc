<?php

if (!function_exists('growtype_wc_payment_form')) {
    /**
     * Render a payment form for any active WooCommerce gateway provider.
     *
     * The function detects the preferred gateway automatically from site settings,
     * or you can force a specific provider via the 'provider' param.
     *
     * Minimum usage:
     *   echo growtype_wc_payment_form(['product_id' => 131]);
     *
     * Force a specific provider:
     *   echo growtype_wc_payment_form(['product_id' => 131, 'provider' => 'paypal']);
     *   echo growtype_wc_payment_form(['product_id' => 131, 'provider' => 'stripe']);
     *
     * @param array $args {
     *   @type int    $product_id       WooCommerce product ID. Required.
     *   @type string $provider         'paypal' | 'stripe' | 'auto' (default: 'auto').
     *                                  'auto' resolves from the site's preferred gateway setting.
     *   @type string $container_id     HTML id for the express-buttons container. Auto-generated if omitted.
     *
     *   -- Express buttons (PayPal / Apple Pay / Google Pay) --
     *   @type bool   $show_express     Show wallet buttons. Default true.
     *   @type array  $express_methods  Methods to request. Default ['applepay','googlepay','paypal'].
     *   @type bool   $show_divider     Show "— or pay with card —" separator. Default true.
     *
     *   -- Card form --
     *   @type bool   $show_card        Show hosted card fields. Default true.
     *   @type string $submit_label     Card submit button text. Default ''.
     *   @type bool   $show_loader      Show spinner while SDK loads. Default true.
     * }
     * @return string Rendered HTML.
     */
    function growtype_wc_payment_form(array $args = []): string
    {
        $product_id   = (int) ($args['product_id']   ?? 0);
        $provider     = strtolower((string) ($args['provider'] ?? 'auto'));
        $container_id = sanitize_html_class($args['container_id'] ?? ('gwc-express-' . $product_id . '-' . wp_rand(1000, 9999)));

        // ── Resolve provider ──────────────────────────────────────────────────
        if ($provider === 'auto') {
            $provider = growtype_wc_resolve_preferred_payment_provider();
        }

        // ── Dispatch to provider renderer ─────────────────────────────────────
        ob_start();

        switch ($provider) {
            case 'paypal':
                if (class_exists('Growtype_Wc_Payment_Gateway_Paypal_Payment_Form')) {
                    Growtype_Wc_Payment_Gateway_Paypal_Payment_Form::render(array_merge($args, [
                        'product_id'   => $product_id,
                        'container_id' => $container_id,
                    ]));
                } else {
                    error_log('[growtype_wc_payment_form] PayPal payment form class not found.');
                }
                break;

            case 'stripe':
                /**
                 * Stripe renderer hook — implement via:
                 *   add_action('growtype_wc_payment_form_stripe', function($args) { ... });
                 */
                do_action('growtype_wc_payment_form_stripe', array_merge($args, [
                    'product_id'   => $product_id,
                    'container_id' => $container_id,
                ]));
                break;

            default:
                /**
                 * Custom provider hook — implement via:
                 *   add_action("growtype_wc_payment_form_{$provider}", function($args) { ... });
                 */
                do_action("growtype_wc_payment_form_{$provider}", array_merge($args, [
                    'product_id'   => $product_id,
                    'container_id' => $container_id,
                ]));

                if (!has_action("growtype_wc_payment_form_{$provider}")) {
                    error_log("[growtype_wc_payment_form] Unknown provider: {$provider}");
                }
                break;
        }

        return (string) ob_get_clean();
    }
}

if (!function_exists('growtype_wc_resolve_preferred_payment_provider')) {
    /**
     * Resolve which payment provider the site prefers.
     *
     * Checks (in order):
     *  1. growtype_wc_preferred_payment_provider option
     *  2. First enabled WooCommerce gateway that we know how to render
     *
     * @return string Provider slug: 'paypal' | 'stripe' | ''
     */
    function growtype_wc_resolve_preferred_payment_provider(): string
    {
        // 1. Explicit site setting
        $setting = get_option('growtype_wc_preferred_payment_provider', '');
        if (!empty($setting)) {
            return strtolower(sanitize_text_field($setting));
        }

        // 2. Detect from active WC gateways
        $known = [
            'growtype_wc_paypal' => 'paypal',
            'paypal'             => 'paypal',
            'stripe'             => 'stripe',
        ];

        if (function_exists('WC') && WC()->payment_gateways) {
            foreach (WC()->payment_gateways->payment_gateways() as $id => $gateway) {
                if ($gateway->is_available() && isset($known[$id])) {
                    return $known[$id];
                }
            }
        }

        // 3. Fallback: if PayPal class exists, use it
        if (class_exists('Growtype_Wc_Payment_Gateway_Paypal_Payment_Form')) {
            return 'paypal';
        }

        return '';
    }
}
