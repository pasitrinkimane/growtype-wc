<?php

/**
 * Class WC_Gateway_Free
 * No charge payment method
 */
class Growtype_Wc_Payment_Gateway_Stripe extends WC_Payment_Gateway
{
    const PAYMENT_METHOD_KEY = 'gwc-stripe';
    const PROVIDER_ID = 'growtype_wc_stripe';
    private $visible_in_frontend;
    private $test_mode;
    private $secret_key;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();

        $this->supports = array (
            'products',
            'subscriptions',
            'tokenization',
            'refunds',
            'add_order_meta'
        );

        $this->setup_extra_properties();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array ($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array ($this, 'thankyou_page'));
        add_filter('woocommerce_payment_complete_order_status', array ($this, 'change_payment_complete_order_status'), 10, 3);
        add_filter('template_redirect', array ($this, 'payment_redirect'));
        add_action('woocommerce_add_to_cart', array ($this, 'woocommerce_add_to_cart_extend'), 20, 6);
    }

    protected function setup_properties()
    {
        $this->id = self::PROVIDER_ID;
        $this->icon = apply_filters('growtype_wc_payment_gateway_stripe_icon', 'https://upload.wikimedia.org/wikipedia/commons/b/ba/Stripe_Logo%2C_revised_2016.svg');
        $this->method_title = 'Growtype WC - Stripe';
        $this->method_description = __('Allows subscriptions and payments through Stripe.', 'growtype-wc');
        $this->has_fields = true;
//        $this->chosen = false;
    }

    protected function setup_extra_properties()
    {
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->visible_in_frontend = $this->get_option('visible_in_frontend');

        $this->test_mode = 'yes' === $this->get_option('test_mode');
        $this->secret_key = $this->test_mode ? $this->get_option('secret_key_test') : $this->get_option('secret_key_live');
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array (
            'enabled' => array (
                'title' => __('Enable/Disable', 'growtype-wc'),
                'type' => 'checkbox',
                'label' => __('Method is enabled', 'growtype-wc'),
                'default' => 'no'
            ),
            'test_mode' => array (
                'title' => __('Test mode', 'growtype-wc'),
                'type' => 'checkbox',
                'label' => __('Testing mode is enabled', 'growtype-wc'),
                'description' => 'Test payments will be charged',
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'title' => array (
                'title' => __('Method title', 'growtype-wc'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'growtype-wc'),
                'default' => __('Stripe', 'growtype-wc'),
                'desc_tip' => true,
            ),
            'description' => array (
                'title' => __('Description', 'growtype-wc'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'growtype-wc'),
                'default' => __('', 'growtype-wc'),
                'desc_tip' => true,
            ),
            'add_to_card_redirect_stripe_checkout' => array (
                'title' => __('Stripe checkout - add to cart', 'growtype-wc'),
                'type' => 'checkbox',
                'label' => __('Redirect to stripe checkout after add to cart', 'growtype-wc'),
                'default' => 'no'
            ),
            'secret_key_test' => array (
                'title' => __('Secret key - Test', 'growtype-wc'),
                'type' => 'text',
            ),
            'secret_key_live' => array (
                'title' => __('Secret key - Live', 'growtype-wc'),
                'type' => 'text',
            )
        );
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Invalid order.', 'growtype-wc'), 'error');
            return array ('result' => 'failure');
        }

        try {
            wc_reduce_stock_levels($order_id);

            $order->payment_complete();
            $order->update_status('completed');
            WC()->cart->empty_cart();

            return array (
                'result' => 'success',
                'redirect' => Growtype_Wc_Payment_Gateway::success_url($order_id, self::PROVIDER_ID),
            );
        } catch (Exception $e) {
            wc_add_notice(__('Payment failed. Please try again.', 'growtype-wc'), 'error');
            error_log('Stripe Payment Error: ' . $e->getMessage());
            return array ('result' => 'failure');
        }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page()
    {
    }

    /**
     * Change payment complete order status to completed for COD orders.
     *
     * @param string $status Current order status.
     * @param int $order_id Order ID.
     * @param WC_Order|false $order Order object.
     * @return string
     * @since  3.1.0
     */
    public function change_payment_complete_order_status($status, $order_id, $order)
    {
        if ($order && $order->get_payment_method() === $this->id) {
            return 'completed';
        }

        return $status;
    }

    public function payment_fields()
    {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description));
        }

        $cc_form = new WC_Payment_Gateway_CC();
        $cc_form->id = $this->id;
        $cc_form->supports = $this->supports;
        $cc_form->form();
    }

    public function subscription_details($stripe_subscription_id, $existing_subscription_id)
    {
        try {
            $stripe = new \Stripe\StripeClient($this->secret_key);
            $subscription = $stripe->subscriptions->retrieve($stripe_subscription_id);

            if (!empty($subscription)) {
                $status = $subscription->status;
                $canceled_at = $subscription->canceled_at;
                $canceled_at = !empty($canceled_at) ? date(get_option('date_format') . ' ' . get_option('time_format'), $canceled_at) : null;
                $customer_id = $subscription->customer;
                $current_billing_period_end = $subscription->current_period_end;
                $renewal_date = !empty($current_billing_period_end) ? date(get_option('date_format') . ' ' . get_option('time_format'), $current_billing_period_end) : null;
                $return_url = Growtype_Wc_Subscription::manage_url($existing_subscription_id) . '&status=updated';

                $session = $stripe->billingPortal->sessions->create([
                    'customer' => $customer_id,
                    'return_url' => $return_url,
                ]);

                $billing_portal_url = !empty($session) ? $session->url : null;

                return [
                    'status' => $status,
                    'canceled_at' => $canceled_at,
                    'renewal_date' => $renewal_date,
                    'billing_portal_url' => $billing_portal_url,
                ];
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log(sprintf('growtype_wc_stripe_billing_portal_error %s', $e->getMessage()));
        } catch (Exception $e) {
            error_log(sprintf('growtype_wc_stripe_billing_portal_error %s', $e->getMessage()));
        }

        return [];
    }

    public function payment_redirect()
    {
        // 1) Only on our thank-you page
        if (!growtype_wc_is_thankyou_page()) {
            return;
        }

        global $wp;
        $order_id = absint($wp->query_vars['order-received'] ?? 0);
        $order = wc_get_order($order_id);

        // 2) Bail if invalid order, already completed, or not our gateway
        if (!$order || $order->get_status() === 'completed') {
            return;
        }

        if ($order->get_payment_method() !== self::PROVIDER_ID) {
            return;
        }

        // 3) Validate session ID and guard “run once”
        $session_id = sanitize_text_field($_GET['checkout_session_id'] ?? '');
        $saved_session_id = $order->get_meta('stripe_session_id');
        if ($session_id !== $saved_session_id) {
            return;
        }
        if ($order->get_meta('stripe_customer_id')) {
            return;
        }

        $stripe = new \Stripe\StripeClient($this->secret_key);

        // 4) Fetch the Checkout Session
        try {
            $session = $stripe->checkout->sessions->retrieve($session_id);
        } catch (\Exception $e) {
            error_log('growtype_wc_stripe_order_received_error: ' . $e->getMessage());
            return;
        }

        // 5) Sync email back to WP
        if ($email = $session->customer_details->email ?? '') {
            Growtype_Wc_Payment_Gateway::update_user_email_if_not_exists(get_current_user_id(), $email);
            Growtype_Wc_Payment_Gateway::update_order_email_if_not_exists($order_id, $email);
        }

        // 6a) Subscription flow
        if ($session->mode === 'subscription' && !empty($session->subscription)) {
            if ($session->payment_status === 'paid') {
                try {
                    $sub = $stripe->subscriptions->retrieve(
                        $session->subscription,
                        ['expand' => ['latest_invoice.payment_intent']]
                    );

                    $customer_id = $sub->customer;
                    $subscription_id = $sub->id;
                    $payment_method_id = $sub->latest_invoice->payment_intent->payment_method;
                    $invoice_id = $sub->latest_invoice->id;

                    $order->update_meta_data('stripe_subscription_id', $subscription_id);
                    $order->update_meta_data('stripe_payment_method_id', $payment_method_id);
                    $order->update_meta_data('stripe_invoice_id', $invoice_id);

                    $order->add_order_note(sprintf(
                        __('Subscription created: %s', 'growtype-wc'),
                        $session->subscription
                    ));
                } catch (\Exception $e) {
                    error_log('growtype_wc_stripe_subscription_error: ' . $e->getMessage());
                    return;
                }
            } else {
                error_log('growtype_wc_stripe_order_received_error: Subscription not paid.');
                return;
            }
        } else {
            try {
                $pi = $stripe->paymentIntents->retrieve($session->payment_intent);
            } catch (\Exception $e) {
                error_log('growtype_wc_stripe_pi_error: ' . $e->getMessage());
                return;
            }

            if ($pi->status === 'succeeded') {
                // Create customer
                try {
                    $cust = $stripe->customers->create([
                        'email' => $session->customer_email,
                        'metadata' => ['wp_user_id' => get_current_user_id()],
                    ]);
                    $customer_id = $cust->id;

                    // Attach & default payment method
                    if (!empty($pi->payment_method)) {
                        $stripe->paymentMethods->attach($pi->payment_method, ['customer' => $customer_id]);
                        $stripe->customers->update($customer_id, [
                            'invoice_settings' => ['default_payment_method' => $pi->payment_method],
                        ]);
                        $order->update_meta_data('stripe_payment_method_id', $pi->payment_method);
                        $order->add_order_note(sprintf(
                            __('Payment method attached: %s', 'growtype-wc'),
                            $pi->payment_method
                        ));
                    }

                    $order->update_meta_data('stripe_transaction_id', $pi->id);

                    $order->add_order_note(sprintf(
                        __('PaymentIntent succeeded: %s', 'growtype-wc'),
                        $pi->id
                    ));
                } catch (\Exception $e) {
                    error_log('growtype_wc_stripe_customer_error: ' . $e->getMessage());
                    return;
                }
            } else {
                error_log('growtype_wc_stripe_order_received_error: PI status ' . $pi->status);
                return;
            }
        }

        update_user_meta(get_current_user_id(), 'stripe_customer_id', $customer_id);

        $order->update_meta_data('stripe_customer_id', $customer_id);
        $order->save();
        $order->payment_complete();
    }

    public function webhooks()
    {
        $order_id = $_GET['id'] ?? '';

        if (!empty($order_id)) {
            $order = wc_get_order($order_id);

            if (!empty($order)) {
                error_log(sprintf('growtype_wc_stripe_webhook %s', print_r($order, true)));

                $order->payment_complete();

                update_option('webhook_debug', $_GET);
            }
        }
    }

    function woocommerce_add_to_cart_extend($cart_item_key, $product_id, $quantity, $variation_id, $variation_attributes, $cart_item_data)
    {
        static $already_running = false;

        if ($already_running) {
            return; // Exit if already running
        }

        $already_running = true;

        try {
            do_action('growtype_wc_before_add_to_cart', $cart_item_key, $product_id, $quantity, $variation_id, $variation_attributes, $cart_item_data);

            if ($this->get_option('add_to_card_redirect_stripe_checkout') === 'yes' && isset($_GET['payment_method']) && $_GET['payment_method'] === self::PAYMENT_METHOD_KEY) {
                $product = wc_get_product($product_id);
                $order = wc_create_order();

                $order_id = $order->get_id();

                $order->add_product($product, 1);
                $order->set_payment_method($this->id);

                $applied_coupons = WC()->cart->get_applied_coupons();

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

                WC()->cart->empty_cart();

                try {
                    $current_user = wp_get_current_user();

                    $stripe = new \Stripe\StripeClient($this->secret_key);

                    $product_name = $product->get_name();
                    $product_name = sanitize_text_field($product_name);

                    if (growtype_wc_product_is_subscription($product->get_id())) {
                        try {
                            $stripe_product = $stripe->products->create([
                                'name' => $product_name,
                            ]);

                            $stripe_price_details = [
                                'product' => $stripe_product->id,
                                'unit_amount' => growtype_wc_get_subscription_price($product_id) * 100, // Amount in cents
                                'currency' => get_woocommerce_currency(),
                                'recurring' => [
                                    'interval' => growtype_wc_get_subscription_period($product_id),
                                    'interval_count' => growtype_wc_get_subscription_duration($product_id)
                                ],
                            ];

                            $stripe_price = $stripe->prices->create($stripe_price_details);

                            $checkout_session_data = [
                                'line_items' => [
                                    [
                                        'price' => $stripe_price->id,
                                        'quantity' => $quantity
                                    ]
                                ],
                                'mode' => 'subscription',
                                'success_url' => Growtype_Wc_Payment_Gateway::success_url($order_id, self::PROVIDER_ID),
                                'cancel_url' => $cancel_url,
                                'subscription_data' => [
                                    'description' => sprintf('Order #%s - %s', $order_id, $product_name),
                                    'metadata' => [
                                        'order_id' => $order_id,
                                        'product_id' => $product_id,
                                        'user_id' => $current_user->ID,
                                        'site' => home_url(),
                                    ],
                                ],
                            ];

                            if (growtype_wc_product_is_trial($product_id)) {
                                $checkout_session_data['subscription_data']['trial_period_days'] = growtype_wc_get_trial_duration($product_id);
                            }

                            if (!empty($current_user->user_email)) {
                                $checkout_session_data['customer_email'] = $current_user->user_email;
                                $checkout_session_data['subscription_data']['metadata']['user_email'] = $current_user->user_email;
                            }

                            /**
                             * Apply coupon
                             */
                            if (!empty($applied_coupons)) {
                                $applied_coupon_code = reset($applied_coupons);
                                $wc_coupon = new WC_Coupon($applied_coupon_code);

                                if ($wc_coupon->is_valid()) {
                                    $discount_type = $wc_coupon->get_discount_type();
                                    $discount_amount = (float)$wc_coupon->get_amount();

                                    try {
                                        if ($discount_type === 'percent') {
                                            $stripe_coupon = $stripe->coupons->create([
                                                'percent_off' => $discount_amount,
                                                'duration' => 'once',
                                            ]);
                                        } else {
                                            $stripe_coupon = $stripe->coupons->create([
                                                'amount_off' => $discount_amount * 100, // cents
                                                'currency' => get_woocommerce_currency(),
                                                'duration' => 'once',
                                            ]);
                                        }

                                        // Attach the Stripe coupon to the subscription
                                        $checkout_session_data['discounts'] = [
                                            ['coupon' => $stripe_coupon->id],
                                        ];

                                    } catch (Exception $e) {
                                        error_log('Stripe coupon creation failed: ' . $e->getMessage());
                                    }
                                }
                            }

                            $checkout_session = $stripe->checkout->sessions->create($checkout_session_data);
                        } catch (Exception $e) {
                            error_log(sprintf('growtype_wc_stripe_add_to_cart_error. %s', $e->getMessage()));
                            wp_redirect($cancel_url);
                        }
                    } else {
                        $checkout_session_data = [
                            'line_items' => [
                                [
                                    'price_data' => [
                                        'product_data' => [
                                            'name' => $product_name,
                                            'metadata' => [
                                                'pro_id' => $product->get_id(),
                                            ],
                                        ],
                                        'unit_amount' => $order->get_total() * 100,
                                        'currency' => get_woocommerce_currency(),
                                    ],
                                    'quantity' => $quantity
                                ]
                            ],
                            'mode' => 'payment',
                            'success_url' => Growtype_Wc_Payment_Gateway::success_url($order_id, self::PROVIDER_ID),
                            'cancel_url' => $cancel_url,
                            'payment_intent_data' => [
                                'description' => sprintf('Order #%s - %s', $order_id, $product_name),
                                "statement_descriptor" => sprintf('%s - %s', get_bloginfo('name'), $order_id),
                                'setup_future_usage' => 'off_session',
                                'metadata' => [
                                    'order_id' => $order_id,
                                    'product_id' => $product_id,
                                    'user_id' => $current_user->ID,
                                    'site' => home_url(),
                                ],
                            ],
                        ];

                        if (!empty($current_user->user_email)) {
                            $checkout_session_data['customer_email'] = $current_user->user_email;
                            $checkout_session_data['metadata']['user_email'] = $current_user->user_email;
                        }

                        $checkout_session = $stripe->checkout->sessions->create($checkout_session_data);
                    }
                } catch (Exception $e) {
                    error_log(sprintf('growtype_wc_stripe_add_to_cart_error. %s', $e->getMessage()));

                    $order->update_status('failed', sprintf(__('Reason %s.', 'growtype-wc'), wc_clean($e->getMessage())));
                }

                if (isset($checkout_session) && $checkout_session) {
                    $order->update_meta_data('payment_provider_checkout_url', $checkout_session->url);
                    $order->update_meta_data('stripe_session_id', $checkout_session->id);

                    do_action('woocommerce_checkout_create_order', $order, $cart_item_data);

                    $order->save();

                    wp_redirect($checkout_session->url);
                } else {
                    wp_redirect($cancel_url);
                }

                exit();
            }
        } catch (\Exception $e) {
            error_log('Stripe add_to_cart error: ' . $e->getMessage());
        }

        $already_running = false;
    }

    public function charge_intent($parent_order_id, $product_id, $description)
    {
        // 1) Load the original order
        $parent = wc_get_order($parent_order_id);
        if (!$parent) {
            throw new \Exception("Invalid parent order ID: {$parent_order_id}");
        }

        // 2) Create a new WC order for the upsell
        $upsell_order = wc_create_order();
        // Set parent reference
        $upsell_order->update_meta_data('parent_order_id', $parent_order_id);
        // Assign same customer
        if ($parent->get_customer_id()) {
            $upsell_order->set_customer_id($parent->get_customer_id());
        }

        $product = wc_get_product($product_id);

        $upsell_order->add_product($product, 1);

        $upsell_order->set_payment_method($this->id);

        // Calculate totals
        $upsell_order->set_currency($parent->get_currency());

        $upsell_order->calculate_totals();

        $amount = (float)$product->get_price();

        // 4) Prepare Stripe off-session charge
        $customer_id = $parent->get_meta('stripe_customer_id');
        $payment_method = $parent->get_meta('stripe_payment_method_id');

        if (!$customer_id) {
            throw new \Exception('Missing Stripe customer.');
        }

        if (!$payment_method) {
            throw new \Exception('Missing Stripe payment method.');
        }

        $stripe = new \Stripe\StripeClient($this->secret_key);

        try {
            $pi = $stripe->paymentIntents->create([
                'amount' => intval(round($amount * 100)),
                'currency' => strtolower($upsell_order->get_currency()),
                'customer' => $customer_id,
                'payment_method' => $payment_method,
                'off_session' => true,
                'confirm' => true,
                'description' => $description,
                'metadata' => [
                    'parent_order_id' => $parent_order_id,
                    'upsell_order_id' => $upsell_order->get_id(),
                    'product_id' => $product_id,
                ],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // record failure on upsell order
            $upsell_order->add_order_note(sprintf(
                __('Upsell charge failed: %s', 'growtype-wc'),
                $e->getMessage()
            ));
            throw new \Exception('Upsell charge failed: ' . $e->getMessage(), $e->getCode(), $e);
        }

        // 5) Mark the new order as paid
        $upsell_order->update_meta_data('stripe_transaction_id', $pi->id);

        $upsell_order->add_order_note(sprintf(
            __('Upsell PaymentIntent succeeded: %s', 'growtype-wc'),
            $pi->id
        ));

        $upsell_order->payment_complete();

        // 6) Save everything
        $upsell_order->save();

        return $pi;
    }
}
