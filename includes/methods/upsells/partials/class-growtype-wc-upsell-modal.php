<?php

class Growtype_Wc_Upsell_Modal
{
    public static function init()
    {
        if (apply_filters('growtype_wc_upsell_modal_enabled', true)) {
            add_action('wp_footer', [self::class, 'render_modal'], 40);
            add_action('wp_footer', [self::class, 'render_instant_charge_assets'], 50);
            add_action('wp_ajax_' . Growtype_Wc_Upsell::DISMISS_AJAX_ACTION, [self::class, 'ajax_dismiss_upsell']);
            add_action('wp_ajax_' . Growtype_Wc_Upsell::GET_ITEM_AJAX_ACTION, [self::class, 'ajax_get_upsell_item']);
        }
    }

    public static function render_instant_charge_assets()
    {
        ?>
        <style>
            @keyframes gwc-spin { to { transform: rotate(360deg); } }
            .gwc-instant-charge-btn.gwc-loading { opacity: 0.75; pointer-events: none; cursor: wait; }
            .gwc-btn-spinner { display:none; width:18px; height:18px; border:2px solid rgba(255,255,255,0.4); border-top-color:#fff; border-radius:50%; animation:gwc-spin 0.7s linear infinite; flex-shrink:0; }

            /* Upsell success alert — fixed bottom-center */
            #gwc-upsell-alert-wrap {
                position: fixed;
                top: 30px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 99999;
                min-width: 320px;
                max-width: 90vw;
            }
            
            #gwc-upsell-alert-wrap .alert {
                margin: 0;
                box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            }
        </style>
        <div id="gwc-upsell-alert-wrap">
            <div id="gwc-upsell-alert"
                 class="alert alert-success alert-dismissible fade"
                 role="alert"
                 style="display:none;">
                <?php echo esc_html__('Payment successful! Your purchase is confirmed.', 'growtype-wc'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo esc_attr__('Close', 'growtype-wc'); ?>"></button>
            </div>
        </div>
        <script>
        (function() {
            // Spinner + double-click prevention
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.gwc-instant-charge-btn');
                if (!btn) return;
                if (btn.dataset.clicked) { e.preventDefault(); return; }
                btn.dataset.clicked = '1';
                btn.classList.add('gwc-loading');
                var spinner = btn.querySelector('.gwc-btn-spinner');
                if (spinner) spinner.style.display = 'inline-block';
            }, true);

            // Success alert
            var params = new URLSearchParams(window.location.search);
            if (params.get('gwc_upsell_success') === '1') {
                // Clean the URL without reload
                params.delete('gwc_upsell_success');
                var cleanUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
                window.history.replaceState({}, '', cleanUrl);

                // Show Bootstrap alert
                var alertEl = document.getElementById('gwc-upsell-alert');
                if (alertEl) {
                    alertEl.style.display = 'block';
                    setTimeout(function() { alertEl.classList.add('show'); }, 50);
                    setTimeout(function() {
                        alertEl.classList.remove('show');
                        setTimeout(function() { alertEl.style.display = 'none'; }, 400);
                    }, 5000);
                }
            }
        })();
        </script>
        <?php
    }

    public static function render_modal()
    {
        $user_id = get_current_user_id();

        if (self::is_forced_display()) {
            $queue_ids = self::get_forced_queue_ids();
            $is_allowed = true;
        } else {
            if (!is_user_logged_in()) {
                return;
            }

            $queue_ids = Growtype_Wc_Upsell_Queue::get_product_ids($user_id);
            $is_allowed = apply_filters('growtype_wc_upsell_modal_render_allowed', !empty($queue_ids));
        }

        if (!$is_allowed) {
            return;
        }

        $current_url = Growtype_Wc_Upsell_Return_Url::get_current_request_url();

        if (empty($current_url)) {
            return;
        }

        $modal_args = apply_filters('growtype_wc_upsell_modal_args', [
            'modal_id' => Growtype_Wc_Upsell::MODAL_ID,
            'ajax_action' => Growtype_Wc_Upsell::DISMISS_AJAX_ACTION,
            'ajax_get_item_action' => Growtype_Wc_Upsell::GET_ITEM_AJAX_ACTION,
            'ajax_nonce' => wp_create_nonce('growtype_wc_ajax_nonce'),
            'upsell_ids' => $queue_ids,
            'auto_show_delay' => 0,
        ], $user_id);

        echo growtype_wc_include_view('components.modal.upsell', $modal_args);
    }

    public static function ajax_get_upsell_item()
    {
        check_ajax_referer('growtype_wc_ajax_nonce', 'nonce');

        $product_id = absint(wp_unslash($_GET['product_id'] ?? 0));

        if ($product_id < 1) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'growtype-wc')], 400);
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(['message' => __('Product not found.', 'growtype-wc')], 404);
        }

        $current_url = Growtype_Wc_Upsell_Return_Url::sanitize(wp_unslash($_GET['current_url'] ?? ''));

        if (empty($current_url)) {
            $current_url = home_url('/');
        }

        wp_send_json_success(self::format_upsell_item($product, $current_url));
    }

    public static function ajax_dismiss_upsell()
    {
        check_ajax_referer('growtype_wc_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('Unauthorized.', 'growtype-wc'),
            ], 403);
        }

        $product_id = absint(wp_unslash($_POST['product_id'] ?? 0));

        if ($product_id < 1) {
            wp_send_json_error([
                'message' => __('Invalid upsell.', 'growtype-wc'),
            ], 400);
        }

        $user_id = get_current_user_id();
        $queue_ids = Growtype_Wc_Upsell_Queue::dismiss_product($user_id, $product_id);

        wp_send_json_success(apply_filters('growtype_wc_dismiss_upsell_response', [
            'queue_length' => count($queue_ids),
            'product_id' => $product_id,
        ], $user_id));
    }

    private static function format_upsell_item($product, $return_url): array
    {
        $product_id = $product->get_id();
        $image_url = self::get_product_image_url($product);

        $item_data = [
            'product_id' => $product_id,
            'title' => $product->get_title(),
            'short_description' => apply_filters('growtype_the_content', $product->get_short_description()),
            'description' => apply_filters('growtype_the_content', $product->get_description()),
            'price_html' => $product->get_price_html(),
            'promo_label_html' => Growtype_Wc_Product::get_promo_label_formatted($product_id),
            'discount_label_html' => Growtype_Wc_Product::get_discount_percentage_label_formatted($product_id),
            'extra_details_html' => Growtype_Wc_Product::get_extra_details_formatted($product_id),
            'image_url' => $image_url,
            'button_html' => self::render_payment_button($product, $return_url),
        ];

        return apply_filters('growtype_wc_upsell_modal_item_data', $item_data, $product, $return_url);
    }

    private static function get_product_image_url($product): string
    {
        $image_id = $product->get_image_id();

        if (!empty($image_id)) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');

            if (!empty($image_url)) {
                return $image_url;
            }
        }

        return '';
    }

    private static function render_payment_button($product, $return_url): string
    {
        $product_id = $product->get_id();
        $button_text = get_post_meta($product_id, '_add_to_cart_button_custom_text', true);

        if (empty($button_text)) {
            $price = $product->get_price();
            $currency_symbol = get_woocommerce_currency_symbol();
            $button_text = sprintf(__('Unlock Now - %s%s', 'growtype-wc'), $currency_symbol, $price);
        }

        $return_url = Growtype_Wc_Upsell_Return_Url::sanitize($return_url);
        $return_query_key = Growtype_Wc_Upsell_Return_Url::get_query_arg_name();
        $saved_payment_url = '';

        $stripe_enabled = function_exists('growtype_wc_payment_method_is_enabled')
            && growtype_wc_payment_method_is_enabled(Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID);

        $paypal_enabled = function_exists('growtype_wc_payment_method_is_enabled')
            && growtype_wc_payment_method_is_enabled(Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID);

        // ── Instant charge: check Stripe vault first, then PayPal vault ──────
        if (is_user_logged_in() && class_exists('Growtype_Wc_Payment')) {
            if ($stripe_enabled && Growtype_Wc_Payment::user_can_repeat_purchase_for_provider(Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID)) {
                $url = Growtype_Wc_Payment::get_repeat_purchase_url_for_provider($product_id, Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID, $return_url);
                if (!empty($url)) {
                    $saved_payment_url = $url;
                }
            }

            if (empty($saved_payment_url) && $paypal_enabled && Growtype_Wc_Payment::user_can_repeat_purchase_for_provider(Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID)) {
                $url = Growtype_Wc_Payment::get_repeat_purchase_url_for_provider($product_id, Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID, $return_url);
                if (!empty($url)) {
                    $saved_payment_url = $url;
                }
            }
        }

        $saved_payment_url = apply_filters('growtype_wc_upsell_modal_payment_url', $saved_payment_url, $product, $return_url);

        // ── No payment provider at all ────────────────────────────────────────
        if (!$stripe_enabled && !$paypal_enabled) {
            $fallback_url = apply_filters('growtype_wc_upsell_modal_fallback_url', '', $product, $return_url);

            if (empty($fallback_url)) {
                return '';
            }

            return '<a href="' . esc_url($fallback_url) . '"
                        class="btn btn-primary btn-lg w-100 mx-auto"
                        style="font-size: 1.05rem; min-height: 50px;">' . esc_html($button_text) . '</a>';
        }

        // ── Build express checkout element (Google Pay / Apple Pay) ───────────
        $fallback_url = add_query_arg([
            'add-to-cart'    => $product_id,
            'payment_method' => $stripe_enabled ? 'gwc-stripe' : 'gwc-paypal',
        ], wc_get_checkout_url());

        if (!empty($return_url)) {
            $fallback_url = add_query_arg([
                $return_query_key => rawurlencode($return_url),
            ], $fallback_url);
        }

        $fallback_url = apply_filters('growtype_wc_upsell_modal_fallback_url', $fallback_url, $product, $return_url);
        $provider = $stripe_enabled ? 'stripe' : 'paypal';
        $methods  = 'applePay,googlePay';

        $express_html = '<div class="growtype-wc-payment-button btn btn-primary btn-lg w-100 mx-auto"
                    style="font-size: 1.05rem; min-height: 50px;"
                    data-provider="' . esc_attr($provider) . '"
                    data-product-id="' . esc_attr($product_id) . '"
                    data-provider-extra-class=""
                    data-method="' . esc_attr($methods) . '"
                    data-type="express"
                    data-label="' . esc_attr($button_text) . '"
                    data-return-url="' . esc_url($return_url) . '"
                    data-fallback="' . esc_url($fallback_url) . '">
                </div>';

        // ── Instant charge only — skip express if saved payment exists ────────
        if (!empty($saved_payment_url)) {
            return '<div class="gwc-upsell-payment-instant">
                        <a href="' . esc_url($saved_payment_url) . '"
                           class="btn btn-primary btn-lg w-100 mx-auto gwc-instant-charge-btn"
                           style="font-size: 1.05rem; min-height: 50px; position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 8px;"
                           data-label="' . esc_attr($button_text) . '">
                            <span class="gwc-btn-label">' . esc_html($button_text) . '</span>
                            <span class="gwc-btn-spinner"></span>
                        </a>
                    </div>';
        }

        return $express_html;
    }

    /**
     * Check if we should force the display via URL parameter.
     */
    public static function is_forced_display(): bool
    {
        return !empty($_GET[Growtype_Wc_Upsell::FORCE_TRIGGER_QUERY_PARAM]);
    }

    /**
     * Build a queue containing all active upsells from the catalog.
     */
    public static function get_forced_queue_ids(): array
    {
        if (!class_exists('Growtype_Wc_Upsell_Catalog')) {
            return [];
        }

        return array_map(function ($product) {
            return $product->get_id();
        }, Growtype_Wc_Upsell_Catalog::get_products());
    }
}
