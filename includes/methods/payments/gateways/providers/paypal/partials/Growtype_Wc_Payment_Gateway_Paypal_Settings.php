<?php

/**
 * PayPal Gateway Settings configuration.
 */
class Growtype_Wc_Payment_Gateway_Paypal_Settings
{
    /** @var Growtype_Wc_Payment_Gateway_Paypal */
    private $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Get the form fields for the PayPal gateway settings.
     *
     * @return array
     */
    public function get_form_fields(): array
    {
        return array (
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
            'enable_card_payments' => array (
                'title' => __('Enable Card Payments', 'growtype-wc'),
                'type' => 'checkbox',
                'label' => __('Enable credit card payments via PayPal', 'growtype-wc'),
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
            'merchant_id_test' => array (
                'title'       => __('Merchant id - Test', 'growtype-wc'),
                'type'        => 'text',
                'description' => __('Required for Google Pay & Apple Pay. Find it in your PayPal Developer Dashboard → Sandbox Accounts.', 'growtype-wc'),
                'desc_tip'    => true,
            ),
            'client_id_live' => array (
                'title' => __('Client id - Live', 'growtype-wc'),
                'type' => 'text',
            ),
            'client_secret_live' => array (
                'title' => __('Client secret - Live', 'growtype-wc'),
                'type' => 'text',
            ),
            'merchant_id_live' => array (
                'title'       => __('Merchant id - Live', 'growtype-wc'),
                'type'        => 'text',
                'description' => __('Required for Google Pay & Apple Pay. Find it in your PayPal Developer Dashboard → Live Accounts.', 'growtype-wc'),
                'desc_tip'    => true,
            ),
            'webhook_id' => array (
                'title'       => __('Webhook ID', 'growtype-wc'),
                'type'        => 'text',
                'description' => __('Required for webhook signature verification. Find it in PayPal Developer Dashboard → Webhooks → select your webhook → Webhook ID. Enables secure event verification.', 'growtype-wc'),
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Render the payment fields on the checkout page.
     */
    public function render_payment_fields()
    {
        $description = $this->gateway->get_description();
        if ($description) {
            echo wpautop(wptexturize($description));
        }

        do_action('growtype_wc_payment_gateway_paypal_before_payment_button');

        echo '<button class="btn btn-primary btn-paypal"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/39/PayPal_logo.svg/527px-PayPal_logo.svg.png"/></button>';

        do_action('growtype_wc_payment_gateway_paypal_after_payment_button');
    }
}
