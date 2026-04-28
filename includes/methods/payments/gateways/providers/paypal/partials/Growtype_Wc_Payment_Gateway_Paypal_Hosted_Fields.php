<?php

/**
 * PayPal Hosted Fields — server-side AJAX handlers.
 *
 * Card data is tokenised entirely inside PayPal-hosted iframes.
 * These endpoints only receive/return order IDs and WC order metadata —
 * no raw card data ever touches this server.
 *
 * Flow:
 *   1. Browser clicks "Pay with Card" → modal opens
 *   2. PayPal JS SDK renders secure card iframes (hosted on paypal.com)
 *   3. User fills card → JS calls ajax_hosted_create_order (POST: product_id)
 *      → Server creates WC order + PayPal order → returns { orderID, wc_order_id }
 *   4. SDK tokenises card → calls ajax_hosted_capture_order (POST: orderID, wc_order_id)
 *      → Server verifies orderID, captures via PayPal REST API → marks WC order paid
 *   5. Browser redirects to WooCommerce thank-you page
 */
class Growtype_Wc_Payment_Gateway_Paypal_Hosted_Fields
{
    /** @var Growtype_Wc_Payment_Gateway_Paypal */
    private $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;

        add_action('wp_ajax_gwc_paypal_hosted_create_order', [$this, 'ajax_hosted_create_order']);
        add_action('wp_ajax_nopriv_gwc_paypal_hosted_create_order', [$this, 'ajax_hosted_create_order']);

        add_action('wp_ajax_gwc_paypal_hosted_capture_order', [$this, 'ajax_hosted_capture_order']);
        add_action('wp_ajax_nopriv_gwc_paypal_hosted_capture_order', [$this, 'ajax_hosted_capture_order']);

        add_action('wp_footer', [$this, 'render_paypal_hosted_fields_modal']);
    }

    /**
     * STEP 1 — Create a WooCommerce order and a corresponding PayPal order.
     * Called by the browser before PayPal tokenises the card.
     *
     * POST params: product_id (int), billing_email (string), nonce
     * Returns JSON: { orderID: string, wc_order_id: int }
     */
    public function ajax_hosted_create_order()
    {
        if (!check_ajax_referer('gwc_paypal_hosted_fields', '_ajax_nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'growtype-wc')], 403);
        }

        $product_id    = absint($_POST['product_id'] ?? 0);
        $billing_email = sanitize_email($_POST['billing_email'] ?? '');
        // Whitelist vault_source to prevent arbitrary values reaching build_vault_payment_source()
        $vault_source_raw = sanitize_text_field($_POST['vault_source'] ?? 'card');
        $vault_source     = in_array($vault_source_raw, ['card', 'paypal'], true) ? $vault_source_raw : 'card';

        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product.', 'growtype-wc')], 400);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => __('Product not found.', 'growtype-wc')], 400);
        }

        // Build the WooCommerce order
        $order = wc_create_order();
        $order->add_product($product, 1);
        $order->set_payment_method($this->gateway->id);
        $order->set_payment_method_title($this->gateway->get_hosted_fields_title());

        if (is_user_logged_in()) {
            $order->set_customer_id(get_current_user_id());
            $billing_email = wp_get_current_user()->user_email;
        }

        if (!empty($billing_email)) {
            $order->set_billing_email($billing_email);
        }

        // Apply any active cart coupons
        $applied_coupons = WC()->cart ? WC()->cart->get_applied_coupons() : [];
        foreach ($applied_coupons as $coupon) {
            $order->apply_coupon($coupon);
        }

        $order->calculate_totals();
        $order->update_status('pending', __('Awaiting PayPal Hosted Fields payment.', 'growtype-wc'));
        $order->save();

        $wc_order_id = $order->get_id();

        // Create the PayPal order via REST API
        try {
            $access_token = $this->gateway->get_access_token(
                $this->gateway->get_client_id(),
                $this->gateway->get_client_secret()
            );

            if (empty($access_token)) {
                throw new \Exception('Could not retrieve PayPal access token.');
            }

            $paypal_order = $this->gateway->create_order($access_token, $wc_order_id, $applied_coupons, $vault_source);

            if (empty($paypal_order['id'])) {
                $detail = $paypal_order['details'][0]['description'] ?? 'No details returned.';
                throw new \Exception('PayPal order creation failed: ' . $detail);
            }
        } catch (\Exception $e) {
            $order->update_status('failed', $e->getMessage());
            error_log('GWC PayPal Hosted Fields - create_order error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Could not connect to PayPal. Please try again.', 'growtype-wc')], 500);
        }

        // Persist the PayPal order ID for later verification (prevents orderID substitution)
        $order->update_meta_data('_paypal_hosted_order_id', sanitize_text_field($paypal_order['id']));
        $order->save();

        wp_send_json_success([
            'orderID'       => $paypal_order['id'],
            'wc_order_id'   => $wc_order_id,
            'amount'        => number_format((float) $order->get_total(), 2, '.', ''),
            'currency_code' => get_woocommerce_currency(),
        ]);
    }

    /**
     * STEP 2 — Capture the approved PayPal order and complete the WC order.
     * Called after the PayPal JS SDK has tokenised the card and obtained approval.
     *
     * POST params: orderID (string), wc_order_id (int), nonce
     * Returns JSON: { redirect: string } on success
     */
    public function ajax_hosted_capture_order()
    {
        if (!check_ajax_referer('gwc_paypal_hosted_fields', '_ajax_nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'growtype-wc')], 403);
        }

        $paypal_order_id = sanitize_text_field($_POST['paypal_order_id'] ?? '');
        $wc_order_id     = absint($_POST['wc_order_id'] ?? 0);

        if (!$paypal_order_id || !$wc_order_id) {
            wp_send_json_error(['message' => __('Missing parameters.', 'growtype-wc')], 400);
        }

        error_log(sprintf('[GWC PayPal Capture] ajax_hosted_capture_order: wc_order_id=%d paypal_order_id=%s', $wc_order_id, $paypal_order_id));

        $order = wc_get_order($wc_order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'growtype-wc')], 404);
        }

        // Security: verify the PayPal order ID matches what we stored — prevents orderID substitution attacks
        $stored_paypal_id = $order->get_meta('_paypal_hosted_order_id');
        if ($stored_paypal_id !== $paypal_order_id) {
            error_log("GWC PayPal Hosted Fields - orderID mismatch for WC order {$wc_order_id}. Stored: {$stored_paypal_id}, received: {$paypal_order_id}");
            wp_send_json_error(['message' => __('Payment verification failed.', 'growtype-wc')], 403);
        }

        // Prevent double-capture — use a transient lock to handle concurrent requests
        $lock_key = 'gwc_paypal_capture_lock_' . $wc_order_id;
        if (get_transient($lock_key)) {
            // Another request is already processing this capture
            if ($order->is_paid()) {
                wp_send_json_success(['redirect' => Growtype_Wc_Payment_Gateway::success_url($wc_order_id)]);
            }
            wp_send_json_error(['message' => __('Payment is already being processed. Please wait.', 'growtype-wc')], 409);
        }

        set_transient($lock_key, 1, 30); // 30-second lock

        if ($order->is_paid()) {
            delete_transient($lock_key);
            wp_send_json_success(['redirect' => Growtype_Wc_Payment_Gateway::success_url($wc_order_id)]);
        }

        try {
            $access_token   = $this->gateway->get_access_token(
                $this->gateway->get_client_id(),
                $this->gateway->get_client_secret()
            );

            if (empty($access_token)) {
                error_log('[GWC PayPal Capture] FAIL: empty access token returned.');
                throw new \Exception('Could not retrieve PayPal access token.');
            }
            
            $capture_result = $this->gateway->capture_order($access_token, $paypal_order_id);

            $status = $capture_result['status'] ?? '';

            if ($status !== 'COMPLETED') {
                $detail = $capture_result['details'][0]['description'] ?? $capture_result['message'] ?? 'Capture failed.';
                throw new \Exception($detail);
            }

            // Extract the capture transaction ID
            $capture_id = $capture_result['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';

            // Debug-only: log payment_source type (never log full response — may contain card/vault data)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[GWC Vault] payment_source types: ' . implode(', ', array_keys($capture_result['payment_source'] ?? [])));
            }

            $vault_id       = $capture_result['payment_source']['card']['attributes']['vault']['id'] ?? '';
            $pp_customer_id = $capture_result['payment_source']['card']['attributes']['vault']['customer']['id'] ?? '';

            // Also try google_pay and paypal paths in case payment_source type differs
            if (empty($vault_id)) {
                $vault_id       = $capture_result['payment_source']['google_pay']['card']['attributes']['vault']['id'] ?? '';
                $pp_customer_id = $capture_result['payment_source']['google_pay']['card']['attributes']['vault']['customer']['id'] ?? '';
            }
            if (empty($vault_id)) {
                $vault_id       = $capture_result['payment_source']['paypal']['attributes']['vault']['id'] ?? '';
                $pp_customer_id = $capture_result['payment_source']['paypal']['attributes']['vault']['customer']['id'] ?? '';
            }

            error_log('[GWC Vault] Hosted Fields capture complete: order=' . $wc_order_id . ' capture_id=' . $capture_id . ' vault=' . (!empty($vault_id) ? 'yes' : 'no'));

            $order->update_meta_data('_paypal_capture_id', sanitize_text_field($capture_id));

            if (!empty($vault_id)) {
                $order->update_meta_data('paypal_vault_id', sanitize_text_field($vault_id));
                $order->update_meta_data('paypal_vault_type', 'card');
            }
            if (!empty($pp_customer_id)) {
                $order->update_meta_data('paypal_customer_id', sanitize_text_field($pp_customer_id));
            }

            $order->save();
            $order->payment_complete($capture_id);

            // Persist vault info on user meta so it's available for any future order
            $user_id = (int)$order->get_customer_id();
            if ($user_id > 0) {
                if (!empty($vault_id)) {
                    update_user_meta($user_id, 'paypal_vault_id', sanitize_text_field($vault_id));
                    update_user_meta($user_id, 'paypal_vault_type', 'card');
                    error_log(sprintf('[GWC Vault] Hosted Fields: stored vault_id=%s type=card for user %d', $vault_id, $user_id));
                }
                if (!empty($pp_customer_id)) {
                    update_user_meta($user_id, 'paypal_customer_id', sanitize_text_field($pp_customer_id));
                }
            }

            if (WC()->cart) {
                WC()->cart->empty_cart();
            }
        } catch (\Exception $e) {
            $order->update_status('failed', $e->getMessage());
            error_log('GWC PayPal Hosted Fields - capture_order error: ' . $e->getMessage());
            delete_transient($lock_key);
            wp_send_json_error(['message' => __('Payment capture failed. Please try again or contact support.', 'growtype-wc')], 500);
        }

        delete_transient($lock_key);
        
        wp_send_json_success([
            'redirect' => Growtype_Wc_Payment_Gateway::success_url($wc_order_id),
        ]);
    }

    /**
     * Render the PayPal Hosted Fields modal in the footer.
     * Card data is entered inside PayPal-hosted iframes and never touches our server.
     */
    public function render_paypal_hosted_fields_modal()
    {
        if (!growtype_wc_is_payment_page()) {
            return;
        }

        if (!$this->gateway || empty($this->gateway->enable_card_payments)) {
            return;
        }

        $client_id  = $this->gateway->get_client_id();
        $merchant_id = $this->gateway->get_merchant_id();
        $is_sandbox = $this->gateway->is_test_mode();
        $currency   = get_woocommerce_currency();
        $nonce      = wp_create_nonce('gwc_paypal_hosted_fields');
        $ajax_url   = admin_url('admin-ajax.php');
        ?>
        <!-- PayPal Hosted Fields Modal -->
        <div class="modal fade" id="gwcPaypalHostedFieldsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
                <div class="modal-content" style="background:#141414;border:1px solid #222;border-radius:12px;overflow:hidden">
                    <div class="modal-header gwc-hf-modal-header">
                        <div class="gwc-hf-header-title-wrap">
                            <div class="gwc-hf-secure-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                Secure Checkout                            </div>
                            <h5 class="modal-title">Pay with Card</h5>
                        </div>

<div class="gwc-hf-trust-badges">
                            <div class="gwc-hf-trust-item">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="20" rx="3" fill="#1A1F71"></rect><path d="M11.9733 13.0645L13.1256 6.33125H14.9702L13.8179 13.0645H11.9733ZM19.7428 6.47167C19.3496 6.29417 18.7303 6.11125 17.9547 6.11125C16.1408 6.11125 14.8569 7.02708 14.846 8.32958C14.8368 9.29417 15.7534 9.83333 16.4526 10.1583C17.1691 10.4908 17.4111 10.7021 17.408 11.0117C17.4034 11.4871 16.808 11.6967 16.2713 11.6967C15.4851 11.6967 15.0113 11.4954 14.6544 11.3417L14.3983 12.545C14.7506 12.6975 15.3998 12.8333 16.0751 12.8333C17.9908 12.8333 19.2555 11.9325 19.2662 10.5183C19.2743 9.35125 18.5036 8.8475 17.5147 8.3975C16.8837 8.10542 16.6644 7.92542 16.6669 7.64125C16.6669 7.32042 17.0494 6.96917 17.8863 6.96917C18.5724 6.96917 19.0189 7.11208 19.3879 7.2625L19.7428 6.47167ZM23.8208 6.33125C23.4184 6.33125 23.084 6.55167 22.9284 6.90375L20.1983 13.0645H22.1373L22.5229 12.0621H24.8931L25.12 13.0645H26.8334L25.3404 6.33125H23.8208ZM23.0768 10.6121L23.708 8.98375L24.0723 10.6121H23.0768ZM10.5905 6.33125L8.78317 10.9329L8.59107 10.0246C8.25413 8.94833 7.23431 7.7475 6.13677 7.19917L7.84236 13.0633H9.79198L12.6896 6.33125H10.5905ZM6.42945 6.33125H3.14154L3.10955 6.48125C5.59737 7.08042 7.23888 8.54417 7.91502 10.2871L7.26593 7.21417C7.15941 6.64333 6.8458 6.36 6.42945 6.33125Z" fill="white"></path></svg>
                            </div>
                            <div class="gwc-hf-trust-item">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="20" rx="3" fill="#222222"></rect><path d="M12.4444 10C12.4444 11.5165 11.7588 12.8719 10.6875 13.7845C9.88086 14.4714 8.84157 14.8889 7.70833 14.8889C5.51239 14.8889 3.72517 13.2081 3.52479 11.0556H3.33333V8.94444H3.52479C3.72517 6.79194 5.51239 5.11111 7.70833 5.11111C8.84157 5.11111 9.88086 5.52864 10.6875 6.21553C11.7588 7.12814 12.4444 8.48353 12.4444 10Z" fill="#EB001B"></path><path d="M22.0833 10C22.0833 8.48353 21.3977 7.12814 20.3264 6.21553C19.5197 5.52864 18.4804 5.11111 17.3472 5.11111C15.1513 5.11111 13.364 6.79194 13.1636 8.94444H12.9722V11.0556H13.1636C13.364 13.2081 15.1513 14.8889 17.3472 14.8889C18.4804 14.8889 19.5197 14.4714 20.3264 13.7845C21.3977 12.8719 22.0833 11.5165 22.0833 10Z" fill="#F79E1B"></path><path d="M13.1636 10C13.1636 8.48353 13.8492 7.12814 14.9205 6.21553C15.6599 5.58614 16.6074 5.20764 17.6364 5.13283C16.5651 4.22022 15.1613 3.66667 13.625 3.66667C11.4291 3.66667 9.64183 5.3475 9.44145 7.5H9.25V12.5H9.44145C9.64183 14.6525 11.4291 16.3333 13.625 16.3333C15.1613 16.3333 16.5651 15.7798 17.6364 14.8672C16.6074 14.7924 15.6599 14.4139 14.9205 13.7845C13.8492 12.8719 13.1636 11.5165 13.1636 10Z" fill="#FF5F00"></path></svg>
                            </div>
                            <div class="gwc-hf-trust-item">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="20" rx="3" fill="#0070BA"></rect><path d="M22.5 7.5L20 12.5H23L25.5 7.5H22.5ZM17.5 7.5L15 12.5H18L20.5 7.5H17.5ZM12.5 7.5L10 12.5H13L15.5 7.5H12.5ZM7.5 7.5L5 12.5H8L10.5 7.5H7.5Z" fill="white"></path></svg>
                            </div>
                            <div class="gwc-hf-trust-divider"></div>
                            <div class="gwc-hf-trust-item pci-badge">
                                <svg width="40" height="12" viewBox="0 0 40 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 0H0V12H3.5V0ZM8.5 0H5V12H8.5V0ZM13.5 0H10V12H13.5V0Z" fill="#888"></path><text x="15" y="10" fill="#888" font-family="sans-serif" font-size="8" font-weight="bold">PCI DSS</text></svg>
                            </div>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="background:#141414;padding:20px 24px">
                        <div id="gwc-paypal-not-eligible" style="display:none;color:#e05c5c;padding:12px;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.3);border-radius:8px;margin-bottom:16px;font-size:13px">
                            <?php _e('Advanced card payments are not available for this account. Please use the PayPal button instead.', 'growtype-child'); ?>
                        </div>

                        <div id="gwc-paypal-fields-wrap" style="position:relative; min-height: 250px;">
                            <!-- Form Loader Overlay -->
                            <div id="gwc-paypal-form-loader" style="position:absolute; top:0; left:0; width:100%; height:100%; background:#141414; z-index:100; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; border-radius:8px;">
                                <div class="gwc-hf-spinner"></div>
                                <div style="color:#666; font-size:12px; font-weight:500; text-transform:uppercase; letter-spacing:1px;"><?php _e('Loading...', 'growtype-child'); ?></div>
                            </div>

                            <div id="card-form" class="card_container">
                                <div class="gwc-hf-group">
                                    <label class="gwc-hf-label"><?php _e('Cardholder name', 'growtype-child'); ?> <span style="color:#e05c5c">*</span></label>
                                    <div id="card-name-field-container" class="gwc-hf-frame"></div>
                                </div>
                                <div class="gwc-hf-group">
                                    <label class="gwc-hf-label"><?php _e('Card Number', 'growtype-child'); ?> <span style="color:#e05c5c">*</span></label>
                                    <div id="card-number-field-container" class="gwc-hf-frame"></div>
                                </div>
                                <div class="gwc-hf-row">
                                    <div class="gwc-hf-group">
                                        <label class="gwc-hf-label"><?php _e('Expiry (MM/YY)', 'growtype-child'); ?> <span style="color:#e05c5c">*</span></label>
                                        <div id="card-expiry-field-container" class="gwc-hf-frame"></div>
                                    </div>
                                    <div class="gwc-hf-group">
                                        <label class="gwc-hf-label"><?php _e('CVV', 'growtype-child'); ?> <span style="color:#e05c5c">*</span></label>
                                        <div id="card-cvv-field-container" class="gwc-hf-frame"></div>
                                    </div>
                                </div>

                                <div id="gwc-hf-errors" style="display:none;margin-bottom:5px;padding:10px 14px;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.3);border-radius:6px;color:#e05c5c;font-size:13px"></div>

                                <button id="card-field-submit-button" class="gwc-hf-submit" disabled>
                                     <?php _e('Complete Secure Payment', 'growtype-child'); ?> <span style="position:relative;top:-2px;"><svg class="gwc-hf-lock-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>
                                </button>

                                <div class="gwc-hf-footer-badge">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                                    <span>Your data is encrypted and never stored on our servers.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>

:root {
    --gwc-hf-primary: #4f8ef7;
    --gwc-hf-bg: #141414;
    --gwc-hf-header-bg: #0d0d0d;
    --gwc-hf-border: rgba(255, 255, 255, 0.08);
    --gwc-hf-text-muted: #888;
    --gwc-hf-error: #e05c5c;
    --gwc-hf-success: #4caf50;
    --gwc-hf-radius: 12px;
    --gwc-hf-input-radius: 10px;
    --gwc-hf-font: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif;
}

.modal .modal-header .btn-close {
   color: #7b7b7b;
}

            .gwc-hf-group  { margin-bottom:14px; }
            .gwc-hf-row    { display:flex; gap:12px; }
            .gwc-hf-row .gwc-hf-group { flex:1; }
            .gwc-hf-label  { display:block; color:#aaa; font-size:12px; font-weight:500; letter-spacing:.5px; text-transform:uppercase; margin-bottom:6px;padding-left:5px;padding-right:5px; }
            /* Unified style for all inputs (standard and iframe) */
            .gwc-hf-input, .gwc-hf-frame { width:100%; height:65px !important; min-height:65px !important; border-radius:8px; color:#fff !important; font-size:15px; box-sizing:border-box; outline:none; transition:border-color .2s,box-shadow .2s; }
            .gwc-hf-input { padding:0 14px; }
            .gwc-hf-frame { position:relative; overflow:hidden;    
                /* margin-left: -5px;
    width: calc(100% + 12px);  */
}

            .gwc-hf-input:focus, .gwc-hf-frame.gwc-focused { border-color:#4f8ef7; box-shadow:0 0 0 3px rgba(79,142,247,.15); }
            .gwc-hf-frame.gwc-valid   { border-color:#4caf50; }
            .gwc-hf-frame.gwc-invalid { border-color:#e05c5c; }

            /* Spinner styling */
            .gwc-hf-spinner {
                width: 28px;
                height: 28px;
                border: 3px solid rgba(255,255,255,0.1);
                border-top-color: #4f8ef7;
                border-radius: 50%;
                animation: gwc-spin 0.8s linear infinite;
            }
            @keyframes gwc-spin {
                to { transform: rotate(360deg); }
            }

            /* Force PayPal's iframes and all their wrappers to be exactly 65px */
            .gwc-hf-frame div, 
            .gwc-hf-frame iframe { 
                position: absolute !important; 
                top: 0 !important; 
                left: 0 !important; 
                width: 100% !important; 
                height: 65px !important; 
                min-height: 65px !important;
                border: none !important; 
                background: transparent !important;
            }

            .gwc-hf-submit {margin-top:10px; width:100%; padding:14px; font-size:18px; font-weight:600; letter-spacing:.5px; border-radius:8px; background:#ff9000; border:none; color:#fff; cursor:pointer; transition:opacity .2s,transform .1s; }
            .gwc-hf-submit:hover:not(:disabled) { opacity:.92; }
            .gwc-hf-submit:active  { transform:scale(.99); }
            .gwc-hf-submit:disabled { opacity:.5; cursor:not-allowed; }
            .gwc-hf-badge  { display:flex; align-items:center; justify-content:center; gap:6px; margin-top:14px; color:#555; font-size:12px; }
            .btn-addtocart.processing { opacity:.5!important; pointer-events:none!important; }

            .gwc-hf-footer-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 24px;
    color: #555;
    font-size: 11px;
    font-weight: 500;
}

.gwc-hf-footer-badge svg {
    color: #4caf50;
}

.gwc-hf-modal-header {
    background: var(--gwc-hf-header-bg) !important;
    border-bottom: 1px solid var(--gwc-hf-border) !important;
    padding: 24px 28px !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.gwc-hf-header-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.gwc-hf-secure-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #4caf50;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.gwc-hf-modal-header .modal-title {
    color: #fff !important;
    font-size: 20px !important;
    font-weight: 700 !important;
    margin: 0 !important;
    letter-spacing: -0.02em;
}

.gwc-hf-trust-badges {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-right: 30px;
}
        </style>

        <script>
        (function($) {
            var gwcPaypalClientId = <?php echo wp_json_encode($client_id); ?>;
            var gwcPaypalMerchantId = <?php echo wp_json_encode($merchant_id); ?>;
            var gwcPaypalSandbox  = <?php echo $is_sandbox ? 'true' : 'false'; ?>;
            var gwcAjaxUrl       = <?php echo wp_json_encode($ajax_url); ?>;
            var gwcNonce         = <?php echo wp_json_encode($nonce); ?>;
            var gwcCurrency      = <?php echo wp_json_encode($currency); ?>;
            var gwcProductId     = 0;
            var gwcWcOrderId     = 0;

            // Card Fields instance
            var cardFields = null;

            // ── Prevent double-clicks on all payment buttons ──────────────────
            $(document).on('click', '.btn-addtocart', function(e) {
                var $b = $(this);
                if ($b.hasClass('processing')) { e.preventDefault(); return false; }
                $b.addClass('processing');
            });

            // ── Open modal when the PayPal Card button is clicked ─────────────
            $(document).on('click', '.btn-show-paypal-card', function(e) {
                e.preventDefault();
                var $btn = $(this);

                // If a vault token is available the button carries data-instant-charge="1"
                // and its href already points to the charge_intent action — just follow it.
                if ($btn.data('instant-charge') == '1') {
                    var chargeUrl = $btn.attr('href');
                    if (chargeUrl) {
                        $btn.addClass('processing');
                        window.location.href = chargeUrl;
                    }
                    return;
                }

                gwcProductId = parseInt($btn.data('product-id'), 10) || 0;

                var modalEl = document.getElementById('gwcPaypalHostedFieldsModal');
                if (window.bootstrap && window.bootstrap.Modal) {
                    new bootstrap.Modal(modalEl).show();
                } else if ($.fn.modal) {
                    $(modalEl).modal('show');
                }

                // Initialise Card Fields when modal opens
                loadPaypalSdk(function() {
                    initCardFields();
                });

                $btn.removeClass('processing');
            });

            // Reset Card Fields when modal is closed so styles re-apply on reopen
            $(document).on('hidden.bs.modal', '#gwcPaypalHostedFieldsModal', function() {
                cardFields = null;
                $('#card-name-field-container, #card-number-field-container, #card-expiry-field-container, #card-cvv-field-container').empty().height(65);
                $('#gwc-hf-errors').hide();
                $('#gwc-paypal-form-loader').show(); // Show loader again for next time
                $('#card-field-submit-button').prop('disabled', true).text('<?php echo esc_js(__('Pay now with Card', 'growtype-child')); ?>');
            });

            // ── Load PayPal JS SDK and initialise Card Fields ────────────────
            function loadPaypalSdk(callback) {
                // window.paypal may already exist (loaded by Express/GooglePay) but WITHOUT
                // card-fields component — we must load our own namespaced instance.
                if (window.paypal_gwc && window.paypal_gwc.CardFields) {
                    console.log('[GWC HF] paypal_gwc already loaded with CardFields, skipping reload.');
                    callback();
                    return;
                }
                console.log('[GWC HF] Loading PayPal SDK (card-fields) into paypal_gwc namespace...');
                var s = document.createElement('script');
                s.src = 'https://www.paypal.com/sdk/js'
                    + '?client-id=' + encodeURIComponent(gwcPaypalClientId)
                    + (gwcPaypalMerchantId ? '&merchant-id=' + encodeURIComponent(gwcPaypalMerchantId) : '')
                    + '&components=card-fields'
                    + '&intent=capture'
                    + '&currency=' + encodeURIComponent(gwcCurrency)
                    + (gwcPaypalSandbox ? '&debug=true&buyer-country=US' : '');
                console.log('[GWC HF] SDK URL:', s.src);
                s.setAttribute('data-namespace', 'paypal_gwc');
                s.onload  = function() {
                    console.log('[GWC HF] SDK loaded. paypal_gwc.CardFields:', typeof window.paypal_gwc !== 'undefined' ? typeof window.paypal_gwc.CardFields : 'paypal_gwc undefined');
                    callback();
                };
                s.onerror = function() {
                    showError('<?php echo esc_js(__('Failed to load PayPal SDK. Please refresh and try again.', 'growtype-child')); ?>');
                };
                document.head.appendChild(s);
            }

            function showError(msg) {
                var $err = $('#gwc-hf-errors');
                $err.text(msg).show(); // .text() prevents XSS
                $('#card-field-submit-button').prop('disabled', false).text('<?php echo esc_js(__('Pay now with Card', 'growtype-child')); ?>');
            }

            function initCardFields() {
                console.log('[GWC HF] initCardFields called. paypal_gwc:', typeof window.paypal_gwc, 'CardFields:', typeof window.paypal_gwc !== 'undefined' ? typeof window.paypal_gwc.CardFields : 'N/A');

                if (!window.paypal_gwc || !window.paypal_gwc.CardFields) {
                    console.error('[GWC HF] PayPal Card Fields not found in SDK (namespace: paypal_gwc). This usually means the SDK failed to load or the card-fields component was not included.');
                    return;
                }

                if (cardFields) {
                    console.log('[GWC HF] CardFields already initialised, skipping.');
                    return;
                }

                console.log('[GWC HF] Creating CardFields instance...');
                cardFields = window.paypal_gwc.CardFields({
                    createOrder: function() {
                        return createOrderInternal();
                    },
                    onApprove: function(data) {
                        return onApproveInternal(data.orderID);
                    },
                    style: {
                        input: {
                            "font-size":   "16px",
                            "font-family": "-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif",
                            "color":       "#000000",
                            "padding":     "15px 15px"
                        },
                        ".invalid": { "color": "#e05c5c" }
                    }
                });

                console.log('[GWC HF] isEligible:', cardFields.isEligible());

                if (!cardFields.isEligible()) {
                    console.group('[GWC HF] ❌ CardFields NOT eligible — diagnostics');
                    console.error('isEligible() returned false.');
                    console.log('  client-id:   ', gwcPaypalClientId ? gwcPaypalClientId.substring(0, 12) + '...' : 'MISSING');
                    console.log('  merchant-id: ', gwcPaypalMerchantId || 'NOT SET');
                    console.log('  currency:    ', gwcCurrency);
                    console.log('  sandbox:     ', gwcPaypalSandbox);
                    console.log('  Fix: In your PayPal Developer Dashboard → REST App → Features, enable "Advanced Credit and Debit Card Payments".');
                    console.groupEnd();
                    $('#gwc-paypal-not-eligible').show();
                    $('#gwc-paypal-fields-wrap').hide();
                    return;
                }

                // Show the form wrapper before rendering fields into it
                $('#gwc-paypal-not-eligible').hide();
                $('#gwc-paypal-fields-wrap').show();

                // Render individual fields
                console.log('[GWC HF] Rendering card field iframes...');
                Promise.all([
                    cardFields.NameField({ placeholder: '<?php _e('Cardholder Name', 'growtype-child'); ?>' }).render('#card-name-field-container'),
                    cardFields.NumberField({ placeholder: '•••• •••• •••• ••••' }).render('#card-number-field-container'),
                    cardFields.ExpiryField({ placeholder: 'MM / YY' }).render('#card-expiry-field-container'),
                    cardFields.CVVField({ placeholder: '•••' }).render('#card-cvv-field-container')
                ]).then(function() {
                    console.log('[GWC HF] All card fields rendered successfully.');
                    setTimeout(function(){
                       $('#gwc-paypal-form-loader').fadeOut(300);
                    },1000)
                }).catch(function(err) {
                    console.error('[GWC HF] CardFields render error:', err);
                    $('#gwc-paypal-form-loader').hide();
                    showError('<?php echo esc_js(__('Failed to render card fields. Please try again.', 'growtype-child')); ?>');
                });

                // Force 65px height on PayPal's internal structure (Zoid wrappers and iframes)
                var TARGET_H = 65;
                ['#card-name-field-container',
                 '#card-number-field-container',
                 '#card-expiry-field-container',
                 '#card-cvv-field-container'].forEach(function(sel) {
                    var container = document.querySelector(sel);
                    if (!container) return;

                    function forceHeight(reason) {
                        // 1. Force the container itself
                        container.style.setProperty('height', TARGET_H + 'px', 'important');
                        container.style.setProperty('display', 'flex', 'important');
                        container.style.setProperty('align-items', 'center', 'important');
                        container.style.setProperty('overflow', 'hidden', 'important');
                        
                        // 2. Force every child (Zoid wrappers and their nested structures)
                        var descendants = container.querySelectorAll('*');
                        descendants.forEach(function(el) {
                            if (el.tagName === 'IFRAME' && el.name && el.name.indexOf('__detect_close') !== -1) return;
                            if (el.tagName === 'STYLE' || el.tagName === 'SCRIPT') return;
                            
                            // If inline style doesn't match, snap it back immediately
                            if (el.style.height !== (TARGET_H + 'px')) {
                                // console.log('  -> Fixing ' + el.tagName + ' (id:' + (el.id || 'none') + ') to ' + TARGET_H + 'px');
                                el.style.setProperty('height', TARGET_H + 'px', 'important');
                                el.style.setProperty('min-height', TARGET_H + 'px', 'important');
                                el.style.setProperty('max-height', TARGET_H + 'px', 'important');
                            }
                        });
                    }

                    // Observe everything: new elements, style changes, etc.
                    var obs = new MutationObserver(function(mutations) {
                        // console.log('Mutation detected in ' + sel, mutations);
                        forceHeight('mutation');
                    });
                    
                    obs.observe(container, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['style']
                    });

                    // Initial force + delayed force to catch post-render jumps
                    forceHeight('initial');
                    setTimeout(function() { forceHeight('timeout_500'); }, 500);
                    setTimeout(function() { forceHeight('timeout_1500'); }, 1500);
                });

                // Enable submit button
                $('#card-field-submit-button').prop('disabled', false);

                // Helper for Create Order (shared by Buttons and CardFields)
                function createOrderInternal() {
                    return $.post(gwcAjaxUrl, {
                        action: 'gwc_paypal_hosted_create_order',
                        _ajax_nonce: gwcNonce,
                        product_id: gwcProductId,
                        currency: gwcCurrency,
                        vault_source: 'card'
                    }).then(function(res) {
                        if (res.success && res.data.orderID) {
                            gwcWcOrderId = res.data.wc_order_id;
                            return res.data.orderID;
                        }
                        throw new Error(res.data.message || 'Order creation failed');
                    });
                }

                // Helper for On Approve (shared by Buttons and CardFields)
                function onApproveInternal(orderID) {
                    // PayPal has approved — show loader while we capture on server
                    $('#gwc-paypal-form-loader').stop(true, true).show();
                    return $.post(gwcAjaxUrl, {
                        action: 'gwc_paypal_hosted_capture_order',
                        _ajax_nonce: gwcNonce,
                        paypal_order_id: orderID,
                        wc_order_id: gwcWcOrderId
                    }).then(function(res) {
                        if (res.success && res.data.redirect) {
                            window.location.href = res.data.redirect;
                        } else {
                            throw new Error(res.data.message || 'Payment capture failed');
                        }
                    }).catch(function(err) {
                        $('#gwc-paypal-form-loader').hide();
                        $('#card-field-submit-button').prop('disabled', false).text('<?php echo esc_js(__('Pay now with Card', 'growtype-child')); ?>');
                        showError(err.message || '<?php echo esc_js(__('Payment capture failed. Please try again.', 'growtype-child')); ?>');
                    });
                }

                // Submit listener for Card Fields
                $('#card-field-submit-button').off('click').on('click', function(e) {
                    e.preventDefault();
                    if (!gwcProductId) {
                        showError('<?php echo esc_js(__('Please select a plan before paying.', 'growtype-child')); ?>');
                        return;
                    }
                    $('#gwc-hf-errors').hide();
                    $('#card-field-submit-button').prop('disabled', true).text('<?php echo esc_js(__('Processing…', 'growtype-child')); ?>');
                    $('#gwc-paypal-form-loader').stop(true, true).show(); // Show loader while PayPal processes
                    cardFields.submit({
                        // No additional data needed for basic submission
                    }).catch(function(err) {
                        console.error('Submission Error:', err);

                        // Hide loader and re-enable button so user can correct their details
                        $('#gwc-paypal-form-loader').hide();
                        $('#card-field-submit-button').prop('disabled', false).text('<?php echo esc_js(__('Pay now with Card', 'growtype-child')); ?>');

                        var msg = (err && err.message) ? err.message : '';

                        // Map technical error codes to user-friendly messages
                        var errorMap = {
                            'INVALID_NUMBER': '<?php echo esc_js(__('The card number is invalid. Please check and try again.', 'growtype-child')); ?>',
                            'INVALID_EXPIRY': '<?php echo esc_js(__('The expiry date is invalid or has passed.', 'growtype-child')); ?>',
                            'INVALID_CVV':    '<?php echo esc_js(__('The security code (CVV) is invalid.', 'growtype-child')); ?>',
                            'CARD_TYPE_NOT_SUPPORTED': '<?php echo esc_js(__('This card type is not supported.', 'growtype-child')); ?>'
                        };

                        if (errorMap[msg]) {
                            msg = errorMap[msg];
                        } else if (!msg) {
                            msg = '<?php echo esc_js(__('Card submission failed. Please check your details.', 'growtype-child')); ?>';
                        }

                        showError(msg);
                    });
                });
            }

        })(jQuery);
        </script>
        <?php
    }
}
