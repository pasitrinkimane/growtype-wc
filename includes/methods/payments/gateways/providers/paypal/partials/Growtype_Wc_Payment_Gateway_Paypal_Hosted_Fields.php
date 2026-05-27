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
 *   5. Browser redirects to the originating page (e.g. the chat page) or the WooCommerce thank-you page.
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

        // Client token — needed for PayPal Hosted Fields (card form).
        add_action('wp_ajax_gwc_paypal_client_token', [$this, 'ajax_get_client_token']);
        add_action('wp_ajax_nopriv_gwc_paypal_client_token', [$this, 'ajax_get_client_token']);

        // User id_token — needed for Google Pay / Apple Pay confirmOrder() (data-user-id-token).
        add_action('wp_ajax_gwc_paypal_user_id_token', [$this, 'ajax_get_user_id_token']);
        add_action('wp_ajax_nopriv_gwc_paypal_user_id_token', [$this, 'ajax_get_user_id_token']);

        // Outputs only the JS boot script (no modal HTML) — powers gwcPaymentFormModal and any inline card form.
        add_action('wp_footer', [$this, 'render_card_fields_footer_script']);
    }

    /**
     * Generate a PayPal id_token for use as data-user-id-token on the PayPal JS SDK.
     *
     * This is DIFFERENT from the client_token (Hosted Fields) — it uses:
     *   POST /v1/oauth2/token?grant_type=client_credentials&response_type=id_token
     *   Auth: Basic base64(client_id:secret)
     *
     * Required for Google Pay / Apple Pay confirmOrder() to exit "prebuild" state.
     * Matches the official WooCommerce PayPal Payments plugin (UserIdToken.php).
     * Cached for 4 minutes (token TTL is ~5 min per official plugin).
     */
    public function ajax_get_user_id_token()
    {
        if (!check_ajax_referer('gwc_paypal_hosted_fields', '_ajax_nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }

        $client_id = $this->gateway->get_client_id();
        $client_secret = $this->gateway->get_client_secret();
        $cache_key = 'gwc_paypal_user_id_token_' . md5($client_id . get_current_user_id());
        $cached = get_transient($cache_key);
        if ($cached) {
            wp_send_json_success(['id_token' => $cached]);
            return;
        }

        try {
            $base_url = $this->gateway->get_api_url('/v1/oauth2/token');
            $url = add_query_arg([
                'grant_type' => 'client_credentials',
                'response_type' => 'id_token',
            ], $base_url);

            // If the logged-in user has a PayPal Customer ID, pass it so PayPal
            // links the id_token to their vault record (matches official plugin).
            $user_id = get_current_user_id();
            $pp_cust_id = $user_id > 0 ? (string)get_user_meta($user_id, 'paypal_customer_id', true) : '';
            if (!empty($pp_cust_id)) {
                $url = add_query_arg(['target_customer_id' => $pp_cust_id], $url);
            }

            $credentials = base64_encode($client_id . ':' . $client_secret);

            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => '',
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                throw new \Exception('HTTP error: ' . $response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true) ?: [];
            $code = (int)wp_remote_retrieve_response_code($response);

            if ($code !== 200 || empty($body['id_token'])) {
                throw new \Exception(sprintf(
                    'PayPal id_token request failed (HTTP %d): %s',
                    $code,
                    wp_remote_retrieve_body($response)
                ));
            }

            $id_token = trim($body['id_token']);
            set_transient($cache_key, $id_token, 4 * MINUTE_IN_SECONDS);

            wp_send_json_success(['id_token' => $id_token]);
        } catch (\Exception $e) {
            error_log('[GWC PayPal] ajax_get_user_id_token error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Could not generate id_token.'], 500);
        }
    }

    /**
     * Generate a PayPal client_token for Hosted Fields (card form).
     * Uses: POST /v1/identity/generate-token with Bearer auth.
     * Cached for 55 minutes.
     */
    public function ajax_get_client_token()
    {
        if (!check_ajax_referer('gwc_paypal_hosted_fields', '_ajax_nonce', false)) {
            error_log('[GWC PayPal] ajax_get_client_token: nonce check FAILED. Nonce value: ' . sanitize_text_field($_POST['_ajax_nonce'] ?? '(empty)'));
            wp_send_json_error(['message' => 'Security check failed.'], 403);
            return;
        }

        $cache_key = 'gwc_paypal_client_token_' . md5($this->gateway->get_client_id());
        $cached = get_transient($cache_key);
        if ($cached) {
            error_log('[GWC PayPal] ajax_get_client_token: returning cached client_token.');
            wp_send_json_success(['client_token' => $cached]);
            return;
        }

        try {
            error_log('[GWC PayPal] ajax_get_client_token: fetching access token...');
            $access_token = $this->gateway->get_access_token(
                $this->gateway->get_client_id(),
                $this->gateway->get_client_secret()
            );
            if (empty($access_token)) {
                throw new \Exception('Could not retrieve access token.');
            }
            error_log('[GWC PayPal] ajax_get_client_token: access token OK, fetching client_token...');

            $url = $this->gateway->get_api_url('/v1/identity/generate-token');
            error_log('[GWC PayPal] ajax_get_client_token: POST ' . $url);
            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'body'    => '',
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                throw new \Exception('HTTP error: ' . $response->get_error_message());
            }

            $http_code = (int) wp_remote_retrieve_response_code($response);
            $raw_body  = wp_remote_retrieve_body($response);
            error_log('[GWC PayPal] ajax_get_client_token: PayPal HTTP ' . $http_code . ' body=' . substr($raw_body, 0, 400));

            $body = json_decode($raw_body, true) ?: [];
            if (empty($body['client_token'])) {
                throw new \Exception('PayPal did not return a client_token. HTTP ' . $http_code . '. Response: ' . $raw_body);
            }

            $token = trim($body['client_token']);
            set_transient($cache_key, $token, 55 * MINUTE_IN_SECONDS);

            error_log('[GWC PayPal] ajax_get_client_token: success, token length=' . strlen($token));
            wp_send_json_success(['client_token' => $token]);
        } catch (\Exception $e) {
            error_log('[GWC PayPal] ajax_get_client_token ERROR: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Could not generate client token.'], 500);
        }
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

        $product_id = absint($_POST['product_id'] ?? 0);
        $billing_email = sanitize_email($_POST['billing_email'] ?? '');
        // Whitelist vault_source to prevent arbitrary values reaching build_vault_payment_source()
        $vault_source_raw = sanitize_text_field($_POST['vault_source'] ?? 'card');
        $vault_source = in_array($vault_source_raw, ['card', 'paypal', 'applepay', 'googlepay'], true) ? $vault_source_raw : 'card';

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

        $return_url = $this->resolve_return_url_from_request();
        if (!empty($return_url)) {
            $order->update_meta_data('_growtype_return_after_payment_url', esc_url_raw($return_url));
        }

        do_action('woocommerce_checkout_create_order', $order, $_POST);

        $order->save();

        $wc_order_id = $order->get_id();

        // Create the PayPal order via REST API
        try {
            $access_token = $this->get_gateway_access_token();

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
            'orderID' => $paypal_order['id'],
            'wc_order_id' => $wc_order_id,
            'amount' => number_format((float)$order->get_total(), 2, '.', ''),
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
        $wc_order_id = absint($_POST['wc_order_id'] ?? 0);

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
                wp_send_json_success(['redirect' => $this->resolve_redirect_url_for_order($wc_order_id, $order)]);
            }
            wp_send_json_error(['message' => __('Payment is already being processed. Please wait.', 'growtype-wc')], 409);
        }

        set_transient($lock_key, 1, 30); // 30-second lock

        if ($order->is_paid()) {
            delete_transient($lock_key);
            wp_send_json_success(['redirect' => $this->resolve_redirect_url_for_order($wc_order_id, $order)]);
        }

        try {
            $access_token = $this->get_gateway_access_token();

            $capture_result = $this->gateway->capture_order($access_token, $paypal_order_id);

            $status = $capture_result['status'] ?? '';

            if ($status !== 'COMPLETED') {
                $detail = $capture_result['details'][0]['description'] ?? $capture_result['message'] ?? 'Capture failed.';
                throw new \Exception($detail);
            }

            // PayPal can return order status=COMPLETED even when the individual capture
            // inside is DECLINED (e.g. prepaid card, failed 3DS, fraud block, response_code=9500).
            // We MUST check the inner capture status — not just the outer order status.
            $capture_status = $capture_result['purchase_units'][0]['payments']['captures'][0]['status'] ?? '';
            if (!empty($capture_status) && $capture_status !== 'COMPLETED') {
                $proc_code = $capture_result['purchase_units'][0]['payments']['captures'][0]['processor_response']['response_code'] ?? '';
                $detail    = sprintf('Payment declined by card issuer (code: %s). Please try again or contact our support %s', $proc_code ?: 'unknown', get_option('admin_email'));
                error_log(sprintf(
                    '[GWC PayPal Capture] Inner capture DECLINED for WC order %d: capture_status=%s response_code=%s',
                    $wc_order_id,
                    $capture_status,
                    $proc_code
                ));
                throw new \Exception($detail);
            }

            // Extract the capture transaction ID
            $capture_id = $capture_result['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';

            // Debug-only: log payment_source type (never log full response — may contain card/vault data)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[GWC Vault] payment_source types: ' . implode(', ', array_keys($capture_result['payment_source'] ?? [])));
            }

            $vault_id = $capture_result['payment_source']['card']['attributes']['vault']['id'] ?? '';
            $pp_customer_id = $capture_result['payment_source']['card']['attributes']['vault']['customer']['id'] ?? '';
            $vault_type = 'card';

            // Google Pay embeds a card token under payment_source.google_pay.card
            if (empty($vault_id)) {
                $vault_id = $capture_result['payment_source']['google_pay']['card']['attributes']['vault']['id'] ?? '';
                $pp_customer_id = $capture_result['payment_source']['google_pay']['card']['attributes']['vault']['customer']['id'] ?? '';
                if (!empty($vault_id)) {
                    $vault_type = 'card';
                } // google_pay still vaults as card
            }
            // Apple Pay embeds a card token under payment_source.apple_pay.card
            if (empty($vault_id)) {
                $vault_id = $capture_result['payment_source']['apple_pay']['card']['attributes']['vault']['id'] ?? '';
                $pp_customer_id = $capture_result['payment_source']['apple_pay']['card']['attributes']['vault']['customer']['id'] ?? '';
                if (!empty($vault_id)) {
                    $vault_type = 'card';
                } // apple_pay still vaults as card
            }
            // PayPal account vault
            if (empty($vault_id)) {
                $vault_id = $capture_result['payment_source']['paypal']['attributes']['vault']['id'] ?? '';
                $pp_customer_id = $capture_result['payment_source']['paypal']['attributes']['vault']['customer']['id'] ?? '';
                if (!empty($vault_id)) {
                    $vault_type = 'paypal';
                }
            }

            error_log('[GWC Vault] Hosted Fields capture complete: order=' . $wc_order_id . ' capture_id=' . $capture_id . ' vault=' . (!empty($vault_id) ? 'yes(' . $vault_type . ')' : 'no'));

            $order->update_meta_data('_paypal_capture_id', sanitize_text_field($capture_id));

            if (!empty($vault_id)) {
                $order->update_meta_data('paypal_vault_id', sanitize_text_field($vault_id));
                $order->update_meta_data('paypal_vault_type', $vault_type);
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
                    update_user_meta($user_id, 'paypal_vault_type', $vault_type);
                    error_log(sprintf('[GWC Vault] Hosted Fields: stored vault_id=%s type=%s for user %d', $vault_id, $vault_type, $user_id));
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
            wp_send_json_error(['message' => sprintf(__('Payment capture failed. Please try again or contact our support %s', 'growtype-wc'), get_option('admin_email'))], 500);
        }

        delete_transient($lock_key);

        wp_send_json_success([
            'redirect' => $this->resolve_redirect_url_for_order($wc_order_id, $order),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Fetch a PayPal REST API access token using the configured credentials.
     *
     * @throws \Exception if the token cannot be retrieved.
     */
    private function get_gateway_access_token(): string
    {
        $token = $this->gateway->get_access_token(
            $this->gateway->get_client_id(),
            $this->gateway->get_client_secret()
        );

        if (empty($token)) {
            throw new \Exception('Could not retrieve PayPal access token.');
        }

        return $token;
    }

    /**
     * Resolve the "return after payment" URL from the current AJAX request.
     *
     * Only honours an explicitly POSTed return_url (sent by the triggering widget, e.g. the
     * chat payment button). We intentionally do NOT fall back to wp_get_referer(): AJAX calls
     * always have the triggering page as their HTTP Referer, which would cause users to be
     * redirected back to the page they came from (e.g. /credits/) instead of the WooCommerce
     * thank-you page.
     *
     * @return string Sanitised absolute URL on the same domain, or '' if none provided.
     */
    private function resolve_return_url_from_request(): string
    {
        // Only honour an explicitly POSTed return_url (set by the triggering widget, e.g. chat).
        // We intentionally do NOT fall back to wp_get_referer(): for AJAX calls the HTTP Referer
        // is always the page the user is currently on, so saving it would redirect them right back
        // to that page (e.g. /credits/) instead of the WooCommerce thank-you page.
        if (!empty($_POST['return_url'])) {
            $url = Growtype_Wc_Payment::sanitize_return_url(
                sanitize_text_field(wp_unslash($_POST['return_url']))
            );
            if (!empty($url)) {
                return $url;
            }
        }

        return '';
    }

    /**
     * Resolve the redirect URL for a completed order.
     *
     * Returns the URL saved as _growtype_return_after_payment_url on the order
     * (i.e. the page where the user initiated payment — e.g. the chat page).
     * Falls back to the standard WooCommerce thank-you/order-received URL.
     *
     * @param int           $wc_order_id
     * @param \WC_Order|null $order       Optional already-loaded order to avoid a second DB fetch.
     * @return string
     */
    private function resolve_redirect_url_for_order(int $wc_order_id, ?\WC_Order $order = null): string
    {
        $order = $order ?: wc_get_order($wc_order_id);
        if ($order) {
            $saved = (string)$order->get_meta('_growtype_return_after_payment_url');
            if (!empty($saved)) {
                $sanitised = Growtype_Wc_Payment::sanitize_return_url($saved);
                if (!empty($sanitised)) {
                    // Append success flag so the landing page shows the confirmation toast,
                    // matching the instant-charge flow behaviour.
                    return add_query_arg(Growtype_Wc_Payment::PAYMENT_SUCCESS_QUERY_ARG, '1', $sanitised);
                }
            }
        }

        return Growtype_Wc_Payment_Gateway::success_url($wc_order_id);
    }

    /**
     * Render only the modal HTML structure for #gwcPaypalHostedFieldsModal.
     * Extracted so it can be called independently from the JS boot script.
     */
    public static function render_modal_html(): void
    {
        ?>
        <!-- PayPal Hosted Fields Modal -->
        <div class="modal fade" id="gwcPaypalHostedFieldsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
                <div class="modal-content" style="background:#141414;border:1px solid #222;border-radius:12px;overflow:hidden">
                    <div class="modal-header gwc-hf-modal-header">
                        <div class="gwc-hf-header-title-wrap">
                            <div class="gwc-hf-secure-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                </svg>
                                Secure Checkout
                            </div>
                            <h5 class="modal-title">Pay with Card</h5>
                        </div>

                        <div class="gwc-hf-trust-badges">
                            <div class="gwc-hf-trust-item">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="32" height="20" rx="3" fill="#1A1F71"></rect>
                                    <path d="M11.9733 13.0645L13.1256 6.33125H14.9702L13.8179 13.0645H11.9733ZM19.7428 6.47167C19.3496 6.29417 18.7303 6.11125 17.9547 6.11125C16.1408 6.11125 14.8569 7.02708 14.846 8.32958C14.8368 9.29417 15.7534 9.83333 16.4526 10.1583C17.1691 10.4908 17.4111 10.7021 17.408 11.0117C17.4034 11.4871 16.808 11.6967 16.2713 11.6967C15.4851 11.6967 15.0113 11.4954 14.6544 11.3417L14.3983 12.545C14.7506 12.6975 15.3998 12.8333 16.0751 12.8333C17.9908 12.8333 19.2555 11.9325 19.2662 10.5183C19.2743 9.35125 18.5036 8.8475 17.5147 8.3975C16.8837 8.10542 16.6644 7.92542 16.6669 7.64125C16.6669 7.32042 17.0494 6.96917 17.8863 6.96917C18.5724 6.96917 19.0189 7.11208 19.3879 7.2625L19.7428 6.47167ZM23.8208 6.33125C23.4184 6.33125 23.084 6.55167 22.9284 6.90375L20.1983 13.0645H22.1373L22.5229 12.0621H24.8931L25.12 13.0645H26.8334L25.3404 6.33125H23.8208ZM23.0768 10.6121L23.708 8.98375L24.0723 10.6121H23.0768ZM10.5905 6.33125L8.78317 10.9329L8.59107 10.0246C8.25413 8.94833 7.23431 7.7475 6.13677 7.19917L7.84236 13.0633H9.79198L12.6896 6.33125H10.5905ZM6.42945 6.33125H3.14154L3.10955 6.48125C5.59737 7.08042 7.23888 8.54417 7.91502 10.2871L7.26593 7.21417C7.15941 6.64333 6.8458 6.36 6.42945 6.33125Z" fill="white"></path>
                                </svg>
                            </div>
                            <div class="gwc-hf-trust-item">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="32" height="20" rx="3" fill="#222222"></rect>
                                    <path d="M12.4444 10C12.4444 11.5165 11.7588 12.8719 10.6875 13.7845C9.88086 14.4714 8.84157 14.8889 7.70833 14.8889C5.51239 14.8889 3.72517 13.2081 3.52479 11.0556H3.33333V8.94444H3.52479C3.72517 6.79194 5.51239 5.11111 7.70833 5.11111C8.84157 5.11111 9.88086 5.52864 10.6875 6.21553C11.7588 7.12814 12.4444 8.48353 12.4444 10Z" fill="#EB001B"></path>
                                    <path d="M22.0833 10C22.0833 8.48353 21.3977 7.12814 20.3264 6.21553C19.5197 5.52864 18.4804 5.11111 17.3472 5.11111C15.1513 5.11111 13.364 6.79194 13.1636 8.94444H12.9722V11.0556H13.1636C13.364 13.2081 15.1513 14.8889 17.3472 14.8889C18.4804 14.8889 19.5197 14.4714 20.3264 13.7845C21.3977 12.8719 22.0833 11.5165 22.0833 10Z" fill="#F79E1B"></path>
                                    <path d="M13.1636 10C13.1636 8.48353 13.8492 7.12814 14.9205 6.21553C15.6599 5.58614 16.6074 5.20764 17.6364 5.13283C16.5651 4.22022 15.1613 3.66667 13.625 3.66667C11.4291 3.66667 9.64183 5.3475 9.44145 7.5H9.25V12.5H9.44145C9.64183 14.6525 11.4291 16.3333 13.625 16.3333C15.1613 16.3333 16.5651 15.7798 17.6364 14.8672C16.6074 14.7924 15.6599 14.4139 14.9205 13.7845C13.8492 12.8719 13.1636 11.5165 13.1636 10Z" fill="#FF5F00"></path>
                                </svg>
                            </div>
                            <div class="gwc-hf-trust-item">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="32" height="20" rx="3" fill="#0070BA"></rect>
                                    <path d="M22.5 7.5L20 12.5H23L25.5 7.5H22.5ZM17.5 7.5L15 12.5H18L20.5 7.5H17.5ZM12.5 7.5L10 12.5H13L15.5 7.5H12.5ZM7.5 7.5L5 12.5H8L10.5 7.5H7.5Z" fill="white"></path>
                                </svg>
                            </div>
                            <div class="gwc-hf-trust-divider"></div>
                            <div class="gwc-hf-trust-item pci-badge">
                                <svg width="40" height="12" viewBox="0 0 40 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3.5 0H0V12H3.5V0ZM8.5 0H5V12H8.5V0ZM13.5 0H10V12H13.5V0Z" fill="#888"></path>
                                    <text x="15" y="10" fill="#888" font-family="sans-serif" font-size="8" font-weight="bold">PCI DSS</text>
                                </svg>
                            </div>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body gwc-hf-modal-body">
                        <div id="gwc-paypal-not-eligible" style="display:none;color:#e05c5c;padding:12px;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.3);border-radius:8px;margin-bottom:16px;font-size:13px">
                            <?php _e('Advanced card payments are not available for this account. Please use the PayPal button instead.', 'growtype-child'); ?>
                        </div>

                        <div id="gwc-paypal-fields-wrap" style="position:relative; min-height: 250px;">
                            <?php Growtype_Wc_Payment_Gateway_Paypal_Payment_Form::render_loader(); ?>

                            <?php Growtype_Wc_Payment_Gateway_Paypal_Card_Form::render(); ?>

                            <?php Growtype_Wc_Payment_Gateway_Paypal_Payment_Form::render_badge(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php

        // Inject the PayPal Card Fields boot script so the modal works
        // both when rendered in wp_footer AND when fetched via AJAX.
        if (class_exists('Growtype_Wc_Payment_Gateway_Paypal_Hosted_Fields')) {
            $gateway = null;
            if (function_exists('WC') && WC()->payment_gateways) {
                $gateways = WC()->payment_gateways->payment_gateways();
                $gateway = $gateways['gwc-paypal'] ?? null;
            }

            if ($gateway && !empty($gateway->enable_card_payments)) {
                Growtype_Wc_Payment_Gateway_Paypal_Hosted_Fields::render_card_fields_script(
                    $gateway->get_client_id(),
                    $gateway->get_merchant_id(),
                    $gateway->is_test_mode(),
                    get_woocommerce_currency(),
                    wp_create_nonce('gwc_paypal_hosted_fields'),
                    admin_url('admin-ajax.php')
                );
            }
        }
    }

    /**
     * Render the PayPal Card Fields JS boot script.
     *
     * Static so it can be called from any context (wp_footer, inline AJAX modal response, etc.)
     * without coupling to the legacy #gwcPaypalHostedFieldsModal markup.
     *
     * @param string $client_id
     * @param string $merchant_id
     * @param bool   $is_sandbox
     * @param string $currency
     * @param string $nonce
     * @param string $ajax_url
     */
    public static function render_card_fields_script(
        string $client_id,
        string $merchant_id,
        bool   $is_sandbox,
        string $currency,
        string $nonce,
        string $ajax_url
    ): void
    {
        ?>
        <script>
            (function ($) {
                var gwcPaypalClientId = <?php echo wp_json_encode($client_id); ?>;
                var gwcPaypalMerchantId = <?php echo wp_json_encode($merchant_id); ?>;
                var gwcPaypalSandbox = <?php echo $is_sandbox ? 'true' : 'false'; ?>;
                var gwcAjaxUrl  = <?php echo wp_json_encode($ajax_url); ?>;
                var gwcNonce    = <?php echo wp_json_encode($nonce); ?>;
                var gwcCurrency = <?php echo wp_json_encode($currency); ?>;
                var gwcProductId = 0;
                var gwcWcOrderId = 0;
                var gwcReturnUrl = ''; // Explicit return URL set by the triggering widget (e.g. chat). Empty = no custom redirect.

                // Card Fields instance
                var cardFields = null;
                var cardFieldsLoading = false;

                function setSubmitButtonText($btn, text) {
                    var $label = $btn.find('.gwc-hf-submit-text');
                    if ($label.length > 0) {
                        $label.text(text);
                    } else {
                        $btn.text(text);
                    }
                }

                // Resolve nonce: prefer the one baked into this script block,
                // fall back to window.growtype_wc_ajax.paypal.nonce (set by ensure_inline_paypal_data)
                function resolveHfNonce() {
                    if (gwcNonce) return gwcNonce;
                    return (window.growtype_wc_ajax
                        && window.growtype_wc_ajax.paypal
                        && window.growtype_wc_ajax.paypal.nonce)
                        ? window.growtype_wc_ajax.paypal.nonce
                        : '';
                }

                // ── Handle inline payment form mounting ───────────────────────────
                document.addEventListener('growtype_wc_payment_request', function (e) {
                    if (e.detail && e.detail.provider === 'paypal') {
                        var prodId = parseInt(e.detail.productId, 10);
                        if (prodId) {
                            gwcProductId = prodId;
                            // Accept an explicit return URL from the triggering widget
                            gwcReturnUrl = (e.detail.returnUrl && typeof e.detail.returnUrl === 'string')
                                ? e.detail.returnUrl
                                : '';
                            console.log('[GWC HF] growtype_wc_payment_request received for prodId:', prodId, '| returnUrl:', gwcReturnUrl || '(none)');
                            checkAndBootInlineCardFields();
                        }
                    }
                });

                function checkAndBootInlineCardFields() {
                    var formRoot = getActiveFormRoot();
                    if (formRoot && formRoot !== document) {
                        var prodId = parseInt($(formRoot).data('product-id'), 10) || gwcProductId;
                        if (prodId) {
                            gwcProductId = prodId;
                            // Capture explicit return URL from the form element (set by chat widget).
                            // Only override if not already set by the payment_request event.
                            if (!gwcReturnUrl) {
                                gwcReturnUrl = $(formRoot).data('return-url') || '';
                            }
                            
                            // Check if the secure card field iframe is already loaded inside this active form
                            var nameContainer = formRoot.querySelector ? formRoot.querySelector('#card-name-field-container') : null;
                            var isAlreadyBooted = nameContainer && nameContainer.querySelector('iframe') !== null;

                            console.log('[GWC HF] checkAndBootInlineCardFields scan → prodId=' + prodId + ' | isAlreadyBooted:', isAlreadyBooted, '| cardFieldsLoading:', cardFieldsLoading);

                            if (!isAlreadyBooted && !cardFieldsLoading) {
                                console.log('[GWC HF] ✅ Uninitialized card form detected (prodId=' + prodId + '), loading PayPal SDK...');
                                cardFields = null; // Clear previous instance to allow fresh boot
                                cardFieldsLoading = true;
                                loadPaypalSdk(function () {
                                    initCardFields();
                                });
                            }
                        }
                    }
                }

                // Run immediately to catch pre-existing forms
                if (document.readyState === 'loading') {
                    $(document).ready(function () {
                        checkAndBootInlineCardFields();
                    });
                } else {
                    checkAndBootInlineCardFields();
                }

                // Bulletproof observer: catch dynamically injected card forms
                var gwcDomObserver = null;
                if (window.MutationObserver) {
                    gwcDomObserver = new MutationObserver(function () {
                        checkAndBootInlineCardFields();
                    });
                    gwcDomObserver.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                } else {
                    console.warn('[GWC HF] MutationObserver not available in this browser.');
                }

                // ── Manual debug helper ───────────────────────────────────────────
                window.gwcHfBoot = function () {
                    console.log('[GWC HF] Manual gwcHfBoot() called.');
                    cardFields = null;
                    cardFieldsLoading = false;
                    checkAndBootInlineCardFields();
                };

                // ── Prevent double-clicks on all payment buttons ──────────────────
                $(document).on('click', '.btn-addtocart', function (e) {
                    var $b = $(this);
                    if ($b.hasClass('processing')) {
                        e.preventDefault();
                        return false;
                    }
                    $b.addClass('processing');
                });

                // ── Open modal when the PayPal Card button is clicked ─────────────
                $(document).on('click', '.btn-show-paypal-card', function (e) {
                    var $btn = $(this);

                    // If a vault token is available the button carries data-instant-charge="1"
                    // and its href already points to the charge_intent action — just follow it.
                    if ($btn.data('instant-charge') == '1') {
                        e.preventDefault();
                        var chargeUrl = $btn.attr('href');
                        if (chargeUrl) {
                            $btn.addClass('processing');
                            window.location.href = chargeUrl;
                        }
                        return;
                    }
                    // Otherwise, let the global wc-payment-form.js handler show the gwcPaymentFormModal.
                });

                // Reset Card Fields when modal is closed so styles re-apply on reopen
                $(document).on('hidden.bs.modal', '#gwcPaypalHostedFieldsModal', function () {
                    cardFields = null;
                    cardFieldsLoading = false;
                    $('#gwcPaypalHostedFieldsModal #card-name-field-container, #gwcPaypalHostedFieldsModal #card-number-field-container, #gwcPaypalHostedFieldsModal #card-expiry-field-container, #gwcPaypalHostedFieldsModal #card-cvv-field-container').empty().height(65);
                    $('#gwcPaypalHostedFieldsModal #gwc-hf-errors, #gwcPaypalHostedFieldsModal .gwc-hf-errors').hide();
                    if (window.GrowtypeWcPaypalProvider) {
                        window.GrowtypeWcPaypalProvider.showSpinner('#gwcPaypalHostedFieldsModal #gwc-paypal-fields-wrap', '<?php _e('Loading...', 'growtype-child'); ?>');
                    } else {
                        $('#gwcPaypalHostedFieldsModal #gwc-paypal-form-loader, #gwcPaypalHostedFieldsModal .gwc-paypal-form-loader').show();
                    }
                    var $modalSubmit = $('#gwcPaypalHostedFieldsModal #card-field-submit-button, #gwcPaypalHostedFieldsModal .gwc-hf-submit');
                    $modalSubmit.prop('disabled', true);
                    setSubmitButtonText($modalSubmit, '<?php echo esc_js(Growtype_Wc_Payment_Gateway_Paypal_Card_Form::get_default_submit_label()); ?>');
                });

                // ── Load PayPal JS SDK and initialise Card Fields ────────────────
                function loadPaypalSdk(callback) {
                    // Strategy: the express buttons provider (growtypeWcPaypalProvider.js) now
                    // loads card-fields in its SDK when #card-form is present on the same page.
                    // Reusing that single SDK avoids two conflicting PayPal script tags (Zoid conflict).
                    //
                    // HOWEVER: on pages that use only the modal flow (e.g. /plans/), there is no
                    // .gwc-payment-form mount point, so the express provider never loads and
                    // gwc_paypal_sdk_ready never fires.  In that case we must load standalone.

                    // Case 1 — unified SDK already loaded (express buttons on same page).
                    if (window.paypal && window.paypal.CardFields) {
                        console.log('[GWC HF] window.paypal.CardFields available — using unified SDK.');
                        callback();
                        return;
                    }

                    // Case 2 — inline express form is on the page: wait for the unified SDK event.
                    //          If no express form exists, skip straight to standalone loading to avoid
                    //          hanging forever (gwc_paypal_sdk_ready will never fire in modal-only pages).
                    var hasExpressForm = document.querySelector('.gwc-payment-form') !== null;
                    console.log('[GWC HF] hasExpressForm:', hasExpressForm, '| gwcPaypalSdkReady:', !!window.gwcPaypalSdkReady);

                    if (!window.gwcPaypalSdkReady && hasExpressForm) {
                        console.log('[GWC HF] Waiting for unified PayPal SDK (gwc_paypal_sdk_ready)...');
                        document.addEventListener('gwc_paypal_sdk_ready', function onSdkReady() {
                            document.removeEventListener('gwc_paypal_sdk_ready', onSdkReady);
                            console.log('[GWC HF] gwc_paypal_sdk_ready fired. window.paypal.CardFields:', typeof (window.paypal || {}).CardFields);
                            if (window.paypal && window.paypal.CardFields) {
                                callback();
                            } else {
                                loadStandaloneCardFieldsSdk(callback);
                            }
                        });
                        return;
                    }

                    // Case 3 — modal-only page (no express form) OR express SDK ready without CardFields.
                    if (!hasExpressForm) {
                        console.log('[GWC HF] Modal-only page — loading standalone card-fields SDK.');
                    } else {
                        console.warn('[GWC HF] gwcPaypalSdkReady=true but window.paypal.CardFields missing — loading standalone SDK.');
                    }
                    loadStandaloneCardFieldsSdk(callback);
                }

                function loadStandaloneCardFieldsSdk(callback) {
                    console.log('[GWC HF] Fetching PayPal client token for standalone card-fields SDK...');
                    var hfNonce = resolveHfNonce();
                    console.log('[GWC HF] gwcAjaxUrl:', gwcAjaxUrl);
                    console.log('[GWC HF] Using nonce:', hfNonce ? hfNonce.substring(0,8) + '...' : '⚠️ EMPTY — nonce missing!');
                    console.log('[GWC HF] gwcPaypalClientId:', gwcPaypalClientId ? gwcPaypalClientId.substring(0,12)+'...' : '⚠️ MISSING');
                    console.log('[GWC HF] gwcCurrency:', gwcCurrency);
                    $.post(gwcAjaxUrl, {
                        action: 'gwc_paypal_client_token',
                        _ajax_nonce: hfNonce
                    }).done(function (res) {
                        var clientToken = '';
                        if (res.success && res.data && res.data.client_token) {
                            clientToken = res.data.client_token;
                            console.log('[GWC HF] Successfully retrieved PayPal client token.');
                        } else {
                            console.warn('[GWC HF] Could not retrieve client token, loading SDK without it:', res);
                        }

                        console.log('[GWC HF] Loading standalone PayPal card-fields SDK...');
                        var s = document.createElement('script');
                        s.src = 'https://www.paypal.com/sdk/js'
                            + '?client-id=' + encodeURIComponent(gwcPaypalClientId)
                            + (gwcPaypalMerchantId ? '&merchant-id=' + encodeURIComponent(gwcPaypalMerchantId) : '')
                            + '&components=card-fields'
                            + '&intent=capture'
                            + '&currency=' + encodeURIComponent(gwcCurrency)
                            + (gwcPaypalSandbox ? '&debug=true&buyer-country=US' : '');
                        console.log('[GWC HF] Standalone SDK URL:', s.src);
                        s.setAttribute('data-namespace', 'paypal_gwc');
                        if (clientToken) {
                            s.setAttribute('data-client-token', clientToken);
                        }
                        s.onload = function () {
                            console.log('[GWC HF] Standalone SDK loaded. paypal_gwc.CardFields:', typeof (window.paypal_gwc || {}).CardFields);
                            callback();
                        };
                        s.onerror = function () {
                            cardFieldsLoading = false;
                            showError('<?php echo esc_js(__('Failed to load PayPal SDK. Please refresh and try again.', 'growtype-child')); ?>');
                        };
                        document.head.appendChild(s);
                    }).fail(function (xhr, err) {
                        console.error('[GWC HF] Client token AJAX request failed:', err);
                        cardFieldsLoading = false;
                        showError('<?php echo esc_js(__('Failed to load PayPal secure token. Please refresh and try again.', 'growtype-child')); ?>');
                    });
                }

                function getActiveFormRoot() {
                    var $modal = $('#gwcPaypalHostedFieldsModal');
                    if ($modal.length > 0 && ($modal.hasClass('show') || $modal.is(':visible'))) {
                        return $modal[0];
                    }
                    if (gwcProductId) {
                        var $activeForm = $('.gwc-payment-form[data-product-id="' + gwcProductId + '"]:visible').last();
                        if ($activeForm.length > 0) {
                            return $activeForm[0];
                        }
                        $activeForm = $('.gwc-payment-form[data-product-id="' + gwcProductId + '"]').last();
                        if ($activeForm.length > 0) {
                            return $activeForm[0];
                        }
                    }
                    var $anyForm = $('.gwc-payment-form:visible').last();
                    if ($anyForm.length > 0) {
                        return $anyForm[0];
                    }
                    $anyForm = $('.gwc-payment-form').last();
                    if ($anyForm.length > 0) {
                        return $anyForm[0];
                    }
                    return document;
                }

                function showError(msg) {
                    var formRoot = getActiveFormRoot();

                    var $err = $(formRoot).find('#gwc-hf-errors, .gwc-hf-errors');
                    if ($err.length === 0) $err = $('#gwc-hf-errors');
                    $err.text(msg).show(); // .text() prevents XSS

                    var $btn = $(formRoot).find('#card-field-submit-button, .gwc-hf-submit');
                    $btn.prop('disabled', false);
                    setSubmitButtonText($btn, '<?php echo esc_js(Growtype_Wc_Payment_Gateway_Paypal_Card_Form::get_default_submit_label()); ?>');
                }

                function initCardFields() {
                    // Resolve the PayPal SDK reference: prefer the unified window.paypal
                    // (express + card-fields), fall back to the standalone paypal_gwc namespace.
                    var paypalRef = (window.paypal && window.paypal.CardFields) ? window.paypal : window.paypal_gwc;

                    console.group('[GWC HF] initCardFields()');
                    console.log('Using paypal ref:', paypalRef === window.paypal ? 'window.paypal (unified)' : 'window.paypal_gwc (standalone)');
                    console.log('CardFields type:', typeof (paypalRef || {}).CardFields);
                    console.log('gwcProductId:', gwcProductId);
                    console.log('#card-name-field-container in DOM:', !!document.getElementById('card-name-field-container'));
                    console.log('#card-number-field-container in DOM:', !!document.getElementById('card-number-field-container'));
                    console.groupEnd();

                    if (!paypalRef || !paypalRef.CardFields) {
                        console.error('[GWC HF] ❌ PayPal CardFields not available in window.paypal or window.paypal_gwc.');
                        cardFieldsLoading = false;
                        return;
                    }

                    if (cardFields) {
                        console.log('[GWC HF] CardFields already initialised, skipping.');
                        return;
                    }

                    console.log('[GWC HF] Creating CardFields instance...');
                    cardFields = paypalRef.CardFields({
                        createOrder: function () {
                            return createOrderInternal();
                        },
                        onApprove: function (data) {
                            return onApproveInternal(data.orderID);
                        },
                        style: {
                            input: {
                                "font-size": "16px",
                                "font-family": "-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif",
                                "color": "#000000",
                                "padding": "15px 15px"
                            },
                            ".invalid": {"color": "#e05c5c"}
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
                        cardFieldsLoading = false;
                        cardFields = null;
                        return;
                    }

                    // Show the form wrapper before rendering fields into it
                    $('#gwc-paypal-not-eligible').hide();
                    $('#gwc-paypal-fields-wrap').show();

                    // ── Scope container lookups to the ACTIVE inline form ───────────
                    // The static modal (#gwcPaypalHostedFieldsModal) shares the same
                    // field IDs (card-name-field-container, etc.).  When both are in
                    // the DOM, getElementById / querySelector return the MODAL's node
                    // first — rendering iframes into the hidden modal, not the visible
                    // inline form.  We scope every lookup to the .gwc-payment-form
                    // that owns this product so we always hit the right element.
                    var formRoot = getActiveFormRoot();

                    function scopedEl(id) {
                        var el = formRoot.querySelector ? formRoot.querySelector('#' + id) : null;
                        if (!el) el = document.getElementById(id); // fallback (modal path)
                        return el;
                    }

                    // Render individual fields
                    console.log('[GWC HF] Calling render() on each field...');
                    var nameContainer   = scopedEl('card-name-field-container');
                    var numberContainer = scopedEl('card-number-field-container');
                    var expiryContainer = scopedEl('card-expiry-field-container');
                    var cvvContainer    = scopedEl('card-cvv-field-container');
                    console.log('[GWC HF] formRoot:', formRoot === document ? 'document (fallback)' : '.gwc-payment-form[data-product-id=' + gwcProductId + ']');
                    console.log('[GWC HF] Containers: name=' + !!nameContainer + ' number=' + !!numberContainer + ' expiry=' + !!expiryContainer + ' cvv=' + !!cvvContainer);

                    Promise.all([
                        cardFields.NameField({placeholder: '<?php _e('Cardholder Name', 'growtype-child'); ?>'}).render(nameContainer),
                        cardFields.NumberField({placeholder: '•••• •••• •••• ••••'}).render(numberContainer),
                        cardFields.ExpiryField({placeholder: 'MM / YY'}).render(expiryContainer),
                        cardFields.CVVField({placeholder: '•••'}).render(cvvContainer)
                    ]).then(function () {
                        console.log('[GWC HF] ✅ All card fields rendered successfully.');
                        cardFieldsLoading = false;
                        setTimeout(function () {
                            if (window.GrowtypeWcPaypalProvider) {
                                window.GrowtypeWcPaypalProvider.hideSpinner('#gwcPaypalHostedFieldsModal #gwc-paypal-fields-wrap');
                                window.GrowtypeWcPaypalProvider.hideSpinner('.gwc-payment-form__card');
                            } else {
                                $('#gwc-paypal-form-loader, .gwc-paypal-form-loader').fadeOut(300);
                                $('.gwc-payment-form-mainloader').fadeOut(300);
                            }
                        }, 1000)
                    }).catch(function (err) {
                        console.error('[GWC HF] ❌ CardFields render error:', err);
                        console.error('[GWC HF] render error name:', err && err.name);
                        console.error('[GWC HF] render error message:', err && err.message);
                        cardFieldsLoading = false;
                        cardFields = null;
                        if (window.GrowtypeWcPaypalProvider) {
                            window.GrowtypeWcPaypalProvider.hideSpinner('#gwcPaypalHostedFieldsModal #gwc-paypal-fields-wrap');
                            window.GrowtypeWcPaypalProvider.hideSpinner('.gwc-payment-form__card');
                        } else {
                            $('#gwc-paypal-form-loader, .gwc-paypal-form-loader').hide();
                            $('.gwc-payment-form-mainloader').hide();
                        }
                        showError('<?php echo esc_js(__('Failed to render card fields. Please try again.', 'growtype-child')); ?>');
                    });

                    // Force 65px height on PayPal's internal structure (Zoid wrappers and iframes)
                    var TARGET_H = 65;
                    ['#card-name-field-container',
                        '#card-number-field-container',
                        '#card-expiry-field-container',
                        '#card-cvv-field-container'].forEach(function (sel) {
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
                            descendants.forEach(function (el) {
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
                        var obs = new MutationObserver(function (mutations) {
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
                        setTimeout(function () {
                            forceHeight('timeout_500');
                        }, 500);
                        setTimeout(function () {
                            forceHeight('timeout_1500');
                        }, 1500);
                    });

                    // Enable submit button
                    $(formRoot).find('#card-field-submit-button, .gwc-hf-submit').prop('disabled', false);

                    // Helper for Create Order (shared by Buttons and CardFields)
                    function createOrderInternal() {
                        var postData = {
                            action: 'gwc_paypal_hosted_create_order',
                            _ajax_nonce: gwcNonce,
                            product_id: gwcProductId,
                            currency: gwcCurrency,
                            vault_source: 'card'
                        };
                        // Only send return_url when explicitly set by the triggering widget.
                        // Never fall back to window.location.href — that would redirect
                        // the user back to whatever page they happen to be on (e.g. /credits/).
                        if (gwcReturnUrl) {
                            postData.return_url = gwcReturnUrl;
                        }
                        return $.post(gwcAjaxUrl, postData).then(function (res) {
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
                        if (window.GrowtypeWcPaypalProvider) {
                            window.GrowtypeWcPaypalProvider.showSpinner(formRoot, '<?php _e('Processing...', 'growtype-child'); ?>');
                        } else {
                            $(formRoot).find('#gwc-paypal-form-loader, .gwc-paypal-form-loader').stop(true, true).show();
                        }
                        return $.post(gwcAjaxUrl, {
                            action: 'gwc_paypal_hosted_capture_order',
                            _ajax_nonce: gwcNonce,
                            paypal_order_id: orderID,
                            wc_order_id: gwcWcOrderId
                        }).then(function (res) {
                            if (res.success && res.data.redirect) {
                                window.location.href = res.data.redirect;
                            } else {
                                throw new Error(res.data.message || 'Payment capture failed');
                            }
                        }).catch(function (err) {
                            if (window.GrowtypeWcPaypalProvider) {
                                window.GrowtypeWcPaypalProvider.hideSpinner(formRoot);
                            } else {
                                $(formRoot).find('#gwc-paypal-form-loader, .gwc-paypal-form-loader').hide();
                            }
                            var $approvalBtn = $(formRoot).find('#card-field-submit-button, .gwc-hf-submit');
                            $approvalBtn.prop('disabled', false);
                            setSubmitButtonText($approvalBtn, '<?php echo esc_js(Growtype_Wc_Payment_Gateway_Paypal_Card_Form::get_default_submit_label()); ?>');
                            showError(err.message || '<?php echo esc_js(sprintf(__('Payment capture failed. Please try again or contact our support %s', 'growtype-child'), get_option('admin_email'))); ?>');
                        });
                    }

                    // Submit listener for Card Fields
                    var $submitBtn = $(formRoot).find('#card-field-submit-button, .gwc-hf-submit');
                    $submitBtn.off('click').on('click', function (e) {
                        e.preventDefault();
                        if (!gwcProductId) {
                            showError('<?php echo esc_js(__('Please select a plan before paying.', 'growtype-child')); ?>');
                            return;
                        }
                        var $err = $(formRoot).find('#gwc-hf-errors, .gwc-hf-errors');
                        if ($err.length === 0) $err = $('#gwc-hf-errors');
                        $err.hide();

                        $submitBtn.prop('disabled', true);
                        setSubmitButtonText($submitBtn, '<?php echo esc_js(__('Processing…', 'growtype-child')); ?>');
                        if (window.GrowtypeWcPaypalProvider) {
                            window.GrowtypeWcPaypalProvider.showSpinner(formRoot, '<?php _e('Processing...', 'growtype-child'); ?>');
                        } else {
                            $(formRoot).find('#gwc-paypal-form-loader, .gwc-paypal-form-loader').stop(true, true).show();
                        }
                        cardFields.submit({
                            // No additional data needed for basic submission
                        }).catch(function (err) {
                            console.error('Submission Error:', err);

                            // Hide loader and re-enable button so user can correct their details
                            if (window.GrowtypeWcPaypalProvider) {
                                window.GrowtypeWcPaypalProvider.hideSpinner(formRoot);
                            } else {
                                $(formRoot).find('#gwc-paypal-form-loader, .gwc-paypal-form-loader').hide();
                            }
                            $submitBtn.prop('disabled', false);
                            setSubmitButtonText($submitBtn, '<?php echo esc_js(Growtype_Wc_Payment_Gateway_Paypal_Card_Form::get_default_submit_label()); ?>');

                            var msg = (err && err.message) ? err.message : '';

                            // Map technical error codes / PayPal issue strings to user-friendly messages
                            var errorMap = {
                                // CardFields validation errors
                                'INVALID_NUMBER':            '<?php echo esc_js(__('The card number is invalid. Please check and try again.', 'growtype-child')); ?>',
                                'INVALID_EXPIRY':            '<?php echo esc_js(__('The expiry date is invalid or has passed.', 'growtype-child')); ?>',
                                'INVALID_CVV':               '<?php echo esc_js(__('The security code (CVV) is invalid.', 'growtype-child')); ?>',
                                'CARD_TYPE_NOT_SUPPORTED':   '<?php echo esc_js(__('This card type is not supported.', 'growtype-child')); ?>',
                                // PayPal API issue codes (appear in err.message for sandbox mismatches)
                                'CREDIT_CARD_NUMBER_MUST_BE_TEST_NUMBER': '<?php echo esc_js(__('Please use a test card number in sandbox mode.', 'growtype-child')); ?>',
                                'INSTRUMENT_DECLINED':       '<?php echo esc_js(sprintf(__('Your card was declined. Please try again or contact our support %s', 'growtype-child'), get_option('admin_email'))); ?>',
                                'PAYER_CANNOT_PAY':          '<?php echo esc_js(sprintf(__('Payment could not be processed. Please try again or contact our support %s', 'growtype-child'), get_option('admin_email'))); ?>',
                                'CARD_EXPIRED':              '<?php echo esc_js(sprintf(__('Your card has expired. Please try again or contact our support %s', 'growtype-child'), get_option('admin_email'))); ?>',
                                'DO_NOT_HONOR':              '<?php echo esc_js(sprintf(__('Your card issuer declined the payment. Please try again or contact our support %s', 'growtype-child'), get_option('admin_email'))); ?>',
                                'TRANSACTION_REFUSED':       '<?php echo esc_js(sprintf(__('The transaction was refused. Please try again or contact our support %s', 'growtype-child'), get_option('admin_email'))); ?>',
                            };

                            // Exact match first
                            if (errorMap[msg]) {
                                msg = errorMap[msg];
                            } else {
                                // Partial match — scan err.message for known issue codes
                                var matched = false;
                                for (var code in errorMap) {
                                    if (msg.indexOf(code) !== -1) {
                                        msg = errorMap[code];
                                        matched = true;
                                        break;
                                    }
                                }
                                // Raw PayPal JSON or URL in message — strip it
                                if (!matched) {
                                    if (msg.indexOf('{') !== -1 || msg.indexOf('returned status') !== -1 || msg.indexOf('paypal.com') !== -1) {
                                        msg = '<?php echo esc_js(sprintf(__('Your payment could not be processed. Please check your card details, try again or contact our support %s', 'growtype-child'), get_option('admin_email'))); ?>';
                                    } else if (!msg) {
                                        msg = '<?php echo esc_js(__('Card submission failed. Please check your details.', 'growtype-child')); ?>';
                                    }
                                }
                            }

                            showError(msg);
                        });
                    });
                }

            })(jQuery);
        </script>
        <?php
    }

    /**
     * Render the PayPal Hosted Fields script in the wp_footer.
     * Card data is entered inside PayPal-hosted iframes and never touches our server.
     */
    public function render_paypal_hosted_fields_modal(): void
    {
        if (!$this->gateway || empty($this->gateway->enable_card_payments)) {
            return;
        }

        $client_id   = $this->gateway->get_client_id();
        $merchant_id = $this->gateway->get_merchant_id();
        $is_sandbox  = $this->gateway->is_test_mode();
        $currency    = get_woocommerce_currency();
        $nonce       = wp_create_nonce('gwc_paypal_hosted_fields');
        $ajax_url    = admin_url('admin-ajax.php');

        self::render_card_fields_script($client_id, $merchant_id, $is_sandbox, $currency, $nonce, $ajax_url);
    }

    /**
     * Render the PayPal Card Fields JS boot script in wp_footer.
     *
     * Outputs ONLY the <script> block — no modal HTML.
     * Powers gwcPaymentFormModal (and any inline .gwc-payment-form) via MutationObserver.
     */
    public function render_card_fields_footer_script(): void
    {
        if (!$this->gateway || empty($this->gateway->enable_card_payments)) {
            return;
        }

        self::render_card_fields_script(
            $this->gateway->get_client_id(),
            $this->gateway->get_merchant_id(),
            $this->gateway->is_test_mode(),
            get_woocommerce_currency(),
            wp_create_nonce('gwc_paypal_hosted_fields'),
            admin_url('admin-ajax.php')
        );
    }
}

