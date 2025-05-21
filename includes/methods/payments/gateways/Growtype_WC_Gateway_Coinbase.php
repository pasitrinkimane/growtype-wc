<?php

/**
 * Class Growtype_WC_Gateway_Coinbase
 * Payment method for Coinbase Commerce
 */
class Growtype_WC_Gateway_Coinbase extends WC_Payment_Gateway
{
    const PAYMENT_METHOD_KEY = 'gwc-coinbase';
    const PROVIDER_ID = 'growtype_wc_coinbase';

    private $api_key;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();

        $this->api_key = $this->get_option('api_key');

        $this->setup_extra_properties();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array ($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array ($this, 'thankyou_page'));
        add_action('woocommerce_add_to_cart', array ($this, 'woocommerce_add_to_cart_extend'), 20, 6);
        add_filter('template_redirect', array ($this, 'payment_redirect'));
    }

    protected function setup_properties()
    {
        $this->id = self::PROVIDER_ID;
        $this->icon = apply_filters('growtype_wc_gateway_coinbase_icon', 'https://upload.wikimedia.org/wikipedia/commons/1/1a/Coinbase.svg');
        $this->method_title = 'Growtype WC - Coinbase Commerce';
        $this->method_description = __('Accept cryptocurrency payments via Coinbase Commerce.', 'growtype-wc');
        $this->has_fields = true;
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array (
            'enabled' => array (
                'title' => __('Enable/Disable', 'growtype-wc'),
                'type' => 'checkbox',
                'label' => __('Enable Coinbase Commerce', 'growtype-wc'),
                'default' => 'no',
            ),
            'title' => array (
                'title' => __('Title', 'growtype-wc'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'growtype-wc'),
                'default' => __('Pay with Cryptocurrency', 'growtype-wc'),
                'desc_tip' => true,
            ),
            'description' => array (
                'title' => __('Description', 'growtype-wc'),
                'type' => 'textarea',
                'description' => __('Description of this payment method on the checkout page.', 'growtype-wc'),
                'default' => __('Pay securely with Bitcoin, Ethereum, Litecoin, and other cryptocurrencies.', 'growtype-wc'),
            ),
            'add_to_card_redirect_coinbase_checkout' => array (
                'title' => __('Coinbase checkout - add to cart', 'growtype-wc'),
                'type' => 'checkbox',
                'label' => __('Redirect to coinbase checkout after add to cart', 'growtype-wc'),
                'default' => 'no'
            ),
            'api_key' => array (
                'title' => __('API Key', 'growtype-wc'),
                'type' => 'text',
                'description' => __('Your Coinbase Commerce API key.', 'growtype-wc'),
                'default' => '',
            ),
        );
    }

    protected function setup_extra_properties()
    {
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
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
            // Prepare request to Coinbase Commerce API
            $api_url = 'https://api.commerce.coinbase.com/charges';

            $headers = array (
                'Content-Type' => 'application/json',
                'X-Cc-Api-Key' => $this->api_key,
                'X-Cc-Version' => '2018-03-22'
            );

            $body = [
                'name' => 'Order #' . $order->get_id(),
                'description' => 'Payment for Order #' . $order->get_id(),
                'pricing_type' => 'fixed_price',
                'local_price' => [
                    'amount' => $order->get_total(),
                    'currency' => get_woocommerce_currency(),
                ],
                'metadata' => [
                    'order_id' => $order_id,
                ],
                'redirect_url' => $this->get_return_url($order),
                'cancel_url' => $order->get_cancel_order_url(),
            ];

            $response = $this->send_request($api_url, $headers, $body);

            if (isset($response['data']['hosted_url'])) {
                $order->add_order_note(__('Payment initiated. Redirecting to Coinbase Commerce.', 'growtype-wc'));
                $order->update_meta_data('charge_uuid', $response['data']['id']);
                $order->save();

                return [
                    'result' => 'success',
                    'redirect' => $response['data']['hosted_url'],
                    'charge_uuid' => $response['data']['id'],
                ];
            } else {
                wc_add_notice(__('Payment error: Failed to create Coinbase Commerce charge.', 'growtype-wc'), 'error');

                return [
                    'result' => 'failure',
                    'message' => 'Failed to create Coinbase Commerce charge.'
                ];
            }
        } catch (Exception $e) {
            wc_add_notice(__('Payment error: ', 'growtype-wc') . $e->getMessage(), 'error');

            return [
                'result' => 'failure'
            ];
        }
    }

    function payment_redirect()
    {
        if (growtype_wc_is_thankyou_page()) {
            global $wp;

            $order_id = apply_filters('woocommerce_thankyou_order_id', absint($wp->query_vars['order-received']));
            $order = wc_get_order($order_id);

            if (empty($order)) {
                wp_redirect(home_url());
                exit();
            }

            if ($order->get_status() !== 'completed') {
                $payment_method = $order->get_payment_method();

                if ($payment_method === self::PROVIDER_ID) {
                    $charge_uuid = $order->get_meta('charge_uuid');
                    $coinbase_charge = $this->get_coinbase_charge($charge_uuid);

                    if ($coinbase_charge['success'] === true) {
                        $order->payment_complete();
                    } else {
                        error_log(sprintf('Order %s is not paid and status is missing. Coinbase charge UUID: %s. Charge data: %s.', $order_id, $charge_uuid, print_r($coinbase_charge, true)));
                    }

                    error_log(sprintf('Order %s charged. Coinbase charge UUID: %s. Charge data: %s.', $order_id, $charge_uuid, print_r($coinbase_charge, true)));
                }
            }
        }
    }

    /**
     * Thank you page
     */
    public function thankyou_page()
    {
        echo '<p>' . __('Thank you for your order. We will process your payment as soon as it is confirmed on the blockchain.', 'growtype-wc') . '</p>';
    }

    public function payment_fields()
    {
        $description = $this->get_description();

        if ($description) {
            echo wpautop(wptexturize($description));
        }
    }

    private function send_request($url, $headers, $body)
    {
        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30,
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $response_body = wp_remote_retrieve_body($response);
        return json_decode($response_body, true);
    }

    function woocommerce_add_to_cart_extend($cart_item_key, $product_id, $quantity, $variation_id, $variation_attributes, $cart_item_data)
    {
        if ($this->get_option('add_to_card_redirect_coinbase_checkout') === 'yes' && isset($_GET['payment_method']) && sanitize_text_field($_GET['payment_method']) === self::PAYMENT_METHOD_KEY) {
            $product = wc_get_product($product_id);
            $order = wc_create_order();
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

            $order->save();

            WC()->cart->empty_cart();

            $order_id = $order->get_id();

            $process_payment = $this->process_payment($order_id);

            if ($process_payment['result'] === 'success') {
                wp_redirect($process_payment['redirect']);
                exit;
            } else {
                wp_redirect(wc_get_checkout_url());
                exit;
            }
        }
    }

    public function get_coinbase_charge($charge_uuid)
    {
        $api_url = "https://api.commerce.coinbase.com/charges/" . $charge_uuid;

        $headers = [
            'Content-Type' => 'application/json',
            'X-CC-Api-Key' => $this->api_key,
            'X-CC-Version' => '2018-03-22',
        ];

        $response = wp_remote_get($api_url, ['headers' => $headers]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) === 200) {
            return [
                'success' => true,
                'data' => $data,
            ];
        } else {
            return [
                'success' => false,
                'error' => $data['error'] ?? 'Unknown error occurred',
            ];
        }
    }
}
