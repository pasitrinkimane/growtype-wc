<?php

require GROWTYPE_WC_PATH . '/vendor/autoload.php';

/**
 * Class WC_Gateway_Free
 * No charge payment method
 */
class Growtype_WC_Gateway_Stripe extends WC_Payment_Gateway
{
    public $domain;

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
            'subscription',
            'sandbox',
            'tokens',
            'addons'
        );

        $this->setup_extra_properties();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array ($this, 'process_admin_options'));

        add_action('woocommerce_thankyou_' . $this->id, array ($this, 'thankyou_page'));

        add_filter('woocommerce_payment_complete_order_status', array ($this, 'change_payment_complete_order_status'), 10, 3);

        add_filter('template_redirect', array ($this, 'payment_redirect'));

        add_action('woocommerce_add_to_cart', array ($this, 'woocommerce_add_to_cart_extend'), 20, 6);

        add_action('init', array ($this, 'webhooks'));
    }

    function payment_redirect($order_id)
    {
        if (growtype_wc_is_thankyou_page()) {
            global $wp;
            $order_id = apply_filters('woocommerce_thankyou_order_id', absint($wp->query_vars['order-received']));
            $order = wc_get_order($order_id);

            if (empty($order)) {
                wp_redirect(home_url());
                exit();
            }

            $session_id = $_GET['checkout_session_id'] ?? '';
            $stripe_session_id = $order->get_meta('stripe_session_id');

            if (empty($stripe_session_id) || $stripe_session_id !== $session_id) {
                return $order_id;
            }

            $stripe = new \Stripe\StripeClient($this->secret_key);

            try {
                $checkout_session = $stripe->checkout->sessions->retrieve($stripe_session_id);
            } catch (Exception $e) {
                error_log(sprintf('growtype_wc_stripe_order_received_error %s', print_r($e->getMessage(), true)));
            }

            if (empty($api_error) && isset($checkout_session) && !empty($checkout_session)) {
                if ($checkout_session->mode === 'subscription') {
                    if ($checkout_session->payment_status === 'paid') {
                        if (empty($order->get_meta('stripe_subscription_id'))) {
                            $order->update_meta_data('stripe_subscription_id', $checkout_session->subscription);
                            $order->add_order_note(__(sprintf('Subscription id: %s', $checkout_session->subscription), 'growtype-wc'));
                            $order->payment_complete();
                        }
                    } else {
                        error_log('growtype_wc_stripe_order_received_error. Subscription has failed!');
                    }
                } else {
                    try {
                        $paymentIntent = $stripe->paymentIntents->retrieve($checkout_session->payment_intent);
                    } catch (\Stripe\Exception\ApiErrorException $e) {
                        error_log('growtype_wc_stripe_order_received_error_2', print_r($e->getMessage(), true));
                    }

                    if (empty($api_error) && $paymentIntent) {
                        if (!empty($paymentIntent) && $paymentIntent->status == 'succeeded') {
                            $transaction_id = $paymentIntent->id;
                            if (empty($order->get_meta('stripe_transaction_id'))) {
                                $order->update_meta_data('stripe_transaction_id', $transaction_id);
                                $order->add_order_note(__(sprintf('Transaction id: %s', $transaction_id), 'growtype-wc'));
                                $order->payment_complete();
                            }
                        } else {
                            error_log('growtype_wc_stripe_order_received_error. Transaction has been failed!');
                        }
                    } else {
                        error_log('growtype_wc_stripe_order_received_error. Unable to fetch the transaction details!');
                    }
                }
            }
        }
    }

    public function webhooks()
    {
//        $order = wc_get_order($_GET['id']);
//
//        error_log('growtype_wc_stripe_webhook', print_r($order, true));

//        $order->payment_complete();
//        $order->reduce_order_stock();
//
//        update_option('webhook_debug', $_GET);
    }

    function woocommerce_add_to_cart_extend($cart_item_key, $product_id, $quantity, $variation_id, $variation_attributes, $cart_item_data)
    {
        if ($this->get_option('add_to_card_redirect_stripe_checkout') === 'yes') {
            $product = wc_get_product($product_id);
            $order = wc_create_order();
            $order->add_product(wc_get_product($product_id), 1);
            $order->set_payment_method($this->id);
            $order->calculate_totals();

            if (is_user_logged_in()) {
                $order->set_customer_id(get_current_user_id());
            }

            WC()->cart->empty_cart();

            $stripe_success_url = add_query_arg(array (
                'key' => $order->get_order_key(),
                'checkout_session_id' => '{CHECKOUT_SESSION_ID}',
            ), wc_get_endpoint_url('order-received', $order->get_id(), wc_get_page_permalink('checkout')));

            $stripe_cancel_url = add_query_arg(array (
                'key' => $order->get_order_key(),
                'checkout_session_id' => '',
            ), wc_get_endpoint_url('order-received', $order->get_id(), wc_get_page_permalink('checkout')));

            try {
                $current_user = wp_get_current_user();

                $stripe = new \Stripe\StripeClient($this->secret_key);

                if (growtype_wc_product_is_subscription($product->get_id())) {
                    $stripe_product = $stripe->products->create([
                        'name' => $product->get_name(),
                    ]);

                    $stripe_price = $stripe->prices->create([
                        'product' => $stripe_product->id,
                        'unit_amount' => $product->get_price() * 100, // Amount in cents
                        'currency' => get_woocommerce_currency(),
                        'recurring' => [
                            'interval' => growtype_wc_get_subcription_period($product_id),
                            'interval_count' => growtype_wc_get_subcription_duration($product_id)
                        ],
                    ]);

                    $checkout_session = $stripe->checkout->sessions->create([
//                        'payment_method_types' => ['card'],
                        'line_items' => [
                            [
                                'price' => $stripe_price->id,
                                'quantity' => $quantity
                            ]
                        ],
                        'mode' => 'subscription',
                        'success_url' => $stripe_success_url,
                        'cancel_url' => $stripe_cancel_url,
                        'customer_email' => $current_user->user_email,
                    ]);
                } else {
                    $checkout_session = $stripe->checkout->sessions->create([
                        'line_items' => [
                            [
                                'price_data' => [
                                    'product_data' => [
                                        'name' => $product->get_name(),
                                        'metadata' => [
                                            'pro_id' => $product->get_id(),
                                        ],
                                    ],
                                    'unit_amount' => $product->get_price() * 100,
                                    'currency' => get_woocommerce_currency(),
                                ],
                                'quantity' => $quantity
                            ]
                        ],
                        'mode' => 'payment',
                        'success_url' => $stripe_success_url,
                        'cancel_url' => $stripe_cancel_url,
                        'customer_email' => $current_user->user_email,
                        "payment_intent_data" => [
                            "statement_descriptor" => sprintf('%s - %s', get_bloginfo('name'), $order->get_id())
                        ],
                    ]);
                }
            } catch (Exception $e) {
                error_log(sprintf('growtype_wc_stripe_add_to_cart_error. %s', $e->getMessage()));

                $order->update_status('failed', sprintf(__('Reason %s.', 'growtype-wc'), wc_clean($e->getMessage())));
            }

            if (empty($api_error) && isset($checkout_session) && $checkout_session) {
                $order->update_meta_data('stripe_checkout_url', $checkout_session->url);
                $order->update_meta_data('stripe_session_id', $checkout_session->id);
                $order->save();

                wp_redirect($checkout_session->url);
            } else {
                wp_redirect($stripe_cancel_url);
            }

            exit();
        }
    }

    protected function setup_properties()
    {
        $this->id = 'growtype_wc_stripe';
        $this->icon = apply_filters('growtype_wc_gateway_stripe_icon', 'https://upload.wikimedia.org/wikipedia/commons/b/ba/Stripe_Logo%2C_revised_2016.svg');
        $this->method_title = 'Growtype WC - Stripe';
        $this->method_description = __('Allow to make transactions through stripe.', 'growtype-wc');
        $this->has_fields = true;
        $this->chosen = false;
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
                'label' => __('Testing mode', 'growtype-wc'),
                'default' => 'yes'
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
        $failed_notice = apply_filters('growtype_wc_gateway_stripe_process_payment_failed_notice', '');

        if (!empty($failed_notice)) {
            return array (
                'result' => 'failure'
            );
        }

        global $woocommerce;

        $order = wc_get_order($order_id);

        $order->payment_complete();

        wc_reduce_stock_levels($order_id);

        $order->update_status('completed');

        $woocommerce->cart->empty_cart();

        return array (
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
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
}
