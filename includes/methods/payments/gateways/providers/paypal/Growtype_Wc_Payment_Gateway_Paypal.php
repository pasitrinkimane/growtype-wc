<?php

/**
 * Class WC_Gateway_Free
 * No charge payment method
 */
class Growtype_Wc_Payment_Gateway_Paypal extends WC_Payment_Gateway
{
    public $domain;

    const CHECKOUT_FLOW_ORDERS_API_ONE_TIME = 'orders_api_one_time';
    const CHECKOUT_FLOW_ORDERS_API_RECURRING_CARD = 'orders_api_recurring_card';
    const CHECKOUT_FLOW_ORDERS_API_RECURRING_APPLE_PAY = 'orders_api_recurring_apple_pay';
    const CHECKOUT_FLOW_BILLING_SUBSCRIPTION = 'billing_subscription';
    const CHECKOUT_FLOW_UNAVAILABLE = 'unavailable';
    const PAYMENT_METHOD_KEY = 'gwc-paypal';
    const PROVIDER_ID = 'growtype_wc_paypal';
    
    private $client_id;
    private $test_mode;
    private $client_secret;
    private $merchant_id;
    private $visible_in_frontend;
    public $enable_card_payments;
    /** @var Growtype_Wc_Payment_Gateway_Paypal_Subscriptions */
    public $subscriptions;
    /** @var Growtype_Wc_Payment_Gateway_Paypal_Orders */
    public $orders;
    /** @var Growtype_Wc_Payment_Gateway_Paypal_Settings */
    public $paypal_settings;
    /** @var Growtype_Wc_Payment_Gateway_Paypal_Token */
    public $token;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->setup_properties();
        $this->load_partials();
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

        $this->merchant_id = $this->test_mode ? $this->get_option('merchant_id_test') : $this->get_option('merchant_id_live');

        $this->setup_extra_properties();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array ($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array ($this, 'thankyou_page'));
        add_filter('woocommerce_payment_complete_order_status', array ($this, 'change_payment_complete_order_status'), 10, 3);
    }

    protected function load_partials()
    {
        include_once __DIR__ . '/partials/Growtype_Wc_Payment_Gateway_Paypal_Token.php';
        $this->token = new Growtype_Wc_Payment_Gateway_Paypal_Token($this);

        include_once __DIR__ . '/partials/Growtype_Wc_Payment_Gateway_Paypal_Settings.php';
        $this->paypal_settings = new Growtype_Wc_Payment_Gateway_Paypal_Settings($this);

        include_once __DIR__ . '/partials/Growtype_Wc_Payment_Gateway_Paypal_Orders.php';
        $this->orders = new Growtype_Wc_Payment_Gateway_Paypal_Orders($this);

        include_once __DIR__ . '/partials/Growtype_Wc_Payment_Gateway_Paypal_Subscriptions.php';
        $this->subscriptions = new Growtype_Wc_Payment_Gateway_Paypal_Subscriptions($this);

        include_once __DIR__ . '/partials/Growtype_Wc_Payment_Gateway_Paypal_Webhook.php';
        new Growtype_Wc_Payment_Gateway_Paypal_Webhook($this);

        include_once __DIR__ . '/partials/Growtype_Wc_Payment_Gateway_Paypal_Card_Form.php';
        
        include_once __DIR__ . '/partials/Growtype_Wc_Payment_Gateway_Paypal_Payment_Form.php';
        Growtype_Wc_Payment_Gateway_Paypal_Payment_Form::init();

        include_once __DIR__ . '/partials/Growtype_Wc_Payment_Gateway_Paypal_Hosted_Fields.php';
        new Growtype_Wc_Payment_Gateway_Paypal_Hosted_Fields($this);

        include_once __DIR__ . '/partials/Growtype_Wc_Payment_Gateway_Paypal_Redirects.php';
        new Growtype_Wc_Payment_Gateway_Paypal_Redirects($this);
    }

    protected function setup_properties()
    {
        $this->id = self::PROVIDER_ID;
        $this->icon = apply_filters('growtype_wc_payment_gateway_paypal_icon', 'https://upload.wikimedia.org/wikipedia/commons/b/b7/PayPal_Logo_Icon_2014.svg');
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
        $this->enable_card_payments = $this->get_option('enable_card_payments') === 'yes';
    }

    /**
     * Expose client ID for use in front-end scripts.
     */
    public function get_client_id(): string
    {
        return (string)$this->client_id;
    }

    /**
     * Expose client secret for use by partial classes.
     */
    public function get_client_secret()
    {
        return $this->client_secret;
    }

    public function get_merchant_id()
    {
        return (string)$this->merchant_id;
    }

    /**
     * Payment method title used for orders created via Hosted Fields (card payments).
     */
    public function get_hosted_fields_title(): string
    {
        return 'Growtype WC - ' . $this->get_title() . ' HF';
    }

    /**
     * Whether the gateway is in test/sandbox mode.
     */
    public function is_test_mode(): bool
    {
        return (bool)$this->test_mode;
    }

    /**
     * Build a full PayPal API URL for sandbox or live, based on current test mode.
     *
     * @param string $path e.g. '/v2/checkout/orders' or '/v1/oauth2/token'
     * @return string Full URL
     */
    public function get_api_url(string $path): string
    {
        $base = $this->is_test_mode()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Resolve the server implementation allowed for this product/source pair.
     * Future recurring methods must register a distinct flow and endpoint.
     */
    public function resolve_checkout_flow(int $product_id, string $payment_source): string
    {
        $payment_source = sanitize_key($payment_source);
        $is_subscription = growtype_wc_product_is_subscription($product_id);

        if (!$is_subscription) {
            $default_flow = self::CHECKOUT_FLOW_ORDERS_API_ONE_TIME;
        } elseif ($payment_source === 'paypal') {
            $default_flow = self::CHECKOUT_FLOW_BILLING_SUBSCRIPTION;
        } elseif ($payment_source === 'card') {
            $default_flow = self::CHECKOUT_FLOW_ORDERS_API_RECURRING_CARD;
        } elseif ($payment_source === 'applepay') {
            $default_flow = self::CHECKOUT_FLOW_ORDERS_API_RECURRING_APPLE_PAY;
        } else {
            $default_flow = self::CHECKOUT_FLOW_UNAVAILABLE;
        }

        return (string) apply_filters(
            'growtype_wc_paypal_checkout_flow',
            $default_flow,
            $product_id,
            $payment_source,
            $is_subscription,
            $this
        );
    }

    public function is_orders_api_recurring_flow(string $checkout_flow): bool
    {
        return in_array($checkout_flow, [
            self::CHECKOUT_FLOW_ORDERS_API_RECURRING_CARD,
            self::CHECKOUT_FLOW_ORDERS_API_RECURRING_APPLE_PAY,
        ], true);
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = $this->paypal_settings->get_form_fields();
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        return $this->orders->process_payment($order_id);
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
        return $this->orders->get_completed_order_status($status, $order_id, $order);
    }

    public function payment_fields()
    {
        $this->paypal_settings->render_payment_fields();
    }

    public function get_access_token_details($client_id, $client_secret)
    {
        return $this->token->get_access_token_details($client_id, $client_secret);
    }

    public function get_access_token($client_id, $client_secret)
    {
        return $this->token->get_access_token($client_id, $client_secret);
    }

    public function get_order_data($access_token, $paypal_order_id)
    {
        return $this->orders->get_order_data($access_token, $paypal_order_id);
    }

    public function create_order($access_token, $wc_order_id, $applied_coupons = null, $vault_source = 'card')
    {
        return $this->orders->create_order($access_token, $wc_order_id, $applied_coupons, $vault_source);
    }

    public function capture_order($access_token, $order_id, string $request_id = '')
    {
        return $this->orders->capture_order($access_token, $order_id, $request_id);
    }

    public function charge_intent($parent_order_id, $product_id, $description)
    {
        return $this->orders->charge_intent($parent_order_id, $product_id, $description);
    }

    /**
     * Process a refund for an order via the PayPal Captures API.
     *
     * Called automatically by WooCommerce when the admin clicks the "Refund" button
     * on the order edit screen. The order must have a _transaction_id (PayPal capture ID)
     * stored on it for the refund to be processed automatically.
     *
     * @param int        $order_id WooCommerce order ID.
     * @param float|null $amount   Amount to refund. Defaults to the full order total.
     * @param string     $reason   Reason for the refund (shown in order notes).
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Order not found.', 'growtype-wc' ) );
        }

        $capture_id = $order->get_transaction_id();

        if ( empty( $capture_id ) ) {
            return new WP_Error(
                'missing_capture_id',
                __( 'Cannot refund: no PayPal capture ID found on this order (_transaction_id is empty).', 'growtype-wc' )
            );
        }

        // Obtain a fresh (or cached) access token.
        $access_token = $this->get_access_token( $this->get_client_id(), $this->get_client_secret() );

        if ( empty( $access_token ) ) {
            return new WP_Error( 'token_error', __( 'Could not obtain a PayPal access token.', 'growtype-wc' ) );
        }

        // Determine refund amount — default to the full order total.
        $refund_amount = ( $amount !== null )
            ? number_format( (float) $amount, 2, '.', '' )
            : number_format( (float) $order->get_total(), 2, '.', '' );

        $currency = $order->get_currency();

        $body = [
            'amount' => [
                'value'         => $refund_amount,
                'currency_code' => $currency,
            ],
        ];

        if ( ! empty( $reason ) ) {
            $body['note_to_payer'] = substr( $reason, 0, 255 ); // PayPal caps at 255 chars
        }

        $refund_url = $this->get_api_url( "/v2/payments/captures/{$capture_id}/refund" );

        $response = wp_remote_post( $refund_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            $msg = $response->get_error_message();
            error_log( "[GWC PayPal] process_refund HTTP error for order {$order_id}: {$msg}" );
            return new WP_Error( 'http_error', $msg );
        }

        $http_code   = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body_raw, true ) ?: [];
        $refund_id   = $data['id'] ?? '';
        $status      = $data['status'] ?? '';

        error_log( sprintf(
            '[GWC PayPal] process_refund: order=%d capture=%s http=%d status=%s refund_id=%s',
            $order_id, $capture_id, $http_code, $status, $refund_id
        ) );

        // PayPal returns 201 Created for a successful refund.
        if ( $http_code !== 201 || ! in_array( $status, [ 'COMPLETED', 'PENDING' ], true ) ) {
            $detail = $data['details'][0]['description'] ?? $data['message'] ?? $body_raw;
            error_log( "[GWC PayPal] process_refund failed. Response: {$body_raw}" );
            return new WP_Error(
                'refund_failed',
                sprintf(
                    /* translators: 1: HTTP status, 2: PayPal error detail */
                    __( 'PayPal refund failed (HTTP %1$s): %2$s', 'growtype-wc' ),
                    $http_code,
                    $detail
                )
            );
        }

        $note = sprintf(
            /* translators: 1: amount, 2: currency, 3: PayPal refund ID, 4: reason */
            __( 'PayPal refund of %1$s %2$s processed successfully. PayPal Refund ID: %3$s.%4$s', 'growtype-wc' ),
            $refund_amount,
            $currency,
            $refund_id,
            ! empty( $reason ) ? ' Reason: ' . $reason : ''
        );

        $order->add_order_note( $note );

        return true;
    }
}
