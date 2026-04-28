<?php

/**
 * Stripe Payment Identification Fixes.
 *
 * Orders created via the Stripe PaymentIntent flow (express checkout modal) are
 * often saved with an empty or wrong _payment_method because WooCommerce's
 * standard checkout form — which writes that field — is bypassed.
 */
class Growtype_Wc_Payment_Gateway_Stripe_Identification
{
    /** @var Growtype_Wc_Payment_Gateway_Stripe */
    private $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;

        /**
         * Gateway identification fixes.
         *
         * Orders created via the Stripe PaymentIntent flow (express checkout modal) are
         * often saved with an empty or wrong _payment_method because WooCommerce's
         * standard checkout form — which writes that field — is bypassed.
         *
         * Fix 1 – stamp _payment_method at order creation from the URL query arg
         *          (the fallback checkout URL already carries ?payment_method=gwc-stripe).
         * Fix 2 – stamp the exact wallet type (Apple Pay / Google Pay / card) from
         *          Stripe order meta right after payment is marked complete.
         * Fix 3 – for any failed/pending order that still has an empty _payment_method,
         *          resolve it from Stripe meta whenever the status changes.
         *
         * Together these eliminate the "Unknown" gateway bucket in the analytics report.
         */
        add_action('woocommerce_checkout_order_created', [$this, 'stamp_payment_method_from_request'], 5, 1);
        add_action('woocommerce_payment_complete', [$this, 'stamp_stripe_wallet_type_on_complete'], 5, 1);
        add_action('woocommerce_order_status_changed', [$this, 'stamp_payment_method_on_status_change'], 10, 4);

        /**
         * AJAX endpoint called by stripe-v2.js immediately when a wallet payment
         * method is selected. Stamps _stripe_payment_type on the WC order that
         * belongs to the given PaymentIntent so the gateway is never "Unknown".
         * Works for both success and failure paths because it fires before confirmPayment.
         */
        add_action('wp_ajax_growtype_wc_stamp_payment_type', [$this, 'ajax_stamp_payment_type']);
        add_action('wp_ajax_nopriv_growtype_wc_stamp_payment_type', [$this, 'ajax_stamp_payment_type']);
    }

    /**
     * Fix 1 – Stamp the _payment_method as early as possible (at order creation)
     * if the request carries a ?payment_method=gwc-stripe hint.
     */
    public function stamp_payment_method_from_request($order)
    {
        if (!$order instanceof WC_Abstract_Order) {
            return;
        }

        // Already set by WooCommerce — don't overwrite.
        if ($order->get_payment_method()) {
            return;
        }

        $method_from_url = sanitize_key(wp_unslash($_REQUEST['payment_method'] ?? ''));
        if (empty($method_from_url)) {
            return;
        }

        $order->set_payment_method($method_from_url);
        $order->save();

        error_log(sprintf(
            '[GWT Payment Stripe] Stamped _payment_method="%s" on new order #%d from request param.',
            $method_from_url,
            $order->get_id()
        ));
    }

    /**
     * Fix 2 – After payment_complete, resolve the precise Stripe wallet type
     * (Apple Pay, Google Pay, card, …) from the _stripe_intent_payment_method_type
     * or _stripe_payment_method_id meta that the Growtype Stripe gateway writes,
     * and overwrite _payment_method with a human-readable, analytics-friendly value.
     */
    public function stamp_stripe_wallet_type_on_complete($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $resolved = $this->resolve_stripe_payment_method_label($order);
        if (empty($resolved)) {
            return;
        }

        $current = $order->get_payment_method();

        // Avoid stamping a vaguer label over a more specific existing one.
        if (!empty($current) && $current !== 'gwc-stripe' && $current !== 'Unknown') {
            return;
        }

        $order->set_payment_method($resolved);
        $order->set_payment_method_title($this->payment_method_label_to_title($resolved));
        $order->save();

        error_log(sprintf(
            '[GWT Payment Stripe] Resolved wallet type "%s" on completed order #%d.',
            $resolved,
            $order_id
        ));
    }

    /**
     * Fix 3 – For pending/failed orders that still have no _payment_method,
     * try to resolve it from Stripe meta whenever the order status changes.
     * This catches abandoned PaymentIntent orders that never hit payment_complete.
     */
    public function stamp_payment_method_on_status_change($order_id, $old_status, $new_status, $order)
    {
        if (!$order instanceof WC_Abstract_Order) {
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            return;
        }

        // Only act on terminal failure/pending states — not on every transition.
        $target_statuses = ['failed', 'cancelled', 'pending'];
        if (!in_array($new_status, $target_statuses, true)) {
            return;
        }

        // Already has a meaningful gateway — nothing to do.
        $current = $order->get_payment_method();
        if (!empty($current) && $current !== 'Unknown') {
            return;
        }

        $resolved = $this->resolve_stripe_payment_method_label($order);
        if (empty($resolved)) {
            // Last resort: if the order has a Stripe intent at all, mark it as gwc-stripe
            // so it stops appearing as "Unknown" in the analytics failure segments.
            $has_intent = $order->get_meta('_stripe_payment_intent')
                ?: $order->get_meta('_stripe_source_id')
                    ?: $order->get_meta('_transaction_id');

            if (!empty($has_intent)) {
                $resolved = 'gwc-stripe';
            }
        }

        if (empty($resolved)) {
            return;
        }

        $order->set_payment_method($resolved);
        $order->set_payment_method_title($this->payment_method_label_to_title($resolved));
        $order->save();

        error_log(sprintf(
            '[GWT Payment Stripe] Stamped payment method "%s" on %s order #%d (was empty/Unknown).',
            $resolved,
            $new_status,
            $order_id
        ));
    }

    /**
     * AJAX: stamp _stripe_payment_type on the WC order matching a given PaymentIntent ID.
     * Called fire-and-forget from stripe-v2.js at the moment the wallet sheet appears.
     */
    public function ajax_stamp_payment_type()
    {
        $intent_id = sanitize_text_field(wp_unslash($_POST['intent_id'] ?? ''));
        $wallet_type = sanitize_key(wp_unslash($_POST['wallet_type'] ?? ''));

        if (empty($intent_id) || strpos($intent_id, 'pi_') !== 0) {
            wp_send_json_error(['message' => 'Invalid intent_id.'], 400);
        }

        if (empty($wallet_type)) {
            wp_send_json_error(['message' => 'Missing wallet_type.'], 400);
        }

        // Find WC order by _stripe_payment_intent meta.
        $orders = wc_get_orders([
            'limit' => 1,
            'meta_key' => '_stripe_payment_intent',
            'meta_value' => $intent_id,
        ]);

        if (empty($orders)) {
            wp_send_json_success(['message' => 'Order not found yet, will be stamped on status change.']);
        }

        $order = $orders[0];

        // Stamp the raw wallet type for resolve_stripe_payment_method_label() to read.
        $order->update_meta_data('_stripe_payment_type', $wallet_type);

        // Also resolve and stamp _payment_method immediately.
        $label = $this->map_stripe_type_to_gateway_label($wallet_type);
        if (!empty($label)) {
            $order->set_payment_method($label);
            $order->set_payment_method_title($this->payment_method_label_to_title($label));
        }

        $order->save();

        error_log(sprintf(
            '[GWT Payment Stripe] AJAX stamped wallet_type="%s" (%s) on order #%d (PI: %s).',
            $wallet_type,
            $label,
            $order->get_id(),
            $intent_id
        ));

        wp_send_json_success(['stamped' => true, 'order_id' => $order->get_id(), 'label' => $label]);
    }

    /**
     * Read Stripe-specific order meta written by the Growtype Stripe gateway and
     * return a normalised payment method key.
     */
    private function resolve_stripe_payment_method_label($order)
    {
        if (!$order instanceof WC_Abstract_Order) {
            return '';
        }

        // Priority 1: explicit wallet type stamped by the gateway or our JS
        $type = $order->get_meta('_stripe_intent_payment_method_type')
            ?: $order->get_meta('_stripe_payment_type')
                ?: '';

        if (!empty($type)) {
            return $this->map_stripe_type_to_gateway_label($type);
        }

        // Priority 2: infer from PaymentMethod ID prefix (pm_card_*, pm_apple_pay, …)
        $pm_id = $order->get_meta('_stripe_payment_method_id') ?: '';
        if (!empty($pm_id)) {
            if (stripos($pm_id, 'apple') !== false) {
                return 'Growtype WC - Stripe (Apple_pay)';
            }
            if (stripos($pm_id, 'google') !== false) {
                return 'Growtype WC - Stripe (Google_pay)';
            }
            if (strpos($pm_id, 'pm_') === 0) {
                return 'Growtype WC - Stripe (Card)';
            }
        }

        // Priority 3: any Stripe transaction/intent meta → at least mark as gwc-stripe
        $has_stripe = $order->get_meta('_stripe_payment_intent')
            ?: $order->get_meta('_stripe_source_id')
                ?: $order->get_meta('_transaction_id')
                    ?: '';

        if (!empty($has_stripe) && strpos((string)$has_stripe, 'pi_') === 0) {
            return 'gwc-stripe';
        }

        return '';
    }

    /**
     * Map a Stripe payment_method_type string to our gateway label convention.
     */
    private function map_stripe_type_to_gateway_label($type)
    {
        $type = strtolower(trim((string)$type));

        $map = [
            'apple_pay' => 'Growtype WC - Stripe (Apple_pay)',
            'applepay' => 'Growtype WC - Stripe (Apple_pay)',
            'google_pay' => 'Growtype WC - Stripe (Google_pay)',
            'googlepay' => 'Growtype WC - Stripe (Google_pay)',
            'card' => 'Growtype WC - Stripe (Card)',
            'card_present' => 'Growtype WC - Stripe (Card)',
        ];

        return $map[$type] ?? 'gwc-stripe';
    }

    /**
     * Human-readable title for a gateway key, shown in WooCommerce order screens.
     */
    private function payment_method_label_to_title($label)
    {
        $titles = [
            'Growtype WC - Stripe (Apple_pay)' => 'Apple Pay (Stripe)',
            'Growtype WC - Stripe (Google_pay)' => 'Google Pay (Stripe)',
            'Growtype WC - Stripe (Card)' => 'Credit / Debit Card (Stripe)',
            'gwc-stripe' => 'Stripe',
        ];

        return $titles[$label] ?? $label;
    }
}
