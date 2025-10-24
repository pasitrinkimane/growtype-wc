<?php

class Growtype_Wc_Payment
{

    public function __construct()
    {
        $this->load_methods();

        add_action('growtype_wc_before_add_to_cart', [$this, 'handle_disabled_payment'], 10, 6);
        add_action('template_redirect', [$this, 'process_upsell_endpoint']);
    }

    protected function load_methods()
    {
        include_once __DIR__ . '/gateways/Growtype_Wc_Payment_Gateway.php';
        new Growtype_Wc_Payment_Gateway();
    }

    public static function disabled_payment_methods_notice(): string
    {
        $default = 'Due to a high volume of orders, we are temporarily unable to accept new ones.';
        return esc_textarea(get_option('growtype_wc_disabled_payment_methods_notice', $default));
    }

    public static function all_disabled(): bool
    {
        return (bool)get_option('growtype_wc_disable_all_payment_methods', false);
    }

    public static function intent_url($base_url, int $order_id, int $product_id): string
    {
        return add_query_arg([
            'action' => 'gwc_charge_intent',
            'order_id' => $order_id,
            'product_id' => $product_id,
        ], $base_url);
    }

    /**
     * 1) Block add-to-cart when all payments are disabled.
     */
    public function handle_disabled_payment($cart_item_key, $product_id, $qty, $variation_id, $variation_attrs, $cart_item_data)
    {
        if (!self::all_disabled()) {
            return;
        }

        // Only show once
        if (isset($_GET['payment_failed'])) {
            return;
        }

        wc_add_notice(self::disabled_payment_methods_notice(), 'error');

        // Create a failed order to preserve the cart state
        $product = wc_get_product($product_id);
        $order = wc_create_order();
        $order->add_product($product, $qty);

        // Set payment method from URL (if any)
        $method_key = sanitize_text_field($_GET['payment_method'] ?? '');
        $provider = str_replace('gwc-', '', $method_key);
        $class_name = "Growtype_Wc_Payment_Gateway_{$provider}";
        if (class_exists($class_name)) {
            $pm = $class_name::PROVIDER_ID;
            $order->set_payment_method($pm);
        }

        // Copy coupons
        foreach (WC()->cart->get_applied_coupons() as $coupon) {
            $order->apply_coupon($coupon);
        }

        $order->calculate_totals();

        if (is_user_logged_in()) {
            $order->set_customer_id(get_current_user_id());
        }

        $order->update_status('failed');
        $order->save();

        WC()->cart->empty_cart();

        $referer = remove_query_arg(['add-to-cart', 'payment_method'], wp_get_referer());
        $redirect_to = $referer ?: wc_get_checkout_url();
        $redirect_url = add_query_arg('payment_failed', '1', $redirect_to);

        // Prevent infinite loops
        remove_all_actions('woocommerce_add_to_cart');

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * 4) On template_redirect, handle our upsell endpoint.
     */
    public function process_upsell_endpoint()
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'gwc_charge_intent') {
            return;
        }

        $order_id = absint($_GET['order_id'] ?? 0);
        $product_id = absint($_GET['product_id'] ?? 0);

        if (!$order_id || !$product_id) {
            error_log('Upsell endpoint error: Missing order or product ID.');
            wp_die('Invalid parameters.', 'Error', ['response' => 400]);
        }

        try {
            $order = wc_get_order($order_id);

            $product = wc_get_product($product_id);

            if (!$order || !$product) {
                throw new \Exception('Order or product not found.');
            }

            /** @var Growtype_Wc_Payment_Gateway_Stripe $gateway */
            $gateway = WC()->payment_gateways()->payment_gateways()[$order->get_payment_method()];

            $description = sprintf('Upsell #%d for Order #%d', $product_id, $order_id);

            $pi = $gateway->charge_intent($order_id, $product_id, $description);

            if ($pi->status !== 'succeeded') {
                throw new \Exception('Stripe PaymentIntent status: ' . $pi->status);
            }

            // Redirect back to order received (or wherever)
            wp_safe_redirect($gateway->get_return_url($order));
            exit;

        } catch (\Exception $e) {
            error_log('Upsell endpoint error: ' . $e->getMessage());
            wp_die('Upsell charge failed: ' . esc_html($e->getMessage()), 'Error', ['response' => 500]);
        }
    }
}
