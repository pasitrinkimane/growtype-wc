<?php

class Growtype_Wc_Payment
{

    public function __construct()
    {
        $this->load_methods();

        add_action('growtype_wc_before_add_to_cart', [$this, 'handle_disabled_payment'], 10, 6);
        add_action('template_redirect', [$this, 'process_upsell_endpoint']);

        add_action('wp_ajax_growtype_wc_create_payment_intent', [$this, 'ajax_create_payment_intent']);
        add_action('wp_ajax_nopriv_growtype_wc_create_payment_intent', [$this, 'ajax_create_payment_intent']);

        add_action('wp_ajax_growtype_wc_get_payment_info', [$this, 'ajax_get_payment_info']);
        add_action('wp_ajax_nopriv_growtype_wc_get_payment_info', [$this, 'ajax_get_payment_info']);

        add_action('wp_ajax_growtype_wc_finalize_order', [$this, 'ajax_finalize_order']);
        add_action('wp_ajax_nopriv_growtype_wc_finalize_order', [$this, 'ajax_finalize_order']);
    }

    protected function load_methods()
    {
        include_once __DIR__ . '/gateways/Growtype_Wc_Payment_Gateway.php';
        new Growtype_Wc_Payment_Gateway();
    }

    public static function disabled_payment_methods_notice(): string
    {
        $default = 'Due to a high volume of orders, we are temporarily unable to accept new ones.';
        return esc_textarea(get_option('growtype_wc_disabled_payment_methods_notice', $default));
    }

    public static function all_disabled(): bool
    {
        return (bool)get_option('growtype_wc_disable_all_payment_methods', false);
    }

    public static function intent_url($base_url, int $order_id, int $product_id): string
    {
        return add_query_arg([
            'action' => 'gwc_charge_intent',
            'order_id' => $order_id,
            'product_id' => $product_id,
        ], $base_url);
    }

    /**
     * 1) Block add-to-cart when all payments are disabled.
     */
    public function handle_disabled_payment($cart_item_key, $product_id, $qty, $variation_id, $variation_attrs, $cart_item_data)
    {
        if (!self::all_disabled()) {
            return;
        }

        // Only show once
        if (isset($_GET['payment_failed'])) {
            return;
        }

        wc_add_notice(self::disabled_payment_methods_notice(), 'error');

        // Create a failed order to preserve the cart state
        $product = wc_get_product($product_id);
        $order = wc_create_order();
        $order->add_product($product, $qty);

        // Set payment method from URL (if any)
        $method_key = sanitize_text_field($_GET['payment_method'] ?? '');
        $provider = str_replace('gwc-', '', $method_key);
        $class_name = "Growtype_Wc_Payment_Gateway_{$provider}";
        if (class_exists($class_name)) {
            $pm = $class_name::PROVIDER_ID;
            $order->set_payment_method($pm);
        }

        // Copy coupons
        foreach (WC()->cart->get_applied_coupons() as $coupon) {
            $order->apply_coupon($coupon);
        }

        $order->calculate_totals();

        if (is_user_logged_in()) {
            $order->set_customer_id(get_current_user_id());
        }

        $order->update_status('failed');
        $order->save();

        WC()->cart->empty_cart();

        $referer = remove_query_arg(['add-to-cart', 'payment_method'], wp_get_referer());
        $redirect_to = $referer ?: wc_get_checkout_url();
        $redirect_url = add_query_arg('payment_failed', '1', $redirect_to);

        // Prevent infinite loops
        remove_all_actions('woocommerce_add_to_cart');

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * 4) On template_redirect, handle our upsell endpoint.
     */
    public function process_upsell_endpoint()
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'gwc_charge_intent') {
            return;
        }

        $order_id = absint($_GET['order_id'] ?? 0);
        $product_id = absint($_GET['product_id'] ?? 0);

        if (!$order_id || !$product_id) {
            error_log('Upsell endpoint error: Missing order or product ID.');
            wp_die('Invalid parameters.', 'Error', ['response' => 400]);
        }

        // Prevent double charges - use transient lock
        $lock_key = 'gwc_upsell_lock_' . $order_id . '_' . $product_id;
        $existing_lock = get_transient($lock_key);
        
        if ($existing_lock) {
            // Already processing or recently processed - redirect without charging again
            error_log('Upsell double-charge prevented: Order ' . $order_id . ', Product ' . $product_id);
            
            $order = wc_get_order($order_id);
            if ($order) {
                $redirect_url = remove_query_arg('upsell', $order->get_checkout_order_received_url());
                wp_safe_redirect($redirect_url);
                exit;
            }
            
            // Fallback: redirect to same page without the action
            $current_url = remove_query_arg(['action', 'order_id', 'product_id']);
            wp_safe_redirect($current_url);
            exit;
        }
        
        // Set lock for 30 seconds to prevent duplicate requests
        set_transient($lock_key, time(), 30);

        try {
            $order = wc_get_order($order_id);

            $product = wc_get_product($product_id);

            if (!$order || !$product) {
                delete_transient($lock_key); // Release lock on error
                throw new \Exception('Order or product not found.');
            }

            /** @var Growtype_Wc_Payment_Gateway_Stripe $gateway */
            $gateway = WC()->payment_gateways()->payment_gateways()[$order->get_payment_method()];

            $description = sprintf('Upsell #%d for Order #%d', $product_id, $order_id);

            $pi = $gateway->charge_intent($order_id, $product_id, $description);

            if ($pi->status !== 'succeeded') {
                delete_transient($lock_key); // Release lock on failed charge
                throw new \Exception('Payment Intent status: ' . $pi->status);
            }

            // Extend lock to 5 minutes after successful charge (prevent re-purchase)
            set_transient($lock_key, time(), 300);

            $redirect_url = $gateway->get_return_url($order);

            /**
             * Determine next upsell
             */
            if (class_exists('Growtype_Wc_Upsell')) {
                $upsells = Growtype_Wc_Upsell::get();
                $current_product_slug = $product->get_slug();
                
                $current_index = -1;
                foreach ($upsells as $index => $u) {
                    if ($u['slug'] === $current_product_slug) {
                        $current_index = $index;
                        break;
                    }
                }

                $next_slug = '';
                if ($current_index !== -1 && isset($upsells[$current_index + 1])) {
                    $next_slug = $upsells[$current_index + 1]['slug'];
                }

                if ($next_slug) {
                    $redirect_url = add_query_arg('upsell', $next_slug, $redirect_url);
                } else {
                    $redirect_url = remove_query_arg('upsell', $redirect_url);
                }
            }

            // Redirect back to order received (or wherever)
            wp_safe_redirect($redirect_url);
            exit;

        } catch (\Exception $e) {
            delete_transient($lock_key); // Release lock on exception
            error_log('Upsell endpoint error: ' . $e->getMessage());
            wp_die('Upsell charge failed: ' . esc_html($e->getMessage()), 'Error', ['response' => 500]);
        }
    }
    /**
     * Create an order instantly from product ID, applying current cart context (coupons, user).
     * 
     * @param int $product_id
     * @param int $qty
     * @param string $payment_method
     * @return \WC_Order
     * @throws \Exception
     */
    public static function create_instant_order($product_id, $qty = 1, $payment_method = '', $payment_method_type = '')
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            throw new \Exception('Product not found');
        }

        $order = wc_create_order();
        $order->add_product($product, $qty);

        if ($payment_method) {
            $order->set_payment_method($payment_method);
            
            // Enhance title with specific type if provided
            if ($payment_method === Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID && !empty($payment_method_type)) {
                $gateways = WC()->payment_gateways()->payment_gateways();
                $stripe_gateway = $gateways[$payment_method] ?? null;
                $base_title = $stripe_gateway ? $stripe_gateway->method_title : 'Growtype WC - ' . Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID;

                $type_formatted = ucfirst($payment_method_type);
                $order->set_payment_method_title("$base_title ($type_formatted)");
                $order->update_meta_data('_stripe_payment_method_type', $payment_method_type);
            }
        }
        
        // Apply coupons from current session
        if (WC()->cart) {
            $applied_coupons = WC()->cart->get_applied_coupons();
            if (!empty($applied_coupons)) {
                foreach ($applied_coupons as $applied_coupon) {
                    $order->apply_coupon($applied_coupon);
                }
            }
        }

        if (is_user_logged_in()) {
            $order->set_customer_id(get_current_user_id());
        }

        $order->calculate_totals();
        $order->save();
        
        return $order;
    }

    /**
     * AJAX: Create Payment Intent for Instant Checkout
     */
    public function ajax_create_payment_intent()
    {
        check_ajax_referer('growtype_wc_ajax_nonce', 'nonce');

        try {
            $product_id = absint($_POST['product_id'] ?? 0);
            if (!$product_id) {
                throw new \Exception('Invalid product ID');
            }

            $payment_method_type = sanitize_text_field($_POST['payment_method_type'] ?? '');

            // 1. Create Order using shared logic
            $order = self::create_instant_order($product_id, 1, Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID, $payment_method_type);
            $order_id = $order->get_id();

            // 2. Get Stripe Gateway to use its config
            $gateways = WC()->payment_gateways()->payment_gateways();
            $stripe_gateway = $gateways[Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID] ?? null;

            if (!$stripe_gateway) {
                throw new \Exception('Stripe gateway not available');
            }

            $secret_key = $stripe_gateway->get_secret_key();
            if (empty($secret_key)) {
                throw new \Exception('Stripe secret key is missing in settings');
            }

            $stripe = new \Stripe\StripeClient($secret_key);

            // 3. Create Payment Intent
            $intent_params = [
                'amount' => intval(round($order->get_total() * 100)),
                'currency' => strtolower($order->get_currency()),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'setup_future_usage' => 'off_session',
                'metadata' => [
                    'order_id' => $order_id,
                ],
            ];

            // If user is logged in, ensure we have a Stripe customer
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
                
                if (!$customer_id) {
                    $user = get_userdata($user_id);
                    try {
                        $customer = $stripe->customers->create([
                            'email' => $user->user_email,
                            'name' => $user->display_name,
                            'metadata' => ['user_id' => $user_id]
                        ]);
                        $customer_id = $customer->id;
                        update_user_meta($user_id, 'stripe_customer_id', $customer_id);
                    } catch (\Exception $e) {
                        error_log('Growtype WC: Failed to create customer during intent: ' . $e->getMessage());
                    }
                }

                if ($customer_id) {
                    $intent_params['customer'] = $customer_id;
                }
            }

            $intent = $stripe->paymentIntents->create($intent_params);

            // Fetch account info if possible or just log the intent's account if returned
            // Stripe-PHP doesn't always return the account ID on the intent unless expanded or using Connect
            // But we can check if the client has a default account or just log more info.
            
            error_log(sprintf(
                'Growtype WC: Created Payment Intent %s for Order %d. Key prefix: %s. Status: %s',
                $intent->id,
                $order_id,
                substr($secret_key, 0, 20),
                $intent->status
            ));

            $order->update_meta_data('stripe_intent_id', $intent->id);
            $order->save();

            // 4. Return Data
            wp_send_json_success([
                'order_id' => $order_id,
                'clientSecret' => $intent->client_secret,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'label' => 'Total', 
                'success_url' => Growtype_Wc_Payment_Gateway::success_url($order_id, Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID),
                'debug' => [
                    'test_mode' => $stripe_gateway->test_mode ? 'yes' : 'no',
                    'key_prefix' => substr($secret_key, 0, 7),
                ]
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    /**
     * AJAX: Get Payment Info for Instant Checkout (without creating order)
     */
    public function ajax_get_payment_info()
    {
        check_ajax_referer('growtype_wc_ajax_nonce', 'nonce');

        try {
            $product_id = absint($_POST['product_id'] ?? 0);
            if (!$product_id) {
                throw new \Exception('Invalid product ID');
            }

            $product = wc_get_product($product_id);
            if (!$product) {
                throw new \Exception('Product not found');
            }

            // Mock an order total for element initialization
            $amount = $product->get_price();
            $currency = get_woocommerce_currency();

            wp_send_json_success([
                'amount' => intval(round($amount * 100)),
                'currency' => strtolower($currency),
                'label' => $product->get_name(),
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Finalize Order after successful payment
     */
    public function ajax_finalize_order()
    {
        check_ajax_referer('growtype_wc_ajax_nonce', 'nonce');

        try {
            $order_id = absint($_POST['order_id'] ?? 0);
            $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');

            if (!$order_id) {
                throw new \Exception('Invalid order ID');
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            // Optional: verify status with Stripe if intent ID is provided
            if ($payment_intent_id) {
                $gateways = WC()->payment_gateways()->payment_gateways();
                $stripe_gateway = $gateways[Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID] ?? null;
                if ($stripe_gateway) {
                    $stripe = new \Stripe\StripeClient($stripe_gateway->get_secret_key());
                    $pi = $stripe->paymentIntents->retrieve($payment_intent_id);
                    if ($pi->status !== 'succeeded' && $pi->status !== 'processing') {
                    throw new \Exception('Payment not successful');
                }

                error_log(sprintf('Growtype WC Debug - Finalizing Order %d: PI Response - Customer: %s, PM: %s', $order_id, $pi->customer, $pi->payment_method));

                if ($pi->customer) {
                    $order->update_meta_data('stripe_customer_id', $pi->customer);
                }
                
                if ($pi->payment_method) {
                    $order->update_meta_data('stripe_payment_method_id', $pi->payment_method);
                }

                $order->update_meta_data('stripe_transaction_id', $pi->id);

                // Capture wallet type (google_pay, apple_pay etc) from Stripe if available
                $wallet_type = '';
                try {
                    if (isset($pi->payment_method_details->wallet->type)) {
                        $wallet_type = $pi->payment_method_details->wallet->type;
                    } elseif (isset($pi->payment_method)) {
                        // If not expanded, try to get more info from the payment method object or fallback to meta
                        $pm_obj = $stripe->paymentMethods->retrieve($pi->payment_method);
                        if (isset($pm_obj->card->wallet->type)) {
                            $wallet_type = $pm_obj->card->wallet->type;
                        }
                    }
                } catch (\Exception $e) {
                    error_log('Growtype WC: Could not retrieve wallet type: ' . $e->getMessage());
                }

                // If we found a wallet type, update the order title and meta
                if ($wallet_type) {
                    $order->update_meta_data('_stripe_payment_method_type', $wallet_type);
                    $gateways = WC()->payment_gateways()->payment_gateways();
                    $stripe_gateway = $gateways[Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID] ?? null;
                    $base_title = $stripe_gateway ? $stripe_gateway->method_title : 'Stripe';
                    $order->set_payment_method_title($base_title . ' (' . ucfirst(str_replace('_', ' ', $wallet_type)) . ')');
                }

                if ($order->get_customer_id() && $pi->customer) {
                    update_user_meta($order->get_customer_id(), 'stripe_customer_id', $pi->customer);
                }
                $order->save();
            }
        }

            if (!$order->is_paid()) {
                $order->payment_complete();
            }

            wp_send_json_success();

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
