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

    public function handle_webhook_init()
    {
        if (isset($_GET['wc-api']) && $_GET['wc-api'] === 'wc_paypal') {
            $this->handle_webhook();
        }
    }

    public function handle_webhook()
    {
        static $processed = false;
        if ($processed) {
            return;
        }
        $processed = true;

        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['id']) || empty($data['event_type'])) {
            status_header(400);
            exit;
        }

        $event_id = sanitize_text_field((string) $data['id']);
        $event_type = sanitize_text_field((string) $data['event_type']);
        $event_handlers = $this->get_event_handlers();

        if (!isset($event_handlers[$event_type]) || !is_callable($event_handlers[$event_type])) {
            error_log(sprintf('[GWC PayPal Webhook] Acknowledged event %s (%s); no state transition configured.', $event_type, $event_id));
            status_header(200);
            exit;
        }

        $webhook_id = trim((string) $this->gateway->get_option('webhook_id'));
        if ($webhook_id === '') {
            error_log(sprintf('[GWC PayPal Webhook] Rejected %s (%s): webhook ID is not configured.', $event_type, $event_id));
            status_header(503);
            exit;
        }

        if (!$this->verify_webhook_signature($body, $webhook_id)) {
            error_log(sprintf('[GWC PayPal Webhook] Rejected %s (%s): signature verification failed.', $event_type, $event_id));
            status_header(400);
            exit;
        }

        $claim_key = 'gwc_pp_webhook_' . md5($event_id);
        if (!add_option($claim_key, gmdate('Y-m-d H:i:s'), '', false)) {
            error_log(sprintf('[GWC PayPal Webhook] Duplicate event ignored: %s (%s).', $event_type, $event_id));
            status_header(200);
            exit;
        }

        try {
            $handled = (bool) call_user_func($event_handlers[$event_type], $data);

            if (!$handled) {
                delete_option($claim_key);
                status_header(500);
                exit;
            }

            do_action('growtype_wc_paypal_webhook_processed', $event_type, $event_id);
            error_log(sprintf('[GWC PayPal Webhook] Processed %s (%s).', $event_type, $event_id));
            status_header(200);
            exit;
        } catch (Throwable $throwable) {
            delete_option($claim_key);
            error_log(sprintf('[GWC PayPal Webhook] Processing failed for %s (%s): %s', $event_type, $event_id, $throwable->getMessage()));
            status_header(500);
            exit;
        }
    }

    protected function get_event_handlers(): array
    {
        $handlers = [
            'PAYMENT.CAPTURE.COMPLETED' => [$this, 'handle_payment_completed'],
            'PAYMENT.SALE.COMPLETED' => [$this, 'handle_payment_completed'],
            'BILLING.SUBSCRIPTION.ACTIVATED' => [$this, 'handle_subscription_event'],
            'BILLING.SUBSCRIPTION.SUSPENDED' => [$this, 'handle_subscription_event'],
            'BILLING.SUBSCRIPTION.CANCELLED' => [$this, 'handle_subscription_event'],
            'BILLING.SUBSCRIPTION.EXPIRED' => [$this, 'handle_subscription_event'],
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => [$this, 'handle_subscription_event'],
        ];

        $handlers = apply_filters('growtype_wc_paypal_webhook_event_handlers', $handlers, $this);

        return is_array($handlers) ? $handlers : [];
    }

    protected function verify_webhook_signature(string $raw_body, string $webhook_id): bool
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = array_change_key_case(is_array($headers) ? $headers : [], CASE_LOWER);

        $required = [
            'paypal-auth-algo',
            'paypal-cert-url',
            'paypal-transmission-id',
            'paypal-transmission-sig',
            'paypal-transmission-time',
        ];
        foreach ($required as $header) {
            if (empty($headers[$header])) {
                return false;
            }
        }

        $access_token = $this->gateway->get_access_token(
            $this->gateway->get_client_id(),
            $this->gateway->get_client_secret()
        );
        if (empty($access_token)) {
            return false;
        }

        $response = wp_remote_post(
            $this->gateway->get_api_url('/v1/notifications/verify-webhook-signature'),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'auth_algo' => $headers['paypal-auth-algo'],
                    'cert_url' => $headers['paypal-cert-url'],
                    'transmission_id' => $headers['paypal-transmission-id'],
                    'transmission_sig' => $headers['paypal-transmission-sig'],
                    'transmission_time' => $headers['paypal-transmission-time'],
                    'webhook_id' => $webhook_id,
                    'webhook_event' => json_decode($raw_body, true),
                ]),
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($result) && ($result['verification_status'] ?? '') === 'SUCCESS';
    }

    protected function handle_payment_completed(array $data): bool
    {
        $resource = is_array($data['resource'] ?? null) ? $data['resource'] : [];
        if (($resource['status'] ?? 'COMPLETED') !== 'COMPLETED') {
            return false;
        }

        $transaction_id = sanitize_text_field((string) ($resource['id'] ?? ''));
        if ($transaction_id === '') {
            return false;
        }

        $subscription_id = sanitize_text_field((string) ($resource['billing_agreement_id'] ?? ''));
        if ($subscription_id !== '') {
            $order = $this->find_order_by_subscription_id($subscription_id);
            if (!$order) {
                return false;
            }

            $order->update_meta_data('_paypal_subscription_last_payment_id', $transaction_id);
            $order->update_meta_data('_paypal_subscription_last_payment_at', gmdate('Y-m-d H:i:s'));
            $order->save();

            if ($order->get_meta('_paypal_subscription_verified_active') === 'yes') {
                $this->process_order_completion($order, $transaction_id);
            }

            $this->refresh_linked_subscription($order, 'paypal_webhook_payment');
            return true;
        }

        $invoice_id = absint($resource['invoice_id'] ?? $resource['custom_id'] ?? 0);
        if ($invoice_id <= 0) {
            return false;
        }

        $order = wc_get_order($invoice_id);
        if (
            !$order instanceof WC_Order
            || $order->get_payment_method() !== $this->gateway->id
            || !empty($order->get_meta('parent_order_id'))
            || Growtype_Wc_Subscription::is_subscription_order($order->get_id())
            || !$this->payment_matches_order($resource, $order)
        ) {
            return false;
        }

        $this->process_order_completion($order, $transaction_id);
        return true;
    }

    protected function handle_subscription_event(array $data): bool
    {
        $event_type = (string) ($data['event_type'] ?? '');
        $resource = is_array($data['resource'] ?? null) ? $data['resource'] : [];
        $subscription_id = sanitize_text_field((string) ($resource['id'] ?? ''));
        if ($subscription_id === '') {
            return false;
        }

        $order = $this->find_order_by_subscription_id($subscription_id);
        if (!$order) {
            return false;
        }

        if ($event_type === 'BILLING.SUBSCRIPTION.ACTIVATED') {
            $access_token = $this->gateway->get_access_token(
                $this->gateway->get_client_id(),
                $this->gateway->get_client_secret()
            );
            if (empty($access_token)) {
                return false;
            }

            $provider_data = $this->gateway->subscriptions->fetch_subscription($access_token, $subscription_id);
            if (is_wp_error($provider_data)) {
                return false;
            }

            $validation = $this->gateway->subscriptions->validate_activation_response(
                $provider_data,
                $subscription_id,
                trim((string) $order->get_meta('paypal_subscription_plan_id')),
                (int) $order->get_id()
            );
            if (is_wp_error($validation)) {
                return false;
            }

            $order->update_meta_data('_paypal_subscription_verified_active', 'yes');
            $order->update_meta_data('_paypal_subscription_verified_at', gmdate('Y-m-d H:i:s'));
            $order->save();
            $this->process_order_completion($order, $subscription_id);
            $this->refresh_linked_subscription($order, 'paypal_webhook_activation', $provider_data);
            return true;
        }

        if ($event_type === 'BILLING.SUBSCRIPTION.PAYMENT.FAILED') {
            $order->update_meta_data('_paypal_subscription_payment_failed_at', gmdate('Y-m-d H:i:s'));
            $order->add_order_note(__('PayPal reported a subscription payment failure.', 'growtype-wc'));
            $order->save();
            $this->refresh_linked_subscription($order, 'paypal_webhook_payment_failed');
            return true;
        }

        return $this->refresh_linked_subscription($order, 'paypal_webhook_status', $resource);
    }

    protected function find_order_by_subscription_id(string $subscription_id)
    {
        $orders = wc_get_orders([
            'limit' => 1,
            'meta_key' => 'paypal_subscription_id',
            'meta_value' => $subscription_id,
        ]);
        $order = !empty($orders) ? $orders[0] : null;

        if (
            !$order instanceof WC_Order
            || $order->get_payment_method() !== $this->gateway->id
            || trim((string) $order->get_meta('paypal_subscription_id')) !== $subscription_id
        ) {
            return null;
        }

        return $order;
    }

    protected function refresh_linked_subscription($order, string $source, ?array $provider_data = null): bool
    {
        $subscriptions = growtype_wc_get_subscriptions([
            'order_id' => $order->get_id(),
            'limit' => 1,
        ]);
        if (empty($subscriptions)) {
            // The activation event can complete the order and create this record;
            // other events should be retried until their linked record exists.
            return false;
        }

        if ($provider_data === null) {
            $access_token = $this->gateway->get_access_token(
                $this->gateway->get_client_id(),
                $this->gateway->get_client_secret()
            );
            if (empty($access_token)) {
                return false;
            }
            $provider_data = $this->gateway->subscriptions->fetch_subscription(
                $access_token,
                trim((string) $order->get_meta('paypal_subscription_id'))
            );
            if (is_wp_error($provider_data)) {
                return false;
            }
        }

        $result = $this->gateway->subscriptions->reconcile_subscription(
            (int) $subscriptions[0]->ID,
            $provider_data,
            null,
            $source
        );

        return ($result['outcome'] ?? '') !== 'ignored';
    }

    protected function payment_matches_order(array $resource, $order): bool
    {
        $amount = $resource['amount']['value'] ?? null;
        $currency = strtoupper((string) ($resource['amount']['currency_code'] ?? $resource['amount']['currency'] ?? ''));
        if ($amount === null || $currency === '') {
            return false;
        }

        return abs((float) $amount - (float) $order->get_total()) < 0.00001
            && $currency === strtoupper((string) $order->get_currency());
    }

    protected function process_order_completion($order, $transaction_id): void
    {
        if (!$order instanceof WC_Order || $order->is_paid()) {
            return;
        }

        if (
            Growtype_Wc_Subscription::is_subscription_order($order->get_id())
            && $order->get_meta('_paypal_subscription_verified_active') !== 'yes'
        ) {
            return;
        }

        $order->payment_complete($transaction_id);
        if ($order->get_status() !== 'completed') {
            $order->update_status('completed', __('Completed after verified PayPal provider event.', 'growtype-wc'));
        }
        $order->add_order_note(sprintf(__('PayPal provider event verified transaction %s.', 'growtype-wc'), $transaction_id));
        $order->save();
    }
}
