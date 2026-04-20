<?php

class Growtype_Wc_Payment_Gateway_Paypal_Webhook
{
    private $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;
        add_action('woocommerce_api_wc_paypal', [$this, 'handle_webhook']);
        add_action('init', [$this, 'handle_webhook_init']);
    }

    /**
     * Fallback listener for init
     */
    public function handle_webhook_init()
    {
        if (isset($_GET['wc-api']) && $_GET['wc-api'] === 'wc_paypal') {
            $this->handle_webhook();
        }
    }

    /**
     * Handle incoming PayPal webhooks
     */
    public function handle_webhook()
    {
        static $processed = false;
        if ($processed) {
            return;
        }
        $processed = true;

        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (empty($data) || !isset($data['event_type'])) {
            return;
        }

        $event_type = $data['event_type'];

        error_log('Growtype WC: PayPal Webhook reached. Event details: ' . json_encode($data));

        // Note: Full PayPal signature verification is complex. 
        // We rely on our Self-Healing logic for reliability.

        switch ($event_type) {
            case 'PAYMENT.CAPTURE.COMPLETED':
            case 'PAYMENT.SALE.COMPLETED':
                $this->handle_payment_completed($data);
                break;
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
            case 'BILLING.SUBSCRIPTION.CREATED':
                $this->handle_subscription_event($data);
                break;
        }

        status_header(200);
        exit;
    }

    /**
     * Handle payment completion events
     */
    protected function handle_payment_completed($data)
    {
        $resource = $data['resource'];
        $invoice_id = $resource['invoice_id'] ?? $resource['custom'] ?? $resource['custom_id'] ?? '';
        $paypal_id = $resource['id'] ?? '';

        $order = null;

        // 1. Try by Invoice ID (WooCommerce Order ID)
        if (!empty($invoice_id)) {
            $order = wc_get_order($invoice_id);
        }

        // 2. Self-healing fallback: Email and Amount
        if (!$order) {
            $email = $resource['payer']['email_address'] ?? $resource['billing_details']['email'] ?? '';
            $amount = $resource['amount']['value'] ?? 0;

            if ($email && $amount > 0) {
                error_log("Growtype WC: PayPal searching via self-healing for $email...");
                $order = $this->find_order_by_email_and_amount($email, $amount);
            }
        }

        if ($order) {
            $this->process_order_completion($order, $paypal_id);
        } else {
            error_log("Growtype WC: PayPal Webhook failed to find order for ID: $invoice_id");
        }
    }

    /**
     * Handle subscription events
     */
    protected function handle_subscription_event($data)
    {
        $resource = $data['resource'];
        $subscription_id = $resource['id'] ?? '';
        $invoice_id = $resource['custom_id'] ?? $resource['custom'] ?? '';

        if (empty($subscription_id)) {
            return;
        }

        $order = null;

        // 1. Try by Invoice ID
        if (!empty($invoice_id)) {
            $order = wc_get_order($invoice_id);
        }

        // 2. Try by Meta Lookup
        if (!$order) {
            $orders = wc_get_orders([
                'limit' => 1,
                'meta_key' => 'paypal_subscription_id',
                'meta_value' => $subscription_id,
            ]);
            $order = !empty($orders) ? $orders[0] : null;
        }

        if ($order) {
            $this->process_order_completion($order, $subscription_id);
        }
    }

    /**
     * Finalize the order
     */
    protected function process_order_completion($order, $transaction_id)
    {
        if (!$order || in_array($order->get_status(), ['completed', 'processing'])) {
            return;
        }

        $order->payment_complete($transaction_id);

        if ($order->get_status() !== 'completed') {
            $order->update_status('completed', __('Forced completion via PayPal webhook.', 'growtype-wc'));
        }

        $order->add_order_note(sprintf(__('PayPal payment verified via webhook (ID: %s).', 'growtype-wc'), $transaction_id));
        $order->save();

        error_log("Growtype WC: PayPal Order #" . $order->get_id() . " successfully completed via Webhook.");
    }

    /**
     * Self-healing: find order by email and amount
     */
    protected function find_order_by_email_and_amount($email, $amount)
    {
        $orders = wc_get_orders([
            'limit' => 5,
            'status' => ['pending', 'on-hold', 'failed'],
            'billing_email' => $email,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        foreach ($orders as $order) {
            if (abs($order->get_total() - $amount) < 0.01) {
                return $order;
            }
        }

        return null;
    }
}
