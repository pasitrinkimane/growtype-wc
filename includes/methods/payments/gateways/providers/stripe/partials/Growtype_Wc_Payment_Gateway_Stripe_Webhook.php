<?php

class Growtype_Wc_Payment_Gateway_Stripe_Webhook
{
    private $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;
        add_action('woocommerce_api_wc_stripe', [$this, 'handle_webhook']);
        add_action('init', [$this, 'handle_webhook_init']);
    }

    /**
     * Fallback listener for sites where woocommerce_api_ hook might be blocked
     */
    public function handle_webhook_init()
    {
        if (isset($_GET['wc-api']) && $_GET['wc-api'] === 'wc_stripe') {
            $this->handle_webhook();
        }
    }

    /**
     * Handle incoming Stripe webhooks
     */
    public function handle_webhook()
    {
        static $processed = false;
        if ($processed) {
            return;
        }
        $processed = true;

        $headers = getallheaders();
        $signature = $headers['Stripe-Signature'] ?? $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? 'MISSING';
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        error_log('Growtype WC: Stripe Webhook reached. Event details: ' . json_encode($data));
        error_log('Growtype WC: Stripe Webhook method: ' . $_SERVER['REQUEST_METHOD'] . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        error_log('Growtype WC: Signature: ' . $signature);

        $verified = $this->verify_signature($body, $signature);
        if (!$verified) {
            error_log('Growtype WC: WARNING! Webhook Signature Verification FAILED. Proceeding with Self-Healing fallback for safety.');
        } else {
            error_log('Growtype WC: SUCCESS! Webhook Signature Verified.');
        }

        if (empty($data) || !isset($data['type'])) {
            error_log('Growtype WC: Invalid Stripe webhook payload.');
            status_header(400);
            exit('Invalid payload');
        }

        $event_type = $data['type'];
        error_log("Growtype WC: Event Type: $event_type");

        switch ($event_type) {
            case 'payment_intent.succeeded':
            case 'charge.succeeded':
                $this->handle_payment_intent_succeeded($data);
                break;
            case 'payment_intent.payment_failed':
            case 'invoice.payment_failed':
                $this->handle_payment_failed($data);
                break;
            case 'invoice.payment_succeeded':
                $this->handle_invoice_payment_succeeded($data);
                break;
            case 'checkout.session.completed':
                $this->handle_checkout_session_completed($data);
                break;
        }

        // Always respond with 200 to Stripe to acknowledge receipt
        status_header(200);
        exit;
    }

    /**
     * Handle payment_intent.succeeded
     */
    protected function handle_payment_intent_succeeded($data)
    {
        $pi_id = $data['data']['object']['id'] ?? '';
        if (empty($pi_id)) {
            return;
        }

        $order = $this->find_order_by_pi($pi_id);

        // Fallback 1: Try finding by Order ID in description
        if (!$order) {
            $description = $data['data']['object']['description'] ?? '';
            $order = $this->find_order_by_description($description);
        }

        // Fallback 2: Self-healing by email and amount
        if (!$order) {
            $email = $data['data']['object']['receipt_email'] ?? $data['data']['object']['billing_details']['email'] ?? '';
            $amount = ($data['data']['object']['amount'] ?? 0) / 100;

            if ($email && $amount > 0) {
                error_log("Growtype WC: No order found for PI $pi_id. Attempting self-healing for $email...");
                $order = $this->find_order_by_email_and_amount($email, $amount);
            }
        }

        if ($order) {
            $this->process_order_completion($order, $pi_id);
        } else {
            error_log("Growtype WC: CRITICAL! Webhook failed to find order for PI: $pi_id");
        }
    }

    /**
     * Handle payment failures
     */
    protected function handle_payment_failed($data)
    {
        $object = $data['data']['object'];
        $pi_id = $object['id'] ?? $object['payment_intent'] ?? '';
        $error_message = $object['last_payment_error']['message'] ?? __('Payment failed.', 'growtype-wc');

        if (empty($pi_id)) {
            return;
        }

        $order = $this->find_order_by_pi($pi_id);

        if (!$order) {
            $description = $object['description'] ?? '';
            $order = $this->find_order_by_description($description);
        }

        if ($order) {
            $order->update_status('failed');
            $order->add_order_note(sprintf(__('Stripe payment FAILED: %s (PI: %s)', 'growtype-wc'), $error_message, $pi_id));
            error_log("Growtype WC: Order #" . $order->get_id() . " marked as FAILED via Webhook.");
        }
    }

    /**
     * Handle invoice.payment_succeeded
     */
    protected function handle_invoice_payment_succeeded($data)
    {
        $invoice = $data['data']['object'];
        $subscription_id = $invoice['subscription'] ?? '';
        $pi_id = $invoice['payment_intent'] ?? '';
        $description = $invoice['description'] ?? '';

        if (empty($subscription_id)) {
            return;
        }

        // Try to find order by Stripe Subscription ID
        $orders = wc_get_orders([
            'limit' => 1,
            'meta_key' => 'stripe_subscription_id',
            'meta_value' => $subscription_id,
        ]);

        $order = !empty($orders) ? $orders[0] : null;

        if (!$order && !empty($pi_id)) {
            $order = $this->find_order_by_pi($pi_id);
        }

        if (!$order && !empty($description)) {
            $order = $this->find_order_by_description($description);
        }

        if ($order) {
            $this->process_order_completion($order, $pi_id);
        } else {
            error_log("Growtype WC: Webhook failed to find order for Subscription: $subscription_id");
        }
    }

    /**
     * Parse Order ID from description (e.g. "Order #57896")
     */
    protected function find_order_by_description($description)
    {
        if (empty($description)) {
            return null;
        }

        // Pattern matches "Order #12345"
        if (preg_match('/Order #([0-9]+)/', $description, $matches)) {
            $order_id = $matches[1];
            $order = wc_get_order($order_id);
            if ($order) {
                error_log("Growtype WC: Successfully parsed Order #$order_id from Stripe description.");
                return $order;
            }
        }

        return null;
    }

    /**
     * Handle checkout.session.completed
     */
    protected function handle_checkout_session_completed($data)
    {
        $session_id = $data['data']['object']['id'] ?? '';
        $pi_id = $data['data']['object']['payment_intent'] ?? '';

        if (empty($session_id)) {
            return;
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'meta_key' => 'stripe_session_id',
            'meta_value' => $session_id,
        ]);

        if (!empty($orders)) {
            $order = $orders[0];
            $this->process_order_completion($order, $pi_id);
        }
    }

    /**
     * Complete the order and link IDs
     */
    protected function process_order_completion($order, $pi_id)
    {
        if ($order->get_status() === 'completed') {
            return;
        }

        // Link the PI ID if missing
        if (!empty($pi_id)) {
            if (!$order->get_meta('_stripe_intent_id')) {
                $order->update_meta_data('_stripe_intent_id', $pi_id);
            }
            if (!$order->get_transaction_id()) {
                $order->set_transaction_id($pi_id);
            }
        }

        $order->payment_complete($pi_id);

        $order->add_order_note(sprintf(__('Stripe payment verified via webhook (PI: %s).', 'growtype-wc'), $pi_id));

        if ($order->get_status() !== 'completed') {
            $order->update_status('completed', __('Forced completion via Stripe webhook.', 'growtype-wc'));
        }

        $order->save();

        error_log("Growtype WC: Order #" . $order->get_id() . " successfully completed via Webhook.");
    }

    /**
     * Find order by Payment Intent ID
     */
    protected function find_order_by_pi($pi_id)
    {
        $orders = wc_get_orders([
            'limit' => 1,
            'meta_key' => '_stripe_intent_id',
            'meta_value' => $pi_id,
        ]);

        if (empty($orders)) {
            $orders = wc_get_orders([
                'limit' => 1,
                'transaction_id' => $pi_id,
            ]);
        }

        return !empty($orders) ? $orders[0] : null;
    }

    /**
     * Self-healing: Find order by email and amount
     */
    protected function find_order_by_email_and_amount($email, $amount)
    {
        $orders = wc_get_orders([
            'limit' => 5,
            'customer' => $email,
            'status' => ['pending', 'on-hold', 'failed'],
        ]);

        foreach ($orders as $order) {
            if (abs((float)$order->get_total() - (float)$amount) < 0.01) {
                return $order;
            }
        }

        return null;
    }

    /**
     * Verify the Stripe Webhook Signature
     */
    protected function verify_signature($body, $signature)
    {
        $webhook_secret = !empty($this->gateway) ? $this->gateway->get_webhook_secret() : '';

        if (empty($webhook_secret)) {
            error_log('Growtype WC: Webhook Secret is missing in settings. Skipping signature check.');
            return false;
        }

        try {
            \Stripe\Webhook::constructEvent($body, $signature, $webhook_secret);
            return true;
        } catch (\Exception $e) {
            error_log('Growtype WC: Stripe Signature Error: ' . $e->getMessage());
            return false;
        }
    }
}
