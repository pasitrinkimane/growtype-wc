<?php

/**
 * Class WC_Gateway_Free
 * No charge payment method
 */
class Growtype_WC_Gateway_Paypal extends WC_Payment_Gateway
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

        add_action('woocommerce_email_before_order_table', array ($this, 'email_instructions'), 10, 3);
    }

    protected function setup_properties()
    {
        $this->id = 'growtype_wc_paypal';
        $this->icon = apply_filters('growtype_wc_gateway_paypal_icon', 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b5/PayPal.svg/800px-PayPal.svg.png');
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
                'default' => 'yes'
            ),
            'visible_in_frontend' => array (
                'title' => __('Visibility', 'growtype-wc'),
                'type' => 'checkbox',
                'label' => __('Method is visible in frontend', 'growtype-wc'),
                'default' => 'true'
            ),
            'title' => array (
                'title' => __('Method title', 'growtype-wc'),
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
        $failed_notice = apply_filters('growtype_wc_gateway_paypal_process_payment_failed_notice', '');

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

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($this->instructions && !$sent_to_admin && 'offline' === $order->payment_method && $order->has_status('on-hold')) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }
    }

    public function payment_fields()
    {
        echo '<button class="btn btn-primary btn-paypal"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/39/PayPal_logo.svg/527px-PayPal_logo.svg.png"/></button>';
    }
}
