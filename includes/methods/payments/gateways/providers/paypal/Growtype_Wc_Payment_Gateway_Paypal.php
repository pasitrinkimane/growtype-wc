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
    private $merchant_id;
    private $visible_in_frontend;
    public $enable_card_payments;
    /** @var Growtype_Wc_Payment_Gateway_Paypal_Subscriptions */
    public $subscriptions;
    /** @var Growtype_Wc_Payment_Gateway_Paypal_Orders */
    public $orders;
    /** @var Growtype_Wc_Payment_Gateway_Paypal_Settings */
    public $settings;
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
        include_once 'partials/Growtype_Wc_Payment_Gateway_Paypal_Token.php';
        $this->token = new Growtype_Wc_Payment_Gateway_Paypal_Token($this);

        include_once 'partials/Growtype_Wc_Payment_Gateway_Paypal_Settings.php';
        $this->settings = new Growtype_Wc_Payment_Gateway_Paypal_Settings($this);

        include_once 'partials/Growtype_Wc_Payment_Gateway_Paypal_Orders.php';
        $this->orders = new Growtype_Wc_Payment_Gateway_Paypal_Orders($this);

        include_once 'partials/Growtype_Wc_Payment_Gateway_Paypal_Subscriptions.php';
        $this->subscriptions = new Growtype_Wc_Payment_Gateway_Paypal_Subscriptions($this);

        include_once 'partials/Growtype_Wc_Payment_Gateway_Paypal_Webhook.php';
        new Growtype_Wc_Payment_Gateway_Paypal_Webhook($this);

        include_once 'partials/Growtype_Wc_Payment_Gateway_Paypal_Hosted_Fields.php';
        new Growtype_Wc_Payment_Gateway_Paypal_Hosted_Fields($this);

        include_once 'partials/Growtype_Wc_Payment_Gateway_Paypal_Redirects.php';
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
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = $this->settings->get_form_fields();
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
        $this->settings->render_payment_fields();
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

    public function capture_order($access_token, $order_id)
    {
        return $this->orders->capture_order($access_token, $order_id);
    }

    public function charge_intent($parent_order_id, $product_id, $description)
    {
        return $this->orders->charge_intent($parent_order_id, $product_id, $description);
    }
}
