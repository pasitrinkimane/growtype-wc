<?php

/**
 * Class WC_Gateway_Free
 * No charge payment method
 */
class Growtype_Wc_Payment_Gateway_Cc extends WC_Payment_Gateway
{
    public $domain;

    /**
     * Constructor for the gateway.
     */

    private $card_information_fields_orientation;
    private $visible_in_frontend;

    const PROVIDER_ID = 'growtype_wc_cc';

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

        add_filter('woocommerce_credit_card_form_fields', array ($this, 'extend_woocommerce_credit_card_form_fields'), 0, 2);
    }

    public function extend_woocommerce_credit_card_form_fields($default_fields, $id)
    {
        $cc_form = new WC_Payment_Gateway_CC();

        $extra_values['card-holder-name-field'] = '<p class="form-row form-row-wide">
				<label for="' . esc_attr($id) . '-card-holder-name">' . esc_html__('Name on card', 'woocommerce') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr($id) . '-card-holder-name" class="input-text wc-credit-card-form-card-holder-name" inputmode="text" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="Full name" ' . $cc_form->field_name($id . '-card-holder-name') . ' />
			</p>';

        $default_fields = array_merge($extra_values, $default_fields);

        if ($this->card_information_fields_orientation === 'combined') {
            $default_fields['card-number-field'] = '<p class="form-row form-row-wide">
				<label for="' . esc_attr($id) . '-card-number">' . esc_html__('Card information', 'woocommerce') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr($id) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $cc_form->field_name('card-number') . ' />
			</p>';
        }

        return $default_fields;
    }

    protected function setup_properties()
    {
        $this->id = self::PROVIDER_ID;
        $this->icon = apply_filters('growtype_wc_payment_gateway_cc_icon', GROWTYPE_WC_URL_PUBLIC . 'icons/credit-cards.svg');
        $this->method_title = 'Growtype WC - Credit card';
        $this->method_description = __('Allow to make transactions through paypal.', 'growtype-wc');
        $this->has_fields = true;
        $this->chosen = false;
        $this->card_information_fields_orientation = apply_filters('growtype_wc_payment_gateway_cc_card_information_fields_orientation', 'separated'); //combined
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
        global $woocommerce;

        $holder_name = $_REQUEST['-growtype_wc_cc-card-holder-name'];
        $card_number = $_REQUEST['growtype_wc_cc-card-number'];
        $card_expiry = $_REQUEST['growtype_wc_cc-card-expiry'];
        $card_cvc = $_REQUEST['growtype_wc_cc-card-cvc'];

        $passed_validation = true;
        if (empty($holder_name)) {
            wc_add_notice(__('Please enter card holder name.', 'growtype-wc'), 'error');
            $passed_validation = false;
        }

        if (!growtype_wc_card_number_is_valid($card_number)) {
            wc_add_notice(__('Please enter a valid card number.', 'growtype-wc'), 'error');
            $passed_validation = false;
        }

        if (!growtype_wc_card_expiry_is_valid($card_expiry)) {
            wc_add_notice(__('Please enter a valid card expiry date.', 'growtype-wc'), 'error');
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

        $failed_notice = apply_filters('growtype_wc_payment_gateway_cc_process_payment_failed_notice', '');

        if (!empty($failed_notice)) {
            return array (
                'result' => 'failure'
            );
        }

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
        $payment_box_inner_classes = ['payment_box-inner'];

        $payment_box_inner_classes[] = 'payment_box-inner--' . $this->card_information_fields_orientation;

        echo '<div class="' . implode(' ', $payment_box_inner_classes) . '">';

        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
        }

        $cc_form = new WC_Payment_Gateway_CC();
        $cc_form->id = $this->id;
        $cc_form->supports = $this->supports;
        $cc_form->form();

        $label = apply_filters('growtype_wc_payment_gateway_cc_payment_button_label', __('Complete secure payment', 'growtype-wc'));

        do_action('growtype_wc_payment_gateway_cc_before_payment_button');

        echo '<button type="submit" class="btn btn-primary btn-card">' . $label . '</button>';

        do_action('growtype_wc_payment_gateway_cc_after_payment_button');

        echo '</div>';
    }
}
