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
        add_action('wp_ajax_gwc_payment_form',        [self::class, 'ajax_render']);
        add_action('wp_ajax_nopriv_gwc_payment_form', [self::class, 'ajax_render']);

        // Always load payment form CSS on the front-end — the classes are used
        // by both the AJAX payment form AND the Hosted Fields modal.
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_form_styles'], 20);
    }

    /**
     * Attach payment-form CSS as an inline style to the main plugin stylesheet.
     * Hooked to wp_enqueue_scripts so it runs before wp_head outputs the <link> tag.
     */
    public static function enqueue_form_styles(): void
    {
        // At wp_enqueue_scripts time the handle is 'registered' or 'enqueued', never 'done'.
        if (wp_style_is('growtype-wc', 'registered') || wp_style_is('growtype-wc', 'enqueued')) {
            wp_add_inline_style('growtype-wc', self::payment_form_css());
        }
    }

    /**
     * AJAX handler — returns the payment form HTML + boot parameters.
     * Boot script is NOT included in the HTML so that JS can safely set
     * innerHTML without worrying about script execution.
     */
    public static function ajax_render(): void
    {
        check_ajax_referer('gwc_payment_form_render', 'nonce');

        $product_id   = absint($_POST['product_id'] ?? 0);
        $methods_raw  = sanitize_text_field($_POST['methods'] ?? 'applepay,googlepay');
        $methods      = array_map('sanitize_text_field', explode(',', $methods_raw));
        $container_id = 'gwc-express-' . $product_id . '-' . wp_rand(1000, 9999);

        ob_start();
        // Include CSS in the HTML payload — <style> tags work correctly via innerHTML.
        self::ensure_styles(null, true);
        
        self::render([
            'product_id'      => $product_id,
            'express_methods' => $methods,
            'container_id'    => $container_id,
            'boot_script'     => false, // JS dispatches the mount event after injection
        ]);
        
        $html = (string) ob_get_clean();

        wp_send_json_success([
            'html'         => $html,
            'container_id' => $container_id,
            'product_id'   => $product_id,
            'methods'      => $methods_raw,
        ]);
    }

    /**
     * Render the payment form.
     *
     * @param array $args {
     *   @type int    $product_id       WooCommerce product ID (required).
     *   @type string $container_id     HTML id for the express buttons container. Default 'gwc-paypal-express'.
     *   @type bool   $show_express     Whether to render Apple Pay / Google Pay / PayPal buttons. Default true.
     *   @type array  $express_methods  Which wallet methods to request. Default ['applepay','googlepay','paypal'].
     *   @type bool   $show_card        Whether to render the card hosted fields form. Default true.
     *   @type bool   $show_divider     Whether to show the "— or pay with card —" separator. Default true.
     *   @type bool   $show_loader      Whether to show a spinner overlay while the form loads. Default true.
     *   @type string $submit_label     Card form submit button text.
     * }
     */
    public static function render(array $args = []): void
    {
        // Skip asset enqueueing during AJAX — headers already sent / no DOM to inject into.
        if (!wp_doing_ajax()) {
            self::ensure_assets();
        }

        $product_id      = (int)   ($args['product_id']      ?? 0);
        $container_id    = sanitize_html_class($args['container_id'] ?? 'gwc-paypal-express');
        $show_express    = (bool)  ($args['show_express']    ?? true);
        $express_methods = (array) ($args['express_methods'] ?? ['applepay', 'googlepay', 'paypal']);
        $show_card       = (bool)  ($args['show_card']       ?? true);
        $show_divider    = (bool)  ($args['show_divider']    ?? true);
        $show_loader     = (bool)  ($args['show_loader']     ?? true);
        $submit_label    = $args['submit_label'] ?? '';
        $boot_script     = (bool)  ($args['boot_script']     ?? true); // false when rendered via AJAX

        $methods_str = implode(',', array_map('sanitize_text_field', $express_methods));
        ?>
        <div class="gwc-payment-form" data-product-id="<?php echo esc_attr($product_id); ?>">

            <?php if ($show_loader) : ?>
            <!-- Loader shown while PayPal SDK boots -->
            <div class="gwc-payment-form__loader" id="<?php echo esc_attr($container_id); ?>-loader">
                <div class="gwc-hf-spinner"></div>
                <div class="gwc-payment-form__loader-label"><?php _e('Loading payment options…', 'growtype-child'); ?></div>
            </div>
            <?php endif; ?>

            <?php if ($show_express) : ?>
            <!-- Express wallet buttons (Apple Pay / Google Pay / PayPal) -->
            <div id="<?php echo esc_attr($container_id); ?>"
                 class="gwc-payment-form__express"
                 data-product-id="<?php echo esc_attr($product_id); ?>"
                 data-method="<?php echo esc_attr($methods_str); ?>"
                 style="min-height:48px;">
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
            <div class="gwc-payment-form__card">
                <?php Growtype_Wc_Payment_Gateway_Paypal_Card_Form::render([
                    'submit_label' => $submit_label,
                ]); ?>
            </div>
            <?php endif; ?>

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
        $style_handle  = 'growtype-wc';
        $script_url    = GROWTYPE_WC_URL_PUBLIC . 'scripts/growtype-wc.js';
        $style_url     = GROWTYPE_WC_URL_PUBLIC . 'styles/growtype-wc.css';
        $version       = defined('GROWTYPE_WC_VERSION') ? GROWTYPE_WC_VERSION : null;

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

        // Print payment-form CSS inline (late path — no stylesheet to attach to).
        self::ensure_styles(null, true);
    }

    /**
     * Ensure payment-form CSS is on the page.
     *
     * @param string|null $style_handle  WP style handle to attach inline CSS to (standard path).
     * @param bool        $force_print   True = print a raw <style> tag (late path).
     */
    private static function ensure_styles(?string $style_handle, bool $force_print): void
    {
        static $styles_printed = false;
        if ($styles_printed) {
            return;
        }
        $styles_printed = true;

        $css = self::payment_form_css();

        if ($force_print) {
            // Late path — output directly.
            printf('<style id="gwc-payment-form-css">%s</style>' . "\n", $css);
        } else {
            // Standard path — attach to the main stylesheet.
            wp_add_inline_style($style_handle, $css);
        }
    }

    /**
     * All CSS needed by the payment form and hosted fields.
     * Defined here so it is co-located with the class that renders the markup.
     */
    private static function payment_form_css(): string
    {
        return <<<'CSS'
:root {
    --gwc-hf-primary:      #4f8ef7;
    --gwc-hf-bg:           #141414;
    --gwc-hf-header-bg:    #0d0d0d;
    --gwc-hf-border:       rgba(255,255,255,0.08);
    --gwc-hf-text-muted:   #888;
    --gwc-hf-error:        #e05c5c;
    --gwc-hf-success:      #4caf50;
    --gwc-hf-radius:       12px;
    --gwc-hf-input-radius: 10px;
}

/* ── Payment Form layout ────────────────────────────────────────────────── */
.gwc-payment-form          { width:100%; position:relative; }
.gwc-payment-form__loader  { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:24px 0; }
.gwc-payment-form__loader-label { font-size:.875rem; opacity:.65; }
.gwc-payment-form__express { width:100%; min-height:48px; }
.gwc-payment-form__divider { display:flex; align-items:center; gap:12px; margin:16px 0; font-size:.8rem; opacity:.55; text-transform:uppercase; letter-spacing:.04em; }
.gwc-payment-form__divider::before,
.gwc-payment-form__divider::after  { content:''; flex:1; height:1px; background:currentColor; opacity:.3; }
.gwc-payment-form__card    { width:100%; }

/* ── Hosted Fields card form ─────────────────────────────────────────────── */
.card_container            { width:100%; }
.gwc-hf-group              { margin-bottom:14px; }
.gwc-hf-row                { display:flex; gap:12px; }
.gwc-hf-row .gwc-hf-group  { flex:1; }
.gwc-hf-label              { display:block; color:#aaa; font-size:12px; font-weight:500; letter-spacing:.5px; text-transform:uppercase; margin-bottom:6px; padding-left:5px; padding-right:5px; }

.gwc-hf-input,
.gwc-hf-frame              { width:100%; height:65px!important; min-height:65px!important; border-radius:8px; color:#fff!important; font-size:15px; box-sizing:border-box; outline:none; transition:border-color .2s,box-shadow .2s; }
.gwc-hf-input              { padding:0 14px; }
.gwc-hf-frame              { position:relative; overflow:hidden; }
.gwc-hf-input:focus,
.gwc-hf-frame.gwc-focused  { border-color:var(--gwc-hf-primary); box-shadow:0 0 0 3px rgba(79,142,247,.15); }
.gwc-hf-frame.gwc-valid    { border-color:var(--gwc-hf-success); }
.gwc-hf-frame.gwc-invalid  { border-color:var(--gwc-hf-error); }

/* Force PayPal Zoid wrappers + iframes to fill the 65 px container */
.gwc-hf-frame div,
.gwc-hf-frame iframe       { position:absolute!important; top:0!important; left:0!important; width:100%!important; height:65px!important; min-height:65px!important; border:none!important; background:transparent!important; }

/* ── Submit button ───────────────────────────────────────────────────────── */
.gwc-hf-submit             { margin-top:10px; width:100%; padding:14px; font-size:18px; font-weight:600; letter-spacing:.5px; border-radius:8px; background:#ff9000; border:none; color:#fff; cursor:pointer; transition:opacity .2s,transform .1s; }
.gwc-hf-submit:hover:not(:disabled) { opacity:.92; }
.gwc-hf-submit:active      { transform:scale(.99); }
.gwc-hf-submit:disabled    { opacity:.5; cursor:not-allowed; }
.gwc-hf-badge              { display:flex; align-items:center; justify-content:center; gap:6px; margin-top:14px; color:#555; font-size:12px; }

/* ── Spinner ─────────────────────────────────────────────────────────────── */
.gwc-hf-spinner            { width:28px; height:28px; border:3px solid rgba(255,255,255,.1); border-top-color:var(--gwc-hf-primary); border-radius:50%; animation:gwc-spin .8s linear infinite; }
@keyframes gwc-spin        { to { transform:rotate(360deg); } }

/* ── Hosted Fields modal ─────────────────────────────────────────────────── */
.gwc-hf-modal-header       { background:var(--gwc-hf-header-bg)!important; border-bottom:1px solid var(--gwc-hf-border)!important; padding:24px 28px!important; display:flex; align-items:center; justify-content:space-between; }
.gwc-hf-header-title-wrap  { display:flex; flex-direction:column; gap:4px; }
.gwc-hf-secure-badge       { display:flex; align-items:center; gap:6px; color:var(--gwc-hf-success); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
.gwc-hf-modal-header .modal-title { color:#fff!important; font-size:20px!important; font-weight:700!important; margin:0!important; letter-spacing:-.02em; }
.gwc-hf-trust-badges       { display:flex; align-items:center; gap:7px; margin-right:30px; }
.gwc-hf-trust-divider      { width:1px; height:14px; background:rgba(255,255,255,.12); }
.gwc-hf-footer-badge       { display:flex; align-items:center; justify-content:center; gap:8px; margin-top:24px; color:#555; font-size:11px; font-weight:500; }
.gwc-hf-footer-badge svg   { color:var(--gwc-hf-success); }

/* ── Misc ────────────────────────────────────────────────────────────────── */
.btn-addtocart.processing  { opacity:.5!important; pointer-events:none!important; }
.modal .modal-header .btn-close { color:#7b7b7b; }
CSS;
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

        $paypal_client_id   = '';
        $paypal_merchant_id = '';
        $paypal_test_mode   = false;
        $paypal_enabled     = false;

        $gateways = (function_exists('WC') && WC() && WC()->payment_gateways())
            ? WC()->payment_gateways()->payment_gateways()
            : [];

        $paypal_gateway = $gateways[Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID] ?? null;
        if ($paypal_gateway) {
            $paypal_enabled     = $paypal_gateway->is_available();
            $paypal_client_id   = $paypal_gateway->get_client_id();
            $paypal_merchant_id = $paypal_gateway->get_merchant_id();
            $paypal_test_mode   = $paypal_gateway->is_test_mode();
        }

        $data = wp_json_encode([
            'url'    => admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce('growtype_wc_ajax_nonce'),
            'paypal' => [
                'enabled'      => $paypal_enabled,
                'client_id'    => $paypal_client_id,
                'merchant_id'  => $paypal_merchant_id,
                'test_mode'    => $paypal_test_mode,
                'country_code' => wc_get_base_location()['country'] ?? 'US',
                'nonce'        => wp_create_nonce('gwc_paypal_hosted_fields'),
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
                        provider:  'paypal',
                        type:      'mount_express',
                        container: '#<?php echo esc_js($container_id); ?>',
                        productId: <?php echo (int) $product_id; ?>,
                        method:    '<?php echo esc_js($methods_str); ?>',
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
