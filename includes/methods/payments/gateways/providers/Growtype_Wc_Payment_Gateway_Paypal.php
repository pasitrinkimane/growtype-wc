<?php

/**
 * Class WC_Gateway_Free
 * No charge payment method
 */
class Growtype_Wc_Payment_Gateway_Paypal extends WC_Payment_Gateway
{
    public $domain;
    const PAYMENT_METHOD_KEY = 'gwc-paypal';
    const PROVIDER_ID = 'growtype_wc_paypal';
    private $client_id;
    private $test_mode;
    private $client_secret;
    private $visible_in_frontend;

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

        $this->test_mode = 'yes' === $this->get_option('test_mode');

        $this->client_id = $this->test_mode ? $this->get_option('client_id_test') : $this->get_option('client_id_live');

        $this->client_secret = $this->test_mode ? $this->get_option('client_secret_test') : $this->get_option('client_secret_live');

        $this->setup_extra_properties();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array ($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array ($this, 'thankyou_page'));
        add_action('woocommerce_add_to_cart', array ($this, 'woocommerce_add_to_cart_extend'), 20, 6);
        add_filter('woocommerce_payment_complete_order_status', array ($this, 'change_payment_complete_order_status'), 10, 3);
        add_filter('template_redirect', array ($this, 'payment_redirect'));
        add_action('growtype_wc_change_subscription_status', array ($this, 'change_subscription_status'), 0, 2);
    }

    protected function setup_properties()
    {
        $this->id = self::PROVIDER_ID;
        $this->icon = apply_filters('growtype_wc_payment_gateway_paypal_icon', 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b5/PayPal.svg/800px-PayPal.svg.png');
        $this->method_title = 'Growtype WC - Paypal';
        $this->method_description = __('Allow to make transactions through paypal.', 'growtype-wc');
        $this->has_fields = true;
        $this->chosen = false;
    }

    protected function setup_extra_properties()
    {
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->visible_in_frontend = $this->get_option('visible_in_frontend');
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
                'title' => __('Title', 'growtype-wc'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'growtype-wc'),
                'default' => __('PayPal', 'growtype-wc'),
                'desc_tip' => true,
            ),
            'description' => array (
                'title' => __('Description', 'growtype-wc'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'growtype-wc'),
                'default' => __('', 'growtype-wc'),
                'desc_tip' => true,
            ),
            'add_to_card_redirect_paypal_checkout' => array (
                'title' => __('Paypal checkout - add to cart', 'growtype-wc'),
                'type' => 'checkbox',
                'label' => __('Redirect to paypal checkout after add to cart', 'growtype-wc'),
                'default' => 'no'
            ),
            'client_id_test' => array (
                'title' => __('Client id - Test', 'growtype-wc'),
                'type' => 'text',
            ),
            'client_secret_test' => array (
                'title' => __('Client secret - Test', 'growtype-wc'),
                'type' => 'text',
            ),
            'client_id_live' => array (
                'title' => __('Client id - Live', 'growtype-wc'),
                'type' => 'text',
            ),
            'client_secret_live' => array (
                'title' => __('Client secret - Live', 'growtype-wc'),
                'type' => 'text',
            ),
        );
    }

    function payment_redirect()
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

        $payment_method = $order->get_payment_method();

        if ($payment_method === self::PROVIDER_ID) {
            $paypal_order_id = sanitize_text_field($_GET['token'] ?? '');
            $paypal_ba_token = sanitize_text_field($_GET['ba_token'] ?? '');

            if (Growtype_Wc_Subscription::is_subscription_order($order_id)) {
                if ($paypal_ba_token !== $order->get_meta('paypal_ba_token')) {
                    return null;
                }
            } else {
                if ($paypal_order_id !== $order->get_meta('paypal_token')) {
                    return null;
                }
            }

            $access_token = $this->get_access_token($this->client_id, $this->client_secret);

            $paypal_order_data = $this->get_order_data($access_token, $paypal_order_id);

            $customer_email = $paypal_order_data['payer']['email_address'] ?? '';

            if (!empty($customer_email)) {
                Growtype_Wc_Payment_Gateway::update_user_email_if_not_exists(get_current_user_id(), $customer_email);
                Growtype_Wc_Payment_Gateway::update_order_email_if_not_exists($order_id, $customer_email);
            }

            if (isset($paypal_order_data['status'])) {
                if (in_array($paypal_order_data['status'], ['APPROVED', 'COMPLETED'])) {
                    if ($paypal_order_data['status'] === 'APPROVED') {
                        $order->add_order_note(__(sprintf('Order id: %s', $paypal_order_id), 'growtype-wc'));

                        if (isset($paypal_order_data['intent']) && $paypal_order_data['intent'] === 'CAPTURE') {
                            foreach ($paypal_order_data['links'] as $link) {
                                if ($link['rel'] === 'capture') {
                                    $this->capture_order($access_token, $paypal_order_id);
                                    break;
                                }
                            }
                        }

                        if (Growtype_Wc_Subscription::is_subscription_order($order_id)) {
                            $paypal_subscription_id = $order->get_meta('paypal_subscription_id');

                            $order->add_order_note(__(sprintf('Subscription id: %s', $paypal_subscription_id), 'growtype-wc'));
                        }
                    }

                    $order->payment_complete();
                } else {
                    error_log(sprintf('Order %s is not approved. Paypal order id: %s. Order data - %s.', $order_id, $paypal_order_id, print_r($paypal_order_data, true)));
                }
            } else {
                error_log(sprintf('Order %s is not payed and status is missing. Paypal order id: %s. Order data - %s.', $order_id, $paypal_order_id, print_r($paypal_order_data, true)));
            }
        }
    }

    /**
     * Process the payment and return the result
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

        $order_status = apply_filters('growtype_wc_process_payment_order_status_gateway_' . $this->id, 'completed', $order_id, $order);

        $order->update_status($order_status);

        $woocommerce->cart->empty_cart();

        return array (
            'result' => 'success',
            'redirect' => Growtype_Wc_Payment_Gateway::success_url($order_id, self::PROVIDER_ID)
        );
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
    public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
    {
        return 'completed';
    }

    public function payment_fields()
    {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description));
        }

        do_action('growtype_wc_payment_gateway_paypal_before_payment_button');

        echo '<button class="btn btn-primary btn-paypal"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/39/PayPal_logo.svg/527px-PayPal_logo.svg.png"/></button>';

        do_action('growtype_wc_payment_gateway_paypal_after_payment_button');
    }

    function woocommerce_add_to_cart_extend($cart_item_key, $wc_product_id, $quantity, $variation_id, $variation_attributes, $cart_item_data)
    {
        do_action('growtype_wc_before_add_to_cart', $cart_item_key, $wc_product_id, $quantity, $variation_id, $variation_attributes, $cart_item_data);

        if ($this->get_option('add_to_card_redirect_paypal_checkout') === 'yes' && isset($_GET['payment_method']) && $_GET['payment_method'] === self::PAYMENT_METHOD_KEY) {
            $wc_product = wc_get_product($wc_product_id);
            $order = wc_create_order();

            $order_id = $order->get_id();

            $order->add_product($wc_product, $quantity);
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
                $access_token = $this->get_access_token($this->client_id, $this->client_secret);

                if (growtype_wc_product_is_subscription($wc_product->get_id())) {
                    $paypal_product = $this->create_product($access_token, $wc_product->get_id());

                    $subscription_plan = $this->create_billing_plan($access_token, $paypal_product, $wc_product_id, $applied_coupons);

                    $subscription_plan_id = $subscription_plan['id'] ?? '';

                    if (empty($subscription_plan_id)) {
                        error_log(sprintf('Growtype Wc - Paypal subscription plan id is empty: %s', print_r([
                            'product_id' => $wc_product->get_id(),
                            'order_id' => $order_id,
                            'subscription_plan' => $subscription_plan,
                        ], true)));
                        throw new Exception(__('Subscription plan creation failed.', 'growtype-wc'));
                    }

                    $paypal_checkout = $this->create_subscription($access_token, $subscription_plan_id, $order_id, $applied_coupons);

                    if (isset($paypal_checkout['id']) && !empty($paypal_checkout['id'])) {
                        $order->update_meta_data('paypal_subscription_id', $paypal_checkout['id']);
                    }
                } else {
                    $paypal_checkout = $this->create_order($access_token, $order_id, $applied_coupons);
                }

                if (isset($paypal_checkout['name']) && $paypal_checkout['name'] === 'INVALID_REQUEST') {
                    error_log(sprintf('Growtype Wc - Paypal invalid request %s', print_r($paypal_checkout, true)));
                }

                if (isset($paypal_checkout['links'])) {
                    foreach ($paypal_checkout['links'] as $link) {
                        $link = (array)$link;

                        if ($link['rel'] === 'approve') {
                            $checkout_url = $link['href'];

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

                            wp_redirect($checkout_url);
                            exit;
                        }
                    }
                } else {
                    error_log(sprintf('Something went wrong for payment provider %s. Order %s', 'paypal', $order_id));
                }
            } catch (Exception $e) {
                error_log(sprintf('growtype_wc_paypal_add_to_cart_error. %s', $e->getMessage()));

                $order->update_status('failed', sprintf(__('Reason %s.', 'growtype-wc'), wc_clean($e->getMessage())));
            }

            wp_redirect($cancel_url);

            exit();
        }
    }

    public function get_access_token_details($client_id, $client_secret)
    {
        $auth = base64_encode($client_id . ':' . $client_secret);

        $token_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v1/oauth2/token"
            : "https://api-m.paypal.com/v1/oauth2/token";

        // Set up the request headers
        $headers = array (
            'Authorization' => 'Basic ' . $auth,
            'Content-Type' => 'application/x-www-form-urlencoded'
        );

        // Request body
        $body = array (
            'grant_type' => 'client_credentials'
        );

        // Send the POST request
        $response = wp_remote_post($token_url, array (
            'headers' => $headers,
            'body' => $body
        ));

        // Get the access token from the response
        if (is_wp_error($response)) {
            return false; // Handle error here
        }

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    public function get_access_token($client_id, $client_secret)
    {
        $access_token_details = $this->get_access_token_details($client_id, $client_secret);

        return $access_token_details['access_token'];
    }

    function create_product($access_token, $wc_product_id)
    {
        $wc_product = wc_get_product($wc_product_id);

        $paypal_product_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v1/catalogs/products"
            : "https://api-m.paypal.com/v1/catalogs/products";

        $headers = array (
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        );

        $wc_product_name = $wc_product->get_name();
        $wc_product_name = sanitize_text_field($wc_product_name);

        $wc_product_description = $wc_product->get_short_description();
        $wc_product_description = sanitize_text_field($wc_product_description);

        $body = json_encode(array (
            "name" => !empty($wc_product_name) ? $wc_product_name : 'Wc product',
            "description" => !empty($wc_product_description) ? $wc_product_description : 'Wc product description',
            "type" => "SERVICE",
            "category" => "SOFTWARE",
        ));

        $response = wp_remote_post($paypal_product_url, array (
            'headers' => $headers,
            'body' => $body,
        ));

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    public function create_billing_plan($access_token, $paypal_product, $wc_product_id, $applied_coupons = null)
    {
        $plan_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v1/billing/plans"
            : "https://api-m.paypal.com/v1/billing/plans";

        $plan_headers = array (
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        );

        $billing_cycles = [];
        $billing_sequence = 1;

        if (growtype_wc_product_is_trial($wc_product_id)) {
            $billing_cycles[] = array (
                "frequency" => array (
                    "interval_unit" => growtype_wc_get_trial_period($wc_product_id),
                    "interval_count" => growtype_wc_get_trial_duration($wc_product_id)
                ),
                "tenure_type" => "TRIAL",
                "sequence" => $billing_sequence,
                "total_cycles" => 1,
                "pricing_scheme" => array (
                    "fixed_price" => array (
                        "value" => growtype_wc_get_trial_price($wc_product_id),
                        "currency_code" => get_woocommerce_currency()
                    )
                )
            );

            $billing_sequence++;
        }

        if (!empty($applied_coupons)) {
            $product = wc_get_product($wc_product_id);
            $sale_price = $product->get_sale_price();

            $billing_cycles[] = array (
                "frequency" => array (
                    "interval_unit" => "MONTH",
                    "interval_count" => 1
                ),
                "tenure_type" => "TRIAL",
                "sequence" => $billing_sequence,
                "total_cycles" => 1,
                "pricing_scheme" => array (
                    "fixed_price" => array (
                        "value" => growtype_wc_price_apply_coupon_discount($wc_product_id, $sale_price, $applied_coupons),
                        "currency_code" => get_woocommerce_currency()
                    )
                )
            );

            $billing_sequence++;
        }

        if (growtype_wc_product_is_subscription($wc_product_id)) {
            $billing_cycles[] = array (
                "frequency" => array (
                    "interval_unit" => growtype_wc_get_subscription_period($wc_product_id),
                    "interval_count" => growtype_wc_get_subscription_duration($wc_product_id)
                ),
                "tenure_type" => "REGULAR",
                "sequence" => $billing_sequence,
                "total_cycles" => 0,
                "pricing_scheme" => array (
                    "fixed_price" => array (
                        "value" => growtype_wc_get_subscription_price($wc_product_id),
                        "currency_code" => get_woocommerce_currency()
                    )
                )
            );
        }

        $plan_details = array (
            "product_id" => $paypal_product['id'],
            "name" => $paypal_product['name'],
            "description" => $paypal_product['description'],
            "status" => "ACTIVE",
            "billing_cycles" => $billing_cycles,
            "payment_preferences" => array (
                "auto_bill_outstanding" => true,
                "setup_fee" => array (
                    "value" => "0",
                    "currency_code" => get_woocommerce_currency()
                ),
                "setup_fee_failure_action" => "CONTINUE",
                "payment_failure_threshold" => 3
            )
        );

        $plan_body = json_encode($plan_details);

        $plan_args = array (
            'headers' => $plan_headers,
            'body' => $plan_body,
        );

        $response = wp_remote_post($plan_url, $plan_args);

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    function create_subscription($access_token, $plan_id, $order_id, $applied_coupons = null)
    {
        $subscription_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v1/billing/subscriptions"
            : "https://api-m.paypal.com/v1/billing/subscriptions";

        // Fetch the WooCommerce order details
        $order = wc_get_order($order_id);
        $customer = $order->get_user();
        $current_user = wp_get_current_user();

        $given_name = $customer ? $customer->get_first_name() : $order->get_billing_first_name();
        $given_name = !empty($given_name) ? $given_name : $order->get_shipping_first_name();
        $given_name = empty($given_name) && !empty($current_user) ? $current_user->first_name : $given_name;

        $surname = $customer ? $customer->get_last_name() : $order->get_billing_last_name();
        $surname = !empty($surname) ? $surname : $order->get_shipping_last_name();
        $surname = empty($surname) && !empty($current_user) ? $current_user->last_name : $surname;

        $email = $customer ? $customer->get_email() : $order->get_billing_email();
        $email = empty($email) && !empty($current_user) ? $current_user->user_email : $email;

        $requires_shipping = false;
        foreach ($order->get_items() as $item_id => $item) {
            $wc_product = $item->get_product();
            if ($wc_product->needs_shipping()) {
                $requires_shipping = true;
                break;
            }
        }

        // Set shipping preference based on product needs
        $shipping_preference = $requires_shipping ? "SET_PROVIDED_ADDRESS" : "NO_SHIPPING";

        // Include shipping address if product requires shipping
        $shipping_details = [];
        if ($requires_shipping) {
            $shipping_details = array (
                "name" => array (
                    "full_name" => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()
                ),
                "address" => array (
                    "address_line_1" => $order->get_shipping_address_1(),
                    "address_line_2" => $order->get_shipping_address_2(),
                    "admin_area_2" => $order->get_shipping_city(),
                    "admin_area_1" => $order->get_shipping_state(),
                    "postal_code" => $order->get_shipping_postcode(),
                    "country_code" => $order->get_shipping_country()
                )
            );
        }

        $subscriber_data = array (
            "name" => array (
                "given_name" => $given_name,   // WooCommerce customer first name
                "surname" => $surname          // WooCommerce customer last name
            )
        );

        if (!empty($email)) {
            $subscriber_data['email_address'] = $email;
        }

        // Add shipping address only if shipping is required
        if ($requires_shipping && !empty($shipping_details)) {
            $subscriber_data['shipping_address'] = $shipping_details;
        }

        $cancel_url = Growtype_Wc_Payment_Gateway::cancel_url($order_id, false, $applied_coupons);

        $subscription_data = array (
            "plan_id" => $plan_id,
            "subscriber" => $subscriber_data,
            "application_context" => array (
                "brand_name" => get_bloginfo('name'),  // Dynamic brand name from WordPress
                "locale" => "en-US",                   // Adjust locale if needed
                "shipping_preference" => $shipping_preference, // Based on product type
                "user_action" => "SUBSCRIBE_NOW",
                "return_url" => Growtype_Wc_Payment_Gateway::success_url($order_id),  // WooCommerce return URL
                "cancel_url" => $cancel_url
            ),
            'description' => 'Subscription plan',
            'invoice_id' => $order_id,
        );

        $subscription_body = json_encode($subscription_data);

        // Set headers and body for the subscription API call
        $subscription_headers = array (
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        );

        $subscription_args = array (
            'headers' => $subscription_headers,
            'body' => $subscription_body,
        );

        // Make the API request
        $response = wp_remote_post($subscription_url, $subscription_args);

        // Retrieve and decode the response
        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    public function get_order_data($access_token, $paypal_order_id)
    {
        $orders_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v2/checkout/orders/{$paypal_order_id}"
            : "https://api-m.paypal.com/v2/checkout/orders/{$paypal_order_id}";

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $orders_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$access_token}",
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);

        curl_close($ch);

        $order_data = json_decode($response, true);

        return $order_data;
    }

    public function create_order($access_token, $wc_order_id, $applied_coupons = null)
    {
        $wc_order = wc_get_order($wc_order_id);

        $create_order_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v2/checkout/orders"
            : "https://api-m.paypal.com/v2/checkout/orders";

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
            'PayPal-Request-Id' => uniqid('ppcp-', true),
        ];

        // Build the purchase units with amount and taxes/shipping as needed
        $items = [
            [
                "amount" => [
                    "currency_code" => get_woocommerce_currency(),
                    "value" => $wc_order->get_total(),
                ],
            ],
        ];

        $cancel_url = Growtype_Wc_Payment_Gateway::cancel_url($wc_order_id, false, $applied_coupons);

        // Order body
        $order_body = [
            "intent" => "CAPTURE",  // Immediate payment capture
            "processing_instruction" => "ORDER_COMPLETE_ON_PAYMENT_APPROVAL",  // Complete the order when payment is approved
            "purchase_units" => $items,
            "application_context" => [
                "return_url" => Growtype_Wc_Payment_Gateway::success_url($wc_order_id),
                "cancel_url" => $cancel_url
            ],
        ];

        // Make the request
        $response = wp_remote_post($create_order_url, [
            'headers' => $headers,
            'body' => wp_json_encode($order_body),
        ]);

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    function change_subscription_status($subscription_id, $status)
    {
        $order_id = get_post_meta($subscription_id, '_order_id', true);

        if (!empty($order_id)) {
            $order = wc_get_order($order_id);

            if ($order->get_payment_method() === self::PROVIDER_ID) {
                $access_token = $this->get_access_token($this->client_id, $this->client_secret);

                $paypal_subscription_id = $order->get_meta('paypal_subscription_id');

                if (!empty($paypal_subscription_id)) {
                    if ($status === 'cancelled') {
                        $response = $this->suspend_paypal_subscription($access_token, $paypal_subscription_id);
                    } elseif ($status === 'active') {
                        $response = $this->resume_paypal_subscription($access_token, $paypal_subscription_id);
                    }
                }
            }
        }
    }

    public function resume_paypal_subscription($access_token, $subscription_id)
    {
        $resume_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/{$subscription_id}/activate"
            : "https://api-m.paypal.com/v1/billing/subscriptions/{$subscription_id}/activate";

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ];

        // Provide a reason for resuming the subscription
        $resume_body = json_encode([
            "reason" => "Resuming subscription as requested by customer"
        ]);

        $response = wp_remote_post($resume_url, [
            'headers' => $headers,
            'body' => $resume_body,
        ]);

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    function suspend_paypal_subscription($access_token, $subscription_id)
    {
        // Adjust the URL for the suspend action
        $suspend_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/{$subscription_id}/suspend"
            : "https://api-m.paypal.com/v1/billing/subscriptions/{$subscription_id}/suspend";

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ];

        // Provide a reason for suspension
        $suspend_body = json_encode([
            "reason" => "Customer requested suspension"
        ]);

        // Make the POST request to suspend the subscription
        $response = wp_remote_post($suspend_url, [
            'headers' => $headers,
            'body' => $suspend_body,
        ]);

        // Retrieve and return the response
        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    function cancel_paypal_subscription($access_token, $subscription_id)
    {
        $cancel_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/{$subscription_id}/cancel"
            : "https://api-m.paypal.com/v1/billing/subscriptions/{$subscription_id}/cancel";

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ];

        // Provide a reason for cancellation
        $cancel_body = json_encode([
            "reason" => "Customer requested cancellation"
        ]);

        $response = wp_remote_post($cancel_url, [
            'headers' => $headers,
            'body' => $cancel_body,
        ]);

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    public function get_subscription($access_token, $subscription_id)
    {
        // Set the correct URL for sandbox or production
        $get_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/{$subscription_id}"
            : "https://api-m.paypal.com/v1/billing/subscriptions/{$subscription_id}";

        // Set the headers including the Authorization Bearer token
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ];

        // Make a GET request to retrieve the billing agreement
        $response = wp_remote_get($get_url, [
            'headers' => $headers,
        ]);

        // Check for errors
        if (is_wp_error($response)) {
            return 'Error: ' . $response->get_error_message();
        }

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    public function cancel_billing_agreement($access_token, $agreement_id)
    {
        $cancel_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v1/payments/billing-agreements/{$agreement_id}/cancel"
            : "https://api-m.paypal.com/v1/payments/billing-agreements/{$agreement_id}/cancel";

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ];

        $body = json_encode([
            "note" => "Canceling the agreement as requested."
        ]);

        $response = wp_remote_post($cancel_url, [
            'headers' => $headers,
            'body' => $body,
        ]);

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    public function activate_billing_agreement($access_token, $agreement_id)
    {
        $activate_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v1/payments/billing-agreements/{$agreement_id}/agreements"
            : "https://api-m.paypal.com/v1/payments/billing-agreements/{$agreement_id}/agreements";

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post($activate_url, [
            'headers' => $headers,
            'body' => json_encode((object)[]),
        ]);

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    public function capture_order($access_token, $order_id)
    {
        $capture_url = $this->test_mode
            ? "https://api-m.sandbox.paypal.com/v2/checkout/orders/{$order_id}/capture"
            : "https://api-m.paypal.com/v2/checkout/orders/{$order_id}/capture";

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post($capture_url, [
            'headers' => $headers,
            'body' => json_encode((object)[]),
        ]);

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);

        return (array)$data;
    }

    public function charge_intent($parent_order_id, $product_id, $description)
    {
        // 1) Load original
        $parent = wc_get_order($parent_order_id);
        if (!$parent) {
            throw new \Exception("Invalid parent order ID: {$parent_order_id}");
        }

        // 2) Create a fresh WC order for this upsell
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
        $upsell->set_payment_method($this->id);
        $upsell->set_currency($parent->get_currency());
        $upsell->calculate_totals();

        // 3) Build PayPal “create order” payload and fire it
        $access_token = $this->get_access_token($this->client_id, $this->client_secret);
        $checkout = $this->create_order($access_token, $upsell->get_id());
        if (empty($checkout['links']) || !is_array($checkout['links'])) {
            throw new \Exception('Unexpected PayPal response creating upsell order.');
        }

        $upsell->update_meta_data('paypal_token', sanitize_text_field($checkout['id']));

        // 4) Find the “approve” link, store it and redirect
        foreach ($checkout['links'] as $link) {
            if (!empty($link->rel) && $link->rel === 'approve') {
                $approve = $link->href;

                // store for later callbacks
                $upsell->update_meta_data('payment_provider_checkout_url', $approve);

                $upsell->save();

                wp_redirect($approve);
                exit;
            }
        }

        throw new \Exception('Could not find PayPal approval URL in response.');
    }
}
