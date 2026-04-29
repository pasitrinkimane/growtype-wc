<?php

/**
 * PayPal Orders (Orders API v2) implementation.
 */
class Growtype_Wc_Payment_Gateway_Paypal_Orders
{
    /** @var Growtype_Wc_Payment_Gateway_Paypal */
    private $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;
    }

    public function get_order_data($access_token, $paypal_order_id)
    {
        $orders_url = $this->gateway->get_api_url("/v2/checkout/orders/{$paypal_order_id}");

        $response = wp_remote_get($orders_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('[GWC PayPal] get_order_data WP_Error: ' . $response->get_error_message());
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : [];
    }

    public function build_vault_payment_source(string $vault_source, string $paypal_customer_id = '', string $return_url = '', string $cancel_url = ''): array
    {
        if ($vault_source === 'applepay') {
            // PayPal requires payment_source.apple_pay with experience_context so its backend
            // can validate the Apple Pay token against the correct return/cancel endpoints.
            // Matches the official WooCommerce PayPal Payments plugin pattern (ApplepayModule.php).
            $ctx = [];
            if (!empty($return_url)) $ctx['return_url'] = $return_url;
            if (!empty($cancel_url)) $ctx['cancel_url'] = $cancel_url;
            return ['apple_pay' => !empty($ctx) ? ['experience_context' => $ctx] : new \stdClass()];
        }

        if ($vault_source === 'googlepay') {
            // Same requirement for Google Pay — experience_context with return/cancel URLs
            // is required for confirmOrder() to succeed in production.
            // Matches the official WooCommerce PayPal Payments plugin pattern (GooglepayModule.php).
            $ctx = [];
            if (!empty($return_url)) $ctx['return_url'] = $return_url;
            if (!empty($cancel_url)) $ctx['cancel_url'] = $cancel_url;
            return ['google_pay' => !empty($ctx) ? ['experience_context' => $ctx] : new \stdClass()];
        }

        if ($vault_source === 'paypal') {
            $vault_attrs = [
                'store_in_vault' => 'ON_SUCCESS',
                'usage_type'     => 'MERCHANT',
            ];
            // If we already have a PayPal Customer ID, link to their existing customer record
            if (!empty($paypal_customer_id)) {
                $vault_attrs['customer'] = ['id' => $paypal_customer_id];
            }
            return [
                'paypal' => [
                    'attributes' => [
                        'vault' => $vault_attrs,
                    ],
                ],
            ];
        }

        return [
            'card' => [
                'attributes' => [
                    'verification' => [
                        'method' => 'SCA_ALWAYS',
                    ],
                    'vault' => [
                        'store_in_vault' => 'ON_SUCCESS',
                    ],
                ],
            ],
        ];
    }

    public function create_order($access_token, $wc_order_id, $applied_coupons = null, $vault_source = 'card')
    {
        $wc_order = wc_get_order($wc_order_id);

        $create_order_url = $this->gateway->get_api_url('/v2/checkout/orders');

        $headers = [
            'Authorization'     => 'Bearer ' . $access_token,
            'Content-Type'      => 'application/json',
            'Prefer'            => 'return=representation',
            'PayPal-Request-Id' => uniqid('ppcp-', true),
        ];

        // If this customer already has a PayPal Customer ID, send it so PayPal can
        // associate the vault token with their existing customer record.
        $customer_id = (int)($wc_order ? $wc_order->get_customer_id() : 0);
        $paypal_customer_id = '';
        if ($customer_id > 0) {
            $paypal_customer_id = (string)get_user_meta($customer_id, 'paypal_customer_id', true);
            if (!empty($paypal_customer_id)) {
                $headers['PayPal-Customer-Id'] = $paypal_customer_id;
                error_log(sprintf('[GWC Vault] create_order: sending PayPal-Customer-Id=%s for WP user %d', $paypal_customer_id, $customer_id));
            }
        }

        $items = [
            [
                "amount" => [
                    "currency_code" => get_woocommerce_currency(),
                    "value" => $wc_order->get_total(),
                ],
                "invoice_id" => (string)$wc_order_id,
            ],
        ];

        $return_url = Growtype_Wc_Payment_Gateway::success_url($wc_order_id);
        $cancel_url = Growtype_Wc_Payment_Gateway::cancel_url($wc_order_id, false, $applied_coupons);

        // ORDER_COMPLETE_ON_PAYMENT_APPROVAL is used for card/paypal vault flows.
        // For Google Pay and Apple Pay, the official WooCommerce PayPal plugin does NOT
        // set processing_instruction — the standard confirmOrder flow handles completion.
        // Using this instruction with wallet payments prevents the confirm-payment-source
        // link from appearing in the order response, causing APPROVE_GOOGLE_PAY_VALIDATION_ERROR.
        $is_wallet = in_array($vault_source, ['googlepay', 'applepay'], true);
        $processing_instruction = $is_wallet ? null : 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL';

        $payment_source = $this->build_vault_payment_source($vault_source, $paypal_customer_id, $return_url, $cancel_url);

        $order_body = [
            "intent"          => "CAPTURE",
            "purchase_units"  => $items,
            "application_context" => [
                "return_url" => $return_url,
                "cancel_url" => $cancel_url,
            ],
        ];

        if ($processing_instruction) {
            $order_body["processing_instruction"] = $processing_instruction;
        }


        // Attach payment_source when set. Apple Pay uses {apple_pay:{}} and Google Pay uses
        // {google_pay:{}} — the empty object declares the payment method type to PayPal's backend
        // so it can route confirmOrder correctly. Card/PayPal include full vault attributes.
        if (!empty($payment_source)) {
            $order_body['payment_source'] = $payment_source;
        }

        // Always log the payment_source type for debugging — never log the full token.
        error_log(sprintf(
            '[GWC PayPal] create_order — vault_source=%s | payment_source keys=%s | order body (no sensitive data): intent=%s instruction=%s',
            $vault_source,
            implode(',', array_keys($payment_source)),
            $order_body['intent'],
            $order_body['processing_instruction'] ?? 'none'
        ));

        $response = wp_remote_post($create_order_url, [
            'headers' => $headers,
            'body'    => wp_json_encode($order_body),
        ]);

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true) ?: [];
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GWC PayPal] create_order raw response: ' . $body);
        }

        if (empty($data['id'])) {
            error_log('[GWC PayPal] create_order failed — no ID in response.');
        }

        // If PayPal returned a vault token immediately in the create response
        // (happens when the order is auto-captured or already approved), store it now.
        if ($wc_order && !empty($data['payment_source'])) {
            $vault_id_resp    = $data['payment_source']['paypal']['attributes']['vault']['id']
                ?? $data['payment_source']['card']['attributes']['vault']['id']
                ?? '';
            $pp_customer_resp = $data['payment_source']['paypal']['attributes']['vault']['customer']['id']
                ?? $data['payment_source']['card']['attributes']['vault']['customer']['id']
                ?? '';
            // Determine the vault type from what PayPal returned
            $vault_type_resp = isset($data['payment_source']['card']) ? 'card' : 'paypal';

            if (!empty($vault_id_resp)) {
                $wc_order->update_meta_data('paypal_vault_id', sanitize_text_field($vault_id_resp));
                $wc_order->update_meta_data('paypal_vault_type', $vault_type_resp);
                error_log(sprintf('[GWC Vault] create_order: stored vault_id=%s type=%s for order %d', $vault_id_resp, $vault_type_resp, $wc_order_id));
            }
            if (!empty($pp_customer_resp)) {
                $wc_order->update_meta_data('paypal_customer_id', sanitize_text_field($pp_customer_resp));
                if ($customer_id > 0) {
                    update_user_meta($customer_id, 'paypal_customer_id', sanitize_text_field($pp_customer_resp));
                    if (!empty($vault_id_resp)) {
                        update_user_meta($customer_id, 'paypal_vault_type', $vault_type_resp);
                    }
                }
                error_log(sprintf('[GWC Vault] create_order: stored paypal_customer_id=%s for user %d', $pp_customer_resp, $customer_id));
            }
            if (!empty($vault_id_resp) || !empty($pp_customer_resp)) {
                $wc_order->save();
            }
        }

        return $data;
    }

    public function capture_order($access_token, $order_id)
    {
        $capture_url = $this->gateway->get_api_url("/v2/checkout/orders/{$order_id}/capture");

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post($capture_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => '{}',
            'timeout' => 20,
        ]);

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true) ?: [];

        error_log(sprintf(
            '[GWC PayPal] capture_order: paypal_order_id=%s http_status=%d status=%s name=%s',
            $order_id,
            wp_remote_retrieve_response_code($response),
            $data['status'] ?? 'n/a',
            $data['name'] ?? 'n/a'
        ));

        if (empty($data['status']) && !empty($data['name'])) {
            error_log('[GWC PayPal] capture_order error body: ' . $body);
        }

        return $data;
    }

    public function get_vault_id_for_order($order): string
    {
        if (!$order instanceof WC_Order) {
            error_log('[GWC Vault] get_vault_id_for_order: not a WC_Order');
            return '';
        }

        $vault_id = (string)$order->get_meta('paypal_vault_id');
        if (!empty($vault_id)) {
            return $vault_id;
        }

        $customer_id = (int)$order->get_customer_id();
        if ($customer_id > 0) {
            $vault_id = (string)get_user_meta($customer_id, 'paypal_vault_id', true);
        }

        return $vault_id;
    }

    /**
     * Get the vault type ('card' or 'paypal') associated with an order or its customer.
     */
    public function get_vault_type_for_order($order): string
    {
        if (!$order instanceof WC_Order) {
            return 'card';
        }

        $type = (string)$order->get_meta('paypal_vault_type');
        if (!empty($type)) {
            return $type;
        }

        $customer_id = (int)$order->get_customer_id();
        if ($customer_id > 0) {
            $type = (string)get_user_meta($customer_id, 'paypal_vault_type', true);
        }

        return !empty($type) ? $type : 'card'; // default to card
    }

    public function charge_with_vault(string $vault_id, string $paypal_customer_id, WC_Order $upsell, string $vault_type = 'card'): array
    {
        $base_url = $this->gateway->get_api_url('/v2/checkout/orders');

        $access_token = $this->gateway->get_access_token($this->gateway->get_client_id(), $this->gateway->get_client_secret());

        // Build the correct payment_source depending on vault type.
        // Card vault tokens use stored_credential for merchant-initiated unscheduled charges.
        // PayPal account vault tokens use a simpler paypal.vault_id structure.
        if ($vault_type === 'paypal') {
            $payment_source = [
                'paypal' => [
                    'vault_id' => $vault_id,
                ],
            ];
        } else {
            $payment_source = [
                'card' => [
                    'vault_id'          => $vault_id,
                    'stored_credential' => [
                        'payment_initiator' => 'MERCHANT',
                        'payment_type'      => 'UNSCHEDULED',
                        'usage'             => 'SUBSEQUENT',
                    ],
                ],
            ];
        }

        $body = [
            'intent'         => 'CAPTURE',
            'customer'       => ['id' => $paypal_customer_id],
            'purchase_units' => [[
                'amount'     => [
                    'currency_code' => $upsell->get_currency(),
                    'value'         => number_format((float)$upsell->get_total(), 2, '.', ''),
                ],
                'invoice_id' => (string)$upsell->get_id(),
            ]],
            'payment_source' => $payment_source,
        ];

        error_log(sprintf('[GWC Vault] charge_with_vault: vault_type=%s vault_id=%s customer=%s amount=%s', $vault_type, $vault_id, $paypal_customer_id, $upsell->get_total()));

        $response = wp_remote_post($base_url, [
            'headers' => [
                'Authorization'     => 'Bearer ' . $access_token,
                'Content-Type'      => 'application/json',
                'Prefer'            => 'return=representation',
                'PayPal-Request-Id' => uniqid('gwc-vault-', true),
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('PayPal vault charge HTTP error: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true) ?: [];

        if (($data['status'] ?? '') === 'APPROVED' && !empty($data['id'])) {
            $data = $this->capture_order($access_token, $data['id']);
        }

        if (($data['status'] ?? '') !== 'COMPLETED') {
            $detail = $data['details'][0]['description'] ?? $data['message'] ?? 'Vault charge failed.';
            throw new \Exception('PayPal vault charge failed: ' . $detail);
        }

        return $data;
    }

    public function get_paypal_customer_id_for_vault(string $vault_id): string
    {
        $base_url = $this->gateway->get_api_url("/v3/vault/payment-tokens/{$vault_id}");

        $access_token = $this->gateway->get_access_token($this->gateway->get_client_id(), $this->gateway->get_client_secret());

        $response = wp_remote_get($base_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $data = json_decode(wp_remote_retrieve_body($response), true) ?: [];
        return $data['customer']['id'] ?? '';
    }

    public function charge_intent($parent_order_id, $product_id, $description)
    {
        $parent = wc_get_order($parent_order_id);
        if (!$parent) {
            error_log(sprintf('[GWC PayPal] charge_intent() failed: parent order ID %d not found.', $parent_order_id));
            throw new \Exception("Invalid parent order ID: {$parent_order_id}");
        }

        // Subscription orders are paid via PayPal Billing Agreements — they have no
        // vault token. charge_intent() would fall back to a redirect creating a second
        // full PayPal checkout, causing a duplicate charge.
        if (class_exists('Growtype_Wc_Subscription') && Growtype_Wc_Subscription::is_subscription_order($parent_order_id)) {
            error_log(sprintf('[GWC PayPal] charge_intent() blocked: order %d is a subscription order — skipping to prevent duplicate charge.', $parent_order_id));
            throw new \Exception("charge_intent() blocked: order {$parent_order_id} is a subscription order. Use the subscription billing flow.");
        }

        $upsell = wc_create_order();
        $upsell->update_meta_data('parent_order_id', $parent_order_id);
        if ($parent->get_customer_id()) {
            $upsell->set_customer_id($parent->get_customer_id());
        }

        $prod = wc_get_product($product_id);
        if (!$prod) {
            throw new \Exception("Invalid product ID: {$product_id}");
        }
        $upsell->add_product($prod, 1);
        $upsell->set_payment_method($this->gateway->id);
        $upsell->set_payment_method_title($this->gateway->get_hosted_fields_title());
        $upsell->set_currency($parent->get_currency());
        $upsell->calculate_totals();
        $upsell->save();

        $vault_id    = $this->get_vault_id_for_order($parent);
        $vault_type  = $this->get_vault_type_for_order($parent);
        $pp_customer = '';

        if (!empty($vault_id)) {
            $pp_customer = (string)$parent->get_meta('paypal_customer_id');
            if (empty($pp_customer) && $parent->get_customer_id() > 0) {
                $pp_customer = (string)get_user_meta((int)$parent->get_customer_id(), 'paypal_customer_id', true);
            }

            if (empty($pp_customer)) {
                $pp_customer = $this->get_paypal_customer_id_for_vault($vault_id);
                if (!empty($pp_customer) && $parent->get_customer_id() > 0) {
                    update_user_meta((int)$parent->get_customer_id(), 'paypal_customer_id', $pp_customer);
                }
            }

            error_log(sprintf('[GWC Vault] charge_intent: vault_id=%s vault_type=%s pp_customer=%s', $vault_id, $vault_type, $pp_customer));
        } else {
            error_log(sprintf('[GWC Vault] charge_intent: no vault_id found for parent order %d — will fall back to PayPal redirect.', $parent_order_id));
        }

        if (!empty($vault_id) && !empty($pp_customer)) {
            try {
                $capture_data = $this->charge_with_vault($vault_id, $pp_customer, $upsell, $vault_type);
                $capture_id = '';
                foreach ($capture_data['purchase_units'] ?? [] as $pu) {
                    foreach ($pu['payments']['captures'] ?? [] as $c) {
                        if (($c['status'] ?? '') === 'COMPLETED') {
                            $capture_id = $c['id'];
                            break 2; // exit both loops
                        }
                    }
                }

                $upsell->payment_complete($capture_id);
                $upsell->add_order_note(sprintf('Instant charge successful via PayPal vault. Capture ID: %s | Type: %s', $capture_id, $vault_type));

                return [
                    'pi'       => (object)['status' => 'succeeded'],
                    'order_id' => $upsell->get_id(),
                ];
            } catch (\Exception $e) {
                error_log('[GWC Vault] charge_intent: vault charge FAILED, falling back to redirect. Reason: ' . $e->getMessage());
            }
        }

        // Fallback: create a new PayPal order and redirect user for approval.
        // Use vault_source='paypal' so PayPal will vault the account on success,
        // enabling instant charges on the NEXT purchase.
        $access_token = $this->gateway->get_access_token($this->gateway->get_client_id(), $this->gateway->get_client_secret());
        $checkout     = $this->create_order($access_token, $upsell->get_id(), null, 'paypal');

        if (empty($checkout['links']) || !is_array($checkout['links'])) {
            throw new \Exception('Unexpected PayPal response creating upsell order.');
        }

        $upsell->update_meta_data('paypal_token', sanitize_text_field($checkout['id'] ?? ''));

        foreach ($checkout['links'] ?? [] as $link) {
            $rel  = is_object($link) ? ($link->rel ?? '') : ($link['rel'] ?? '');
            $href = is_object($link) ? ($link->href ?? '') : ($link['href'] ?? '');

            if ($rel === 'approve' && !empty($href)) {
                // Validate the approve URL is a PayPal domain before redirecting
                $parsed_href = parse_url($href);
                $href_host   = strtolower($parsed_href['host'] ?? '');
                if (!str_ends_with($href_host, 'paypal.com')) {
                    error_log('[GWC Vault] charge_intent: approve link rejected — not a PayPal domain: ' . $href);
                    continue;
                }
                $upsell->update_meta_data('payment_provider_checkout_url', $href);
                $upsell->save();
                error_log(sprintf('[GWC Vault] charge_intent fallback: redirecting to PayPal approve URL for upsell order %d', $upsell->get_id()));
                wp_redirect($href);
                exit;
            }
        }

        // No 'approve' link found — build the PayPal checkoutnow URL directly from the order token.
        // This handles sandbox edge cases where PayPal omits the approve link.
        $paypal_order_token = $checkout['id'] ?? '';
        if (!empty($paypal_order_token)) {
            $base         = $this->gateway->is_test_mode() ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
            $fallback_url = $base . '/checkoutnow?token=' . urlencode($paypal_order_token);
            error_log(sprintf('[GWC Vault] charge_intent fallback: no approve link found, redirecting to checkoutnow: %s', $fallback_url));
            $upsell->update_meta_data('payment_provider_checkout_url', $fallback_url);
            $upsell->save();
            wp_redirect($fallback_url);
            exit;
        }

        throw new \Exception('Could not create PayPal order for upsell — no order ID returned.');
    }

    /**
     * Get the order status to set after payment completion.
     *
     * @param string $status Current order status.
     * @param int $order_id Order ID.
     * @param WC_Order|false $order Order object.
     * @return string
     */
    public function get_completed_order_status($status, $order_id = 0, $order = false): string
    {
        return 'completed';
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $failed_notice = apply_filters('growtype_wc_payment_gateway_paypal_process_payment_failed_notice', '');

        if (!empty($failed_notice)) {
            return array (
                'result' => 'failure'
            );
        }

        global $woocommerce;

        $order = wc_get_order($order_id);

        $order->payment_complete();

        wc_reduce_stock_levels($order_id);

        $order_status = apply_filters('growtype_wc_process_payment_order_status_gateway_' . $this->gateway->id, 'completed', $order_id, $order);

        $order->update_status($order_status);

        $woocommerce->cart->empty_cart();

        return array (
            'result' => 'success',
            'redirect' => Growtype_Wc_Payment_Gateway::success_url($order_id, Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID)
        );
    }
}
