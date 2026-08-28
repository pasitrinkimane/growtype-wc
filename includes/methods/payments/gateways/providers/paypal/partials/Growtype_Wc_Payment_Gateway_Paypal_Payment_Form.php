<?php

/**
 * Flexible PayPal Payment Form
 *
 * Renders the full PayPal payment UI in any context — modal, inline page,
 * shortcode, sidebar, etc. — without coupling to a specific layout.
 *
 * Usage:
 *
 *   // Apple Pay / Google Pay + card form (default):
 *   Growtype_Wc_Payment_Gateway_Paypal_Payment_Form::render([
 *       'product_id'   => 131,
 *       'container_id' => 'my-paypal-form',
 *   ]);
 *
 *   // Express buttons only (no card form):
 *   Growtype_Wc_Payment_Gateway_Paypal_Payment_Form::render([
 *       'product_id'      => 131,
 *       'show_card'       => false,
 *       'express_methods' => ['applepay', 'googlepay', 'paypal'],
 *   ]);
 *
 *   // Card form only (no express buttons):
 *   Growtype_Wc_Payment_Gateway_Paypal_Payment_Form::render([
 *       'product_id'   => 131,
 *       'show_express' => false,
 *   ]);
 */
class Growtype_Wc_Payment_Gateway_Paypal_Payment_Form
{
    // ── AJAX endpoint ─────────────────────────────────────────────────────────

    /**
     * Register the AJAX action that dynamically renders the payment form.
     * Call once during plugin boot — e.g. from the PayPal gateway constructor.
     */
    public static function init(): void
    {
        add_action('wp_ajax_gwc_payment_form', [self::class, 'ajax_render']);
        add_action('wp_ajax_nopriv_gwc_payment_form', [self::class, 'ajax_render']);
    }

    /**
     * AJAX handler — returns the payment form HTML + boot parameters.
     * Boot script is NOT included in the HTML so that JS can safely set
     * innerHTML without worrying about script execution.
     */
    public static function ajax_render(): void
    {
        check_ajax_referer('gwc_payment_form_render', 'nonce');

        $product_id = absint($_POST['product_id'] ?? 0);
        $methods_raw = sanitize_text_field($_POST['methods'] ?? 'applepay,googlepay');
        $methods = array_map('sanitize_text_field', explode(',', $methods_raw));
        $container_id = 'gwc-express-' . $product_id . '-' . wp_rand(1000, 9999);

        ob_start();

        self::render([
            'product_id' => $product_id,
            'express_methods' => $methods,
            'container_id' => $container_id,
            'boot_script' => false, // JS dispatches the mount event after injection
        ]);

        $html = (string)ob_get_clean();

        $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
        $total_price = '';
        if ($product) {
            $total_price = html_entity_decode(strip_tags(wc_price($product->get_price())));
        }

        wp_send_json_success([
            'html' => $html,
            'container_id' => $container_id,
            'product_id' => $product_id,
            'methods' => $methods_raw,
            'total_price' => $total_price,
        ]);
    }

    /**
     * Render the payment form.
     *
     * @param array $args {
     * @type int $product_id WooCommerce product ID (required).
     * @type string $container_id HTML id for the express buttons container. Default 'gwc-paypal-express'.
     * @type bool $show_express Whether to render Apple Pay / Google Pay / PayPal buttons. Default true.
     * @type array $express_methods Which wallet methods to request. Default ['applepay','googlepay','paypal'].
     * @type bool $show_card Whether to render the card hosted fields form. Default true.
     * @type bool $show_divider Whether to show the "— or pay with card —" separator. Default true.
     * @type bool $show_loader Whether to show a spinner overlay while the form loads. Default true.
     * @type string $submit_label Card form submit button text.
     * }
     */
    public static function render(array $args = []): void
    {
        // Skip asset enqueueing during AJAX — headers already sent / no DOM to inject into.
        if (!wp_doing_ajax()) {
            self::ensure_assets();
        }

        $product_id = (int)($args['product_id'] ?? 0);
        $container_id = sanitize_html_class($args['container_id'] ?? 'gwc-paypal-express');
        $show_express = (bool)($args['show_express'] ?? true);
        $express_methods = (array)($args['express_methods'] ?? ['applepay', 'googlepay', 'paypal']);
        $show_card = (bool)($args['show_card'] ?? true);
        $show_divider = (bool)($args['show_divider'] ?? true);
        $show_loader = (bool)($args['show_loader'] ?? true);
        $submit_label = $args['submit_label'] ?? Growtype_Wc_Payment_Gateway_Paypal_Card_Form::get_default_submit_label();
        $boot_script = (bool)($args['boot_script'] ?? true); // false when rendered via AJAX
        $gateways = (function_exists('WC') && WC() && WC()->payment_gateways())
            ? WC()->payment_gateways()->payment_gateways()
            : [];
        $paypal_gateway = $gateways[Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID] ?? null;
        $show_dev_helper = $paypal_gateway ? (bool) $paypal_gateway->is_test_mode() : false;

        $methods_str = implode(',', array_map('sanitize_text_field', $express_methods));
        ?>
        <div class="gwc-payment-form" data-product-id="<?php echo esc_attr($product_id); ?>">

            <?php if ($show_express) : ?>
                <!-- Express wallet buttons (Apple Pay / Google Pay / PayPal) -->
                <div id="<?php echo esc_attr($container_id); ?>"
                     class="gwc-payment-form__express"
                     data-product-id="<?php echo esc_attr($product_id); ?>"
                     data-method="<?php echo esc_attr($methods_str); ?>"
                     style="min-height:48px; position:relative;">
                    <?php self::render_loader([
                        'id' => $container_id . '-loader',
                    ]); ?>
                </div>
            <?php endif; ?>

            <?php if ($show_express && $show_card && $show_divider) : ?>
                <!-- Divider -->
                <div class="gwc-payment-form__divider">
                    <span><?php _e('or pay with card', 'growtype-child'); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($show_card) : ?>
                <!-- Hosted Fields card form -->
                <div class="gwc-payment-form__card" style="position:relative; min-height:250px;">
                    <?php self::render_loader(); ?>

                    <?php Growtype_Wc_Payment_Gateway_Paypal_Card_Form::render([
                        'submit_label' => $submit_label,
                        'show_dev_helper' => $show_dev_helper,
                    ]); ?>
                </div>
            <?php endif; ?>

            <?php self::render_badge(); ?>

        </div>

        <?php
        // Boot the express checkout buttons once the DOM is ready.
        // Skipped when rendered via AJAX — the caller dispatches the event after
        // safely injecting the HTML (avoids script tags inside innerHTML).
        if ($show_express && $boot_script) {
            self::render_express_boot_script($container_id, $product_id, $methods_str);
        }
    }

    // ── Asset loading ─────────────────────────────────────────────────────────

    /**
     * Ensure growtype-wc.js and growtype-wc.css are loaded.
     *
     * Two strategies:
     *   1. Before wp_head() → wp_enqueue_script/style (standard WP path).
     *   2. After wp_head() (modal rendered mid-template) → print tags directly,
     *      guarded by a static flag so they appear only once per page.
     */
    private static function ensure_assets(): void
    {
        static $assets_printed = false;

        $script_handle = 'growtype-wc';
        $style_handle = 'growtype-wc';
        $script_url = GROWTYPE_WC_URL_PUBLIC . 'scripts/growtype-wc.js';
        $style_url = GROWTYPE_WC_URL_PUBLIC . 'styles/growtype-wc.css';
        $version = defined('GROWTYPE_WC_VERSION') ? GROWTYPE_WC_VERSION : null;

        // Standard path — wp_head hasn't fired yet, let WP handle it.
        // CSS is injected via enqueue_form_styles() on wp_enqueue_scripts.
        if (!did_action('wp_head')) {
            wp_enqueue_script($script_handle, $script_url, ['jquery'], $version, true);
            wp_enqueue_style($style_handle, $style_url, [], $version, 'all');
            return;
        }

        // Late path — wp_head already fired, print once directly.
        if ($assets_printed) {
            return;
        }
        $assets_printed = true;

        // Script — only if WP hasn't already output it.
        if (!wp_script_is($script_handle, 'done')) {
            printf(
                '<script src="%s" id="%s-js"></script>' . "\n",
                esc_url(add_query_arg('ver', $version, $script_url)),
                esc_attr($script_handle)
            );
            // Ensure the JS config object is available for the late-loaded script.
            self::ensure_inline_paypal_data();
        }

        // Style — only if WP hasn't already output it.
        if (!wp_style_is($style_handle, 'done')) {
            printf(
                '<link rel="stylesheet" href="%s" id="%s-css">' . "\n",
                esc_url(add_query_arg('ver', $version, $style_url)),
                esc_attr($style_handle)
            );
        }
    }

    /**
     * Render the secure payment footer badge.
     */
    public static function render_badge(): void
    {
        ?>
        <div class="gwc-hf-footer-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="m9 12 2 2 4-4"></path>
            </svg>
            <span><?php _e('Your data is encrypted and never stored on our servers.', 'growtype-child'); ?></span>
        </div>
        <?php
    }

    /**
     * Render a loading overlay or block spinner.
     *
     * @param array $args Options:
     *   - 'id'    (string) The ID attribute. Default: 'gwc-paypal-form-loader'.
     *   - 'class' (string) Additional class name. Default: ''.
     *   - 'label' (string) Text label. Default: 'Loading...'.
     *   - 'style' (string) Inline CSS style. Default contains absolute positioning.
     */
    public static function render_loader(array $args = []): void
    {
        $id = $args['id'] ?? 'gwc-paypal-form-loader';
        $class = $args['class'] ?? '';
        $label = $args['label'] ?? '';

        $style = $args['style'] ?? '';
        $style_attr = $style ? ' style="' . esc_attr($style) . '"' : '';
        $base_class = 'gwc-paypal-form-loader';
        $full_class = $class ? $base_class . ' ' . $class : $base_class;

        $spinner_style = $args['spinner_style'] ?? '';
        $spinner_style_attr = $spinner_style ? ' style="' . esc_attr($spinner_style) . '"' : '';

        $label_style = $args['label_style'] ?? '';
        $label_style_attr = $label_style ? ' style="' . esc_attr($label_style) . '"' : '';
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($full_class); ?>"<?php echo $style_attr; ?>>
            <div class="gwc-hf-spinner"<?php echo $spinner_style_attr; ?>></div>
            <span class="gwc-paypal-form-loader-label"<?php echo $label_style_attr; ?>><?php echo esc_html($label); ?></span>
        </div>
        <?php
    }

    /**
     * Get the loader HTML markup.
     *
     * @param array $args Options
     * @return string
     */
    public static function get_loader_html(array $args = []): string
    {
        ob_start();
        self::render_loader($args);
        return ob_get_clean();
    }



    /**
     * Print window.growtype_wc_ajax with PayPal config so the form can boot
     * even when localize_script_data() hasn't run (late render path).
     *
     * Uses window.growtype_wc_ajax = window.growtype_wc_ajax || {...} so it
     * never overwrites data already set by the standard enqueue path.
     */
    private static function ensure_inline_paypal_data(): void
    {
        static $data_printed = false;
        if ($data_printed) {
            return;
        }
        $data_printed = true;

        $paypal_client_id = '';
        $paypal_merchant_id = '';
        $paypal_test_mode = false;
        $paypal_enabled = false;

        $gateways = (function_exists('WC') && WC() && WC()->payment_gateways())
            ? WC()->payment_gateways()->payment_gateways()
            : [];

        $paypal_gateway = $gateways[Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID] ?? null;
        if ($paypal_gateway) {
            $paypal_enabled = $paypal_gateway->is_available();
            $paypal_client_id = $paypal_gateway->get_client_id();
            $paypal_merchant_id = $paypal_gateway->get_merchant_id();
            $paypal_test_mode = $paypal_gateway->is_test_mode();
        }

        $data = wp_json_encode([
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('growtype_wc_ajax_nonce'),
            'paypal' => [
                'enabled' => $paypal_enabled,
                'client_id' => $paypal_client_id,
                'merchant_id' => $paypal_merchant_id,
                'test_mode' => $paypal_test_mode,
                'country_code' => wc_get_base_location()['country'] ?? 'US',
                'nonce' => wp_create_nonce('gwc_paypal_hosted_fields'),
                'loader_html' => self::get_loader_html([
                    'id' => '{{SPINNER_ID}}',
                    'class' => 'gwc-paypal-express-loader'
                ]),
            ],
        ]);

        printf(
            '<script>window.growtype_wc_ajax = window.growtype_wc_ajax || %s;</script>' . "\n",
            $data
        );
    }

    // ── Boot script ───────────────────────────────────────────────────────────

    /**
     * Emit the inline boot script that fires growtype_wc_payment_request
     * for the express buttons container.
     */
    private static function render_express_boot_script(string $container_id, int $product_id, string $methods_str): void
    {
        ?>
        <script>
            (function () {
                function bootPaypalForm() {
                    document.dispatchEvent(new CustomEvent('growtype_wc_payment_request', {
                        detail: {
                            provider: 'paypal',
                            type: 'mount_express',
                            container: '#<?php echo esc_js($container_id); ?>',
                            productId: <?php echo (int)$product_id; ?>,
                            method: '<?php echo esc_js($methods_str); ?>',
                        }
                    }));
                }

                // Fire immediately if DOM is ready, else wait.
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bootPaypalForm);
                } else {
                    bootPaypalForm();
                }
            })();
        </script>
        <?php
    }
}
