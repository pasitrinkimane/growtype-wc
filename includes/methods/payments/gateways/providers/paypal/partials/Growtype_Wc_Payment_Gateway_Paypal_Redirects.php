<?php

/**
 * PayPal Gateway Redirects (Add to Cart and Thank You page verification).
 */
class Growtype_Wc_Payment_Gateway_Paypal_Redirects
{
    /** @var Growtype_Wc_Payment_Gateway_Paypal */
    private $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;

        add_action('woocommerce_add_to_cart', [$this, 'woocommerce_add_to_cart_extend'], 5, 6);
        add_filter('template_redirect', [$this, 'payment_redirect']);
    }

    public function woocommerce_add_to_cart_extend($cart_item_key, $wc_product_id, $quantity, $variation_id, $variation_attributes, $cart_item_data)
    {
        $setting_enabled = $this->gateway->get_option('add_to_card_redirect_paypal_checkout') === 'yes' 
            || get_option(Growtype_Wc_Payment_Settings::OPTION_REDIRECT_PAYPAL) === 'yes';
        $method_match = isset($_GET['payment_method']) && $_GET['payment_method'] === Growtype_Wc_Payment_Gateway_Paypal::PAYMENT_METHOD_KEY;

        error_log(sprintf('[GWC PayPal] woocommerce_add_to_cart_extend: product=%d setting_enabled=%s method_match=%s', 
            $wc_product_id, 
            $setting_enabled ? 'yes' : 'no',
            $method_match ? 'yes' : 'no'
        ));

        do_action('growtype_wc_before_add_to_cart', $cart_item_key, $wc_product_id, $quantity, $variation_id, $variation_attributes, $cart_item_data);

        if ($setting_enabled && $method_match) {
            error_log('[GWC PayPal] woocommerce_add_to_cart_extend: Starting redirect flow...');
            $wc_product = wc_get_product($wc_product_id);
            if (!$wc_product) {
                error_log('[GWC PayPal] woocommerce_add_to_cart_extend: Product not found.');
                return;
            }

            $order = wc_create_order();
            if (!$order) {
                error_log('[GWC PayPal] woocommerce_add_to_cart_extend: Order creation failed.');
                return;
            }

            $order_id = $order->get_id();
            error_log('[GWC PayPal] woocommerce_add_to_cart_extend: Created WC order ' . $order_id);

            $order->add_product($wc_product, $quantity);
            $order->set_payment_method($this->gateway->id);

            $applied_coupons = WC()->cart ? WC()->cart->get_applied_coupons() : [];

            if (!empty($applied_coupons)) {
                foreach ($applied_coupons as $applied_coupon) {
                    $order->apply_coupon($applied_coupon);
                }
            }

            $order->calculate_totals();

            if (is_user_logged_in()) {
                $order->set_customer_id(get_current_user_id());
            }

            $cancel_url = Growtype_Wc_Payment_Gateway::cancel_url($order_id, false, $applied_coupons);

            if (WC()->cart) {
                WC()->cart->empty_cart();
            }

            try {
                $access_token = $this->gateway->get_access_token($this->gateway->get_client_id(), $this->gateway->get_client_secret());

                if (growtype_wc_product_is_subscription($wc_product->get_id())) {
                    error_log('[GWC PayPal] woocommerce_add_to_cart_extend: Processing subscription...');
                    $paypal_product = $this->gateway->subscriptions->create_product($access_token, $wc_product->get_id());
                    $subscription_plan = $this->gateway->subscriptions->create_billing_plan($access_token, $paypal_product, $wc_product_id, $applied_coupons);
                    $subscription_plan_id = $subscription_plan['id'] ?? '';

                    if (empty($subscription_plan_id)) {
                    error_log('[GWC PayPal] Subscription plan creation failed.');
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[GWC PayPal] Subscription plan response: ' . wp_json_encode($subscription_plan));
                    }
                        throw new \Exception(__('Subscription plan creation failed.', 'growtype-wc'));
                    }

                    $paypal_checkout = $this->gateway->subscriptions->create_subscription($access_token, $subscription_plan_id, $order_id, $applied_coupons);

                    if (isset($paypal_checkout['id']) && !empty($paypal_checkout['id'])) {
                        $order->update_meta_data('paypal_subscription_id', $paypal_checkout['id']);
                    }
                } else {
                    error_log('[GWC PayPal] woocommerce_add_to_cart_extend: Processing standard order...');
                    $paypal_checkout = $this->gateway->orders->create_order($access_token, $order_id, $applied_coupons, 'paypal');
                }

                error_log('[GWC PayPal] woocommerce_add_to_cart_extend: PayPal response: ' . wp_json_encode($paypal_checkout));

                if (defined('WP_DEBUG') && WP_DEBUG && isset($paypal_checkout['name']) && $paypal_checkout['name'] === 'INVALID_REQUEST') {
                    error_log('[GWC PayPal] Invalid request response: ' . wp_json_encode($paypal_checkout));
                }

                if (!empty($paypal_checkout['links'])) {
                    error_log('[GWC PayPal] woocommerce_add_to_cart_extend: Links found: ' . count($paypal_checkout['links']));
                    foreach ($paypal_checkout['links'] as $link) {
                        $link = (array)$link;
                        error_log('[GWC PayPal] Checking link rel: ' . ($link['rel'] ?? 'none'));

                        if ($link['rel'] === 'approve' || $link['rel'] === 'payer-action') {
                            $checkout_url = $link['href'];
                            error_log('[GWC PayPal] Redirecting to: ' . $checkout_url);

                            $parsed_url = parse_url($checkout_url);
                            $query = [];
                            parse_str($parsed_url['query'], $query);
                            $ba_token = isset($query['ba_token']) ? $query['ba_token'] : null;
                            $token = isset($query['token']) ? $query['token'] : null;

                            $order->update_meta_data('payment_provider_checkout_url', $checkout_url);

                            if (!empty($ba_token)) {
                                $order->update_meta_data('paypal_ba_token', $ba_token);
                            }

                            if (isset($subscription_plan_id) && !empty($subscription_plan_id)) {
                                $order->update_meta_data('paypal_subscription_plan_id', $subscription_plan_id);
                            }

                            if (!empty($token)) {
                                $order->update_meta_data('paypal_token', $token);
                            }

                            do_action('woocommerce_checkout_create_order', $order, $cart_item_data);

                            $order->save();

                            // Validate the redirect URL is a PayPal domain (defense-in-depth)
                            $parsed = parse_url($checkout_url);
                            $host   = strtolower($parsed['host'] ?? '');
                            if (!str_ends_with($host, 'paypal.com')) {
                                error_log('[GWC PayPal] Redirect URL rejected — not a PayPal domain: ' . $checkout_url);
                                break;
                            }

                            wp_redirect($checkout_url);
                            exit;
                        }
                    }
                } else {
                    error_log(sprintf('[GWC PayPal] No links in response for order %s', $order_id));
                }
            } catch (\Exception $e) {
                error_log(sprintf('[GWC PayPal] add_to_cart error: %s', $e->getMessage()));
                $order->update_status('failed', sprintf(__('Reason %s.', 'growtype-wc'), wc_clean($e->getMessage())));
            }

            wp_redirect($cancel_url);
            exit();
        }
    }

    public function payment_redirect()
    {
        if (!growtype_wc_is_thankyou_page()) {
            return;
        }

        global $wp;

        $order_id = apply_filters('woocommerce_thankyou_order_id', absint($wp->query_vars['order-received']));
        $order = wc_get_order($order_id);

        if (!$order || $order->get_status() === 'completed') {
            return;
        }

        // Ownership check: if a user is logged in, ensure this order belongs to them.
        // Guests (customer_id=0) are allowed through — they have no account to compare.
        if (is_user_logged_in()) {
            $order_customer_id = (int)$order->get_customer_id();
            if ($order_customer_id > 0 && $order_customer_id !== get_current_user_id()) {
                error_log(sprintf('[GWC PayPal] payment_redirect: ownership check failed for order %d — customer %d vs current user %d', $order_id, $order_customer_id, get_current_user_id()));
                return;
            }
        }

        $payment_method = $order->get_payment_method();

        if ($payment_method === Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID) {
            $paypal_order_id = sanitize_text_field($_GET['token'] ?? '');
            $paypal_ba_token = sanitize_text_field($_GET['ba_token'] ?? '');

            error_log(sprintf(
                'PayPal payment_redirect: order %d | status: %s | GET token: %s | paypal_token meta: %s | _paypal_hosted_order_id meta: %s',
                $order_id,
                $order->get_status(),
                $paypal_order_id,
                $order->get_meta('paypal_token'),
                $order->get_meta('_paypal_hosted_order_id')
            ));

            if (Growtype_Wc_Subscription::is_subscription_order($order_id)) {
                if ($paypal_ba_token !== $order->get_meta('paypal_ba_token')) {
                    return null;
                }
            } else {
                $stored_token         = $order->get_meta('paypal_token');
                $stored_hosted_token  = $order->get_meta('_paypal_hosted_order_id');

                if ($paypal_order_id !== $stored_token && $paypal_order_id !== $stored_hosted_token) {
                    error_log(sprintf(
                        'PayPal payment_redirect: token mismatch for order %d. GET token: %s | paypal_token: %s | _paypal_hosted_order_id: %s',
                        $order_id, $paypal_order_id, $stored_token, $stored_hosted_token
                    ));
                    return null;
                }
            }

            $access_token = $this->gateway->get_access_token($this->gateway->get_client_id(), $this->gateway->get_client_secret());
            $is_subscription = Growtype_Wc_Subscription::is_subscription_order($order_id);

            if ($is_subscription) {
                $subscription_id = sanitize_text_field($_GET['subscription_id'] ?? $order->get_meta('paypal_subscription_id'));
                if (!empty($subscription_id)) {
                    $order->update_meta_data('paypal_subscription_id', $subscription_id);
                }
                $paypal_order_data = $this->gateway->subscriptions->get_subscription_data($access_token, $subscription_id);
            } else {
                $paypal_order_data = $this->gateway->orders->get_order_data($access_token, $paypal_order_id);
            }

            $customer_email = $paypal_order_data['payer']['email_address'] ?? '';
            if (empty($customer_email) && isset($paypal_order_data['subscriber']['email_address'])) {
                $customer_email = $paypal_order_data['subscriber']['email_address'];
            }

            if (!empty($customer_email)) {
                Growtype_Wc_Payment_Gateway::update_user_email_if_not_exists(get_current_user_id(), $customer_email);
                Growtype_Wc_Payment_Gateway::update_order_email_if_not_exists($order_id, $customer_email);
            }

            if (isset($paypal_order_data['status'])) {
                $is_approved = false;
                if ($is_subscription) {
                    $is_approved = in_array($paypal_order_data['status'], ['ACTIVE', 'APPROVED']);
                } else {
                    $is_approved = in_array($paypal_order_data['status'], ['APPROVED', 'COMPLETED']);
                }

                if ($is_approved) {
                    $capture_data = [];

                    if ($paypal_order_data['status'] === 'APPROVED') {
                        $order->add_order_note(__(sprintf('Order id: %s', $paypal_order_id), 'growtype-wc'));

                        if (isset($paypal_order_data['intent']) && $paypal_order_data['intent'] === 'CAPTURE') {
                            foreach ($paypal_order_data['links'] as $link) {
                                if ($link['rel'] === 'capture') {
                                    $capture_data = $this->gateway->orders->capture_order($access_token, $paypal_order_id);
                                    break;
                                }
                            }
                        }
                    } else {
                        $capture_data = $paypal_order_data;
                    }

                    // ── Vault diagnostic: dump full payment_source and purchase_units ────
                    error_log(sprintf('[GWC Vault] payment_redirect full capture_data payment_source: %s', wp_json_encode($capture_data['payment_source'] ?? null)));
                    error_log(sprintf('[GWC Vault] payment_redirect full capture_data status: %s', $capture_data['status'] ?? 'n/a'));
                    if (!empty($capture_data['purchase_units'])) {
                        foreach ($capture_data['purchase_units'] as $pu_idx => $pu) {
                            $captures = $pu['payments']['captures'] ?? [];
                            foreach ($captures as $cap_idx => $cap) {
                                error_log(sprintf(
                                    '[GWC Vault] purchase_units[%d].payments.captures[%d]: status=%s payment_source=%s',
                                    $pu_idx, $cap_idx,
                                    $cap['status'] ?? 'n/a',
                                    wp_json_encode($cap['payment_source'] ?? null)
                                ));
                            }
                        }
                    }
                    // ── End diagnostic ───────────────────────────────────────────────────

                    // Path 1: top-level payment_source (hosted-fields / card flow)
                    $vault_id    = $capture_data['payment_source']['paypal']['attributes']['vault']['id'] ?? '';
                    $pp_customer = $capture_data['payment_source']['paypal']['attributes']['vault']['customer']['id'] ?? '';

                    if (empty($vault_id)) {
                        $vault_id    = $capture_data['payment_source']['card']['attributes']['vault']['id'] ?? '';
                        $pp_customer = $capture_data['payment_source']['card']['attributes']['vault']['customer']['id'] ?? '';
                    }

                    // Path 2: vault data inside purchase_units[].payments.captures[] (standard redirect flow)
                    if (empty($vault_id) && !empty($capture_data['purchase_units'])) {
                        foreach ($capture_data['purchase_units'] as $pu) {
                            foreach ($pu['payments']['captures'] ?? [] as $cap) {
                                $v = $cap['payment_source']['paypal']['attributes']['vault']['id']
                                    ?? $cap['payment_source']['card']['attributes']['vault']['id']
                                    ?? '';
                                $c = $cap['payment_source']['paypal']['attributes']['vault']['customer']['id']
                                    ?? $cap['payment_source']['card']['attributes']['vault']['customer']['id']
                                    ?? '';
                                if (!empty($v)) {
                                    $vault_id    = $v;
                                    $pp_customer = $c;
                                    error_log(sprintf('[GWC Vault] Found vault_id in purchase_units captures: %s customer: %s', $vault_id, $pp_customer));
                                    break 2;
                                }
                            }
                        }
                    }

                    // Path 3: paypal_vault_id may have been set on the order by create_order already
                    if (empty($vault_id)) {
                        $vault_id    = $order->get_meta('paypal_vault_id');
                        $pp_customer = $order->get_meta('paypal_customer_id');
                        if (!empty($vault_id)) {
                            error_log(sprintf('[GWC Vault] Using vault_id from order meta (set during create_order): %s', $vault_id));
                        }
                    }

                    error_log(sprintf('[GWC Vault] payment_redirect final result: order=%d vault_id=%s pp_customer=%s', $order_id, $vault_id, $pp_customer));

                    if (!empty($vault_id)) {
                        $order->update_meta_data('paypal_vault_id', sanitize_text_field($vault_id));
                        // Vault tokens from PayPal redirect are PayPal account tokens, not card tokens
                        $order->update_meta_data('paypal_vault_type', 'paypal');
                    }
                    if (!empty($pp_customer)) {
                        $order->update_meta_data('paypal_customer_id', sanitize_text_field($pp_customer));
                        if ($order->get_customer_id() > 0) {
                            update_user_meta((int)$order->get_customer_id(), 'paypal_customer_id', sanitize_text_field($pp_customer));
                            update_user_meta((int)$order->get_customer_id(), 'paypal_vault_type', 'paypal');
                            error_log(sprintf('[GWC Vault] Saved paypal_customer_id=%s to WP user %d', $pp_customer, $order->get_customer_id()));
                        }
                    }
                    if (empty($vault_id)) {
                        error_log('[GWC Vault] ⚠ vault_id still empty after all path checks — PayPal did not return vault data. Ensure "Vault" is enabled in PayPal PPCP settings and the merchant account supports Reference Transactions.');
                    }

                    if (Growtype_Wc_Subscription::is_subscription_order($order_id)) {
                        $paypal_subscription_id = $order->get_meta('paypal_subscription_id');
                        $order->add_order_note(__(sprintf('Subscription id: %s', $paypal_subscription_id), 'growtype-wc'));
                    }

                    $order->save();
                    $order->payment_complete();
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf('Order %s is not approved. PayPal order id: %s. Status: %s.', $order_id, $paypal_order_id, $paypal_order_data['status'] ?? 'unknown'));
                    }
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('Order %s is not paid and status is missing. PayPal order id: %s.', $order_id, $paypal_order_id));
                }
            }
        }
    }
}
