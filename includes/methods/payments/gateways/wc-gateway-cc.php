<?php

/**
 * Class WC_Gateway_Free
 * No charge payment method
 */
class Growtype_WC_Gateway_Cc extends WC_Payment_Gateway
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

        add_filter('woocommerce_credit_card_form_fields', array ($this, 'extend_woocommerce_credit_card_form_fields'), 0, 2);
    }

    public function extend_woocommerce_credit_card_form_fields($default_fields, $id)
    {
        $cc_form = new WC_Payment_Gateway_CC();

        $extra_values['card-holder-name-field'] = '<p class="form-row form-row-wide">
				<label for="' . esc_attr($id) . '-card-holder-name">' . esc_html__('Name on card', 'woocommerce') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr($id) . '-card-holder-name" class="input-text wc-credit-card-form-card-holder-name" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="Full name" ' . $cc_form->field_name($id . '-card-holder-name') . ' />
			</p>';

        $default_fields = array_merge($extra_values, $default_fields);

        return $default_fields;
    }

    protected function setup_properties()
    {
        $this->id = 'growtype_wc_cc';
        $this->icon = apply_filters('growtype_wc_gateway_cc_icon', GROWTYPE_WC_URL_PUBLIC . 'icons/credit-cards.svg');
        $this->method_title = 'Growtype WC - Credit card';
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
                'default' => __('Credit card', 'growtype-wc'),
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
        $holder_name = $_REQUEST['-growtype_wc_cc-card-holder-name'];
        $card_number = $_REQUEST['growtype_wc_cc-card-number'];
        $card_expiry = $_REQUEST['growtype_wc_cc-card-expiry'];
        $card_cvc = $_REQUEST['growtype_wc_cc-card-cvc'];

        $passed_validation = true;
        if (empty($holder_name)) {
            wc_add_notice(__('Please enter card holder name.', 'growtype-wc'), 'error');
            $passed_validation = false;
        }

        if (empty($card_number)) {
            wc_add_notice(__('Please enter card number.', 'growtype-wc'), 'error');
            $passed_validation = false;
        }

        if (empty($card_expiry)) {
            wc_add_notice(__('Please enter card expiry.', 'growtype-wc'), 'error');
            $passed_validation = false;
        }

        if (empty($card_cvc)) {
            wc_add_notice(__('Please enter card cvc.', 'growtype-wc'), 'error');
            $passed_validation = false;
        }

        if (!$passed_validation) {
            return array (
                'result' => 'failure'
            );
        }

        $failed_notice = apply_filters('growtype_wc_gateway_cc_process_payment_failed_notice', '');

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
        $cc_form = new WC_Payment_Gateway_CC();
        $cc_form->id = $this->id;
        $cc_form->supports = $this->supports;
        $cc_form->form();

        echo '<button type="submit" class="btn btn-primary btn-card">Complete secure payment</button>';
    }
}
