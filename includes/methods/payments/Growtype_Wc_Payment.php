<?php

class Growtype_Wc_Payment
{
    const CHARGE_INTENT_ACTION = 'gwc_charge_intent';
    const CHARGE_INTENT_NONCE_ACTION = 'growtype_wc_charge_intent';
    const RETURN_URL_QUERY_ARG = 'growtype_return_after_payment_url';
    const REMOVE_SAVED_METHOD_QUERY_ARG = 'growtype_wc_remove_saved_method';
    const REMOVED_REPEAT_PURCHASE_METHODS_META_KEY = 'growtype_wc_removed_repeat_purchase_methods';

    public function __construct()
    {
        $this->load_methods();

        add_action('growtype_wc_before_add_to_cart', [$this, 'handle_disabled_payment'], 10, 6);
        add_action('template_redirect', [$this, 'process_upsell_endpoint']);
        add_action('template_redirect', [$this, 'process_remove_saved_payment_method'], 1);
        add_filter('woocommerce_saved_payment_methods_list', [$this, 'extend_saved_payment_methods_list'], 10, 2);
        add_filter('woocommerce_available_payment_gateways', [$this, 'filter_available_gateways_for_add_payment_method'], 20);

        add_action('wp_ajax_growtype_wc_create_payment_intent', [$this, 'ajax_create_payment_intent']);
        add_action('wp_ajax_nopriv_growtype_wc_create_payment_intent', [$this, 'ajax_create_payment_intent']);

        add_action('wp_ajax_growtype_wc_get_payment_info', [$this, 'ajax_get_payment_info']);
        add_action('wp_ajax_nopriv_growtype_wc_get_payment_info', [$this, 'ajax_get_payment_info']);

        add_action('wp_ajax_growtype_wc_finalize_order', [$this, 'ajax_finalize_order']);
        add_action('wp_ajax_nopriv_growtype_wc_finalize_order', [$this, 'ajax_finalize_order']);

        add_action('wp_footer', [$this, 'render_upsell_error_alert']);
    }

    protected function load_methods()
    {
        include_once __DIR__ . '/gateways/Growtype_Wc_Payment_Gateway.php';
        new Growtype_Wc_Payment_Gateway();
    }

    /**
     * Show a JS alert when an upsell charge fails and the user is redirected back.
     * The alert includes the error message and a link to update their payment method.
     */
    public function render_upsell_error_alert()
    {
        if (empty($_GET['upsell_failed'])) {
            return;
        }

        $error_msg = sanitize_text_field(urldecode($_GET['upsell_error'] ?? 'Payment failed. Please try again.'));
        $payment_methods_url = '/my-account/payment-methods/';
        ?>
        <style>
        #gwc-upsell-error-alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 99999;
            min-width: 320px;
            max-width: 520px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0,0,0,.18);
            border-radius: 10px;
            padding: 16px 20px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #664d03;
            font-size: 15px;
            line-height: 1.5;
            animation: gwc-slide-down .35s ease;
        }
        @keyframes gwc-slide-down {
            from { opacity: 0; top: 0; }
            to   { opacity: 1; top: 20px; }
        }
        #gwc-upsell-error-alert .gwc-alert-close {
            float: right;
            background: none;
            border: none;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            color: #664d03;
            margin-left: 12px;
        }
        #gwc-upsell-error-alert a {
            color: #0d6efd;
            font-weight: 600;
        }
        </style>
        <script>
        (function () {
            var cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('upsell_failed');
            cleanUrl.searchParams.delete('upsell_error');
            window.history.replaceState({}, '', cleanUrl.toString());

            var msg  = <?php echo wp_json_encode($error_msg); ?>;
            var link = <?php echo wp_json_encode($payment_methods_url); ?>;

            var el = document.createElement('div');
            el.id = 'gwc-upsell-error-alert';
            el.innerHTML =
                '<button class="gwc-alert-close" aria-label="Close">&times;</button>' +
                '<strong>Payment failed:</strong> ' + msg +
                '<br><br>Please <a href="' + link + '">update your payment method</a> to continue.';

            document.body.appendChild(el);

            el.querySelector('.gwc-alert-close').addEventListener('click', function () {
                el.remove();
            });

            // Auto-dismiss after 8s
            setTimeout(function () { if (el.parentNode) el.remove(); }, 8000);
        })();
        </script>
        <?php
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
        $url = add_query_arg([
            'action' => self::CHARGE_INTENT_ACTION,
            'order_id' => $order_id,
            'product_id' => $product_id,
        ], $base_url);

        return wp_nonce_url($url, self::CHARGE_INTENT_NONCE_ACTION);
    }

    public static function get_saved_payment_charge_url(int $product_id, string $return_url = '', ?int $user_id = null): string
    {
        return self::get_repeat_purchase_url($product_id, $return_url, $user_id);
    }

    public static function get_repeat_purchase_url(int $product_id, string $return_url = '', ?int $user_id = null): string
    {
        return self::get_repeat_purchase_url_for_provider($product_id, null, $return_url, $user_id);
    }

    public static function get_repeat_purchase_url_for_provider(int $product_id, ?string $provider_id = null, string $return_url = '', ?int $user_id = null): string
    {
        $user_id = $user_id ?: get_current_user_id();

        if ($user_id < 1 || $product_id < 1) {
            return '';
        }

        $order = self::get_latest_repeat_purchase_order($user_id, $provider_id);

        if (!$order) {
            return '';
        }

        $url = add_query_arg([
            'action' => self::CHARGE_INTENT_ACTION,
            'order_id' => $order->get_id(),
            'product_id' => $product_id,
        ], home_url('/'));

        $return_url = self::sanitize_return_url($return_url);
        if (!empty($return_url)) {
            $url = add_query_arg([
                self::RETURN_URL_QUERY_ARG => rawurlencode($return_url),
            ], $url);
        }

        $url = wp_nonce_url($url, self::CHARGE_INTENT_NONCE_ACTION);

        return apply_filters('growtype_wc_payment_repeat_purchase_url', $url, $product_id, $return_url, $user_id);
    }

    public static function user_can_charge_with_saved_payment(?int $user_id = null): bool
    {
        return self::user_can_repeat_purchase($user_id);
    }

    public static function user_can_repeat_purchase(?int $user_id = null): bool
    {
        return self::user_can_repeat_purchase_for_provider(null, $user_id);
    }

    public static function user_can_repeat_purchase_for_provider(?string $provider_id = null, ?int $user_id = null): bool
    {
        $user_id = $user_id ?: get_current_user_id();

        if ($user_id < 1) {
            return false;
        }

        $order = self::get_latest_repeat_purchase_order($user_id, $provider_id);

        return self::order_supports_repeat_purchase($order);
    }

    public static function get_latest_saved_payment_order(?int $user_id = null)
    {
        $order = self::get_latest_repeat_purchase_order($user_id, Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID);

        return self::order_supports_saved_payment_charge($order) ? $order : null;
    }

    public static function get_latest_repeat_purchase_order(?int $user_id = null, ?string $provider_id = null)
    {
        static $cache = [];

        $user_id = $user_id ?: get_current_user_id();
        $cache_key = $user_id . '|' . ($provider_id ?? 'all');

        if ($user_id < 1) {
            return null;
        }

        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        $orders = wc_get_orders([
            'customer' => $user_id,
            'limit' => 25,
            'status' => wc_get_is_paid_statuses(),
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);

        foreach ($orders as $order) {
            if (!$order) {
                continue;
            }

            if (!empty($provider_id) && $order->get_payment_method() !== $provider_id) {
                continue;
            }

            if (self::is_repeat_purchase_order_removed($order, $user_id)) {
                continue;
            }

            if (self::order_supports_repeat_purchase($order)) {
                $cache[$cache_key] = $order;
                return $cache[$cache_key];
            }
        }

        $cache[$cache_key] = null;

        return null;
    }

    public function extend_saved_payment_methods_list($saved_methods, $user_id): array
    {
        if (!is_account_page() || !is_wc_endpoint_url('payment-methods')) {
            return is_array($saved_methods) ? $saved_methods : [];
        }

        if (!is_array($saved_methods)) {
            $saved_methods = [];
        }

        $bridged_methods = self::get_repeat_purchase_saved_methods($user_id);

        foreach ($bridged_methods as $type => $methods) {
            if (!isset($saved_methods[$type]) || !is_array($saved_methods[$type])) {
                $saved_methods[$type] = [];
            }

            foreach ($methods as $method_key => $method) {
                if (!isset($saved_methods[$type][$method_key])) {
                    $saved_methods[$type][$method_key] = $method;
                }
            }
        }

        return $saved_methods;
    }

    public function filter_available_gateways_for_add_payment_method($available_gateways)
    {
        if (!is_array($available_gateways) || !is_account_page() || !is_wc_endpoint_url('add-payment-method')) {
            return $available_gateways;
        }

        foreach ($available_gateways as $gateway_id => $gateway) {
            if ($gateway_id !== Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID) {
                unset($available_gateways[$gateway_id]);
            }
        }

        return $available_gateways;
    }

    public static function get_repeat_purchase_saved_methods(?int $user_id = null): array
    {
        $user_id = $user_id ?: get_current_user_id();

        if ($user_id < 1) {
            return [];
        }

        $saved_methods = [];

        $stripe_order = self::get_latest_repeat_purchase_order($user_id, Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID);
        if (self::order_supports_saved_payment_charge($stripe_order)) {
            $stripe_method = self::build_saved_stripe_method($stripe_order);

            if (!empty($stripe_method)) {
                $saved_methods['cc']['growtype-stripe-' . $stripe_order->get_id()] = $stripe_method;
            }
        }

        $paypal_order = self::get_latest_repeat_purchase_order($user_id, Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID);
        if ($paypal_order instanceof WC_Order && $paypal_order->get_payment_method() === Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID) {
            $paypal_method = self::build_saved_paypal_method($paypal_order, $user_id);

            if (!empty($paypal_method)) {
                $saved_methods['paypal']['growtype-paypal-' . $paypal_order->get_id()] = $paypal_method;
            }
        }

        return $saved_methods;
    }

    protected static function build_saved_stripe_method(WC_Order $order): array
    {
        $details = self::get_saved_stripe_method_details($order);
        $expires = __('Saved', 'growtype-wc');

        if (!empty($details['exp_month']) && !empty($details['exp_year'])) {
            $expires = sprintf('%02d / %s', absint($details['exp_month']), substr((string)$details['exp_year'], -2));
        }

        return [
            'method' => [
                'brand' => $details['brand'] ?: 'card',
                'last4' => $details['last4'] ?? '',
            ],
            'expires' => $expires,
            'actions' => [
                'delete' => [
                    'url' => self::get_remove_saved_method_url($order),
                    'name' => __('Remove', 'growtype-wc'),
                ],
            ],
            'is_default' => true,
            'gateway' => Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID,
            'growtype_saved_method_source' => 'repeat_purchase',
        ];
    }

    protected static function build_saved_paypal_method(WC_Order $order, int $user_id): array
    {
        $email = $order->get_billing_email();

        if (empty($email)) {
            $user = get_userdata($user_id);
            $email = $user->user_email ?? '';
        }

        $label = !empty($email)
            ? sprintf(__('PayPal (%s)', 'growtype-wc'), $email)
            : __('PayPal account', 'growtype-wc');

        return [
            'method' => [
                'brand' => $label,
            ],
            'expires' => __('Available', 'growtype-wc'),
            'actions' => [
                'delete' => [
                    'url' => self::get_remove_saved_method_url($order),
                    'name' => __('Remove', 'growtype-wc'),
                ],
            ],
            'is_default' => false,
            'gateway' => Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID,
            'growtype_saved_method_source' => 'repeat_purchase',
        ];
    }

    protected static function get_saved_stripe_method_details(WC_Order $order): array
    {
        $details = [
            'brand' => $order->get_meta('stripe_card_brand'),
            'last4' => $order->get_meta('stripe_card_last4'),
            'exp_month' => $order->get_meta('stripe_card_exp_month'),
            'exp_year' => $order->get_meta('stripe_card_exp_year'),
        ];

        if (!empty($details['brand']) || empty($order->get_meta('stripe_payment_method_id'))) {
            return $details;
        }

        $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        $stripe_gateway = $gateways[Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID] ?? null;

        if (!$stripe_gateway || !method_exists($stripe_gateway, 'get_secret_key')) {
            return $details;
        }

        try {
            $stripe = new \Stripe\StripeClient($stripe_gateway->get_secret_key());
            $payment_method = $stripe->paymentMethods->retrieve($order->get_meta('stripe_payment_method_id'));

            if (!empty($payment_method->card)) {
                $details = [
                    'brand' => $payment_method->card->brand ?? '',
                    'last4' => $payment_method->card->last4 ?? '',
                    'exp_month' => $payment_method->card->exp_month ?? '',
                    'exp_year' => $payment_method->card->exp_year ?? '',
                ];

                $order->update_meta_data('stripe_card_brand', $details['brand']);
                $order->update_meta_data('stripe_card_last4', $details['last4']);
                $order->update_meta_data('stripe_card_exp_month', $details['exp_month']);
                $order->update_meta_data('stripe_card_exp_year', $details['exp_year']);
                $order->save();
            }
        } catch (\Throwable $e) {
            error_log('growtype_wc_saved_payment_method_bridge_error: ' . $e->getMessage());
        }

        return $details;
    }

    public static function persist_stripe_display_details_from_payment_intent(WC_Order $order, $payment_intent): void
    {
        if (!$order instanceof WC_Order || empty($payment_intent)) {
            return;
        }

        $card = null;

        if (isset($payment_intent->charges->data[0]->payment_method_details->card)) {
            $card = $payment_intent->charges->data[0]->payment_method_details->card;
        } elseif (isset($payment_intent->payment_method_details->card)) {
            $card = $payment_intent->payment_method_details->card;
        }

        if (empty($card)) {
            return;
        }

        if (isset($card->brand)) {
            $order->update_meta_data('stripe_card_brand', $card->brand);
        }

        if (isset($card->last4)) {
            $order->update_meta_data('stripe_card_last4', $card->last4);
        }

        if (isset($card->exp_month)) {
            $order->update_meta_data('stripe_card_exp_month', $card->exp_month);
        }

        if (isset($card->exp_year)) {
            $order->update_meta_data('stripe_card_exp_year', $card->exp_year);
        }
    }

    protected static function get_remove_saved_method_url(WC_Order $order): string
    {
        $url = add_query_arg([
            self::REMOVE_SAVED_METHOD_QUERY_ARG => 1,
            'provider' => $order->get_payment_method(),
            'order_id' => $order->get_id(),
        ], wc_get_endpoint_url('payment-methods'));

        return wp_nonce_url($url, 'growtype_wc_remove_saved_method');
    }

    protected static function get_removed_repeat_purchase_methods(?int $user_id = null): array
    {
        $user_id = $user_id ?: get_current_user_id();

        if ($user_id < 1) {
            return [];
        }

        $removed_methods = get_user_meta($user_id, self::REMOVED_REPEAT_PURCHASE_METHODS_META_KEY, true);

        return is_array($removed_methods) ? $removed_methods : [];
    }

    protected static function mark_repeat_purchase_method_removed(int $user_id, string $provider_id, int $order_id): void
    {
        if ($user_id < 1 || empty($provider_id) || $order_id < 1) {
            return;
        }

        $removed_methods = self::get_removed_repeat_purchase_methods($user_id);
        $current_removed_order_id = absint($removed_methods[$provider_id] ?? 0);

        if ($order_id > $current_removed_order_id) {
            $removed_methods[$provider_id] = $order_id;
            update_user_meta($user_id, self::REMOVED_REPEAT_PURCHASE_METHODS_META_KEY, $removed_methods);
        }
    }

    protected static function is_repeat_purchase_order_removed($order, ?int $user_id = null): bool
    {
        if (!$order instanceof WC_Order) {
            return false;
        }

        $user_id = $user_id ?: (int)$order->get_customer_id();
        if ($user_id < 1) {
            return false;
        }

        $removed_methods = self::get_removed_repeat_purchase_methods($user_id);
        $removed_after_order_id = absint($removed_methods[$order->get_payment_method()] ?? 0);

        return $removed_after_order_id > 0 && $order->get_id() <= $removed_after_order_id;
    }

    public function process_remove_saved_payment_method(): void
    {
        if (!is_user_logged_in() || !is_account_page() || !is_wc_endpoint_url('payment-methods')) {
            return;
        }

        if (!isset($_GET[self::REMOVE_SAVED_METHOD_QUERY_ARG])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'growtype_wc_remove_saved_method')) {
            wc_add_notice(__('Unable to remove payment method. Please try again.', 'growtype-wc'), 'error');
            wp_safe_redirect(wc_get_endpoint_url('payment-methods'));
            exit;
        }

        $user_id = get_current_user_id();
        $order_id = absint($_GET['order_id'] ?? 0);
        $provider_id = sanitize_text_field(wp_unslash($_GET['provider'] ?? ''));
        $order = wc_get_order($order_id);

        if (!$order instanceof WC_Order || (int)$order->get_customer_id() !== $user_id || $order->get_payment_method() !== $provider_id) {
            wc_add_notice(__('Payment method not found.', 'growtype-wc'), 'error');
            wp_safe_redirect(wc_get_endpoint_url('payment-methods'));
            exit;
        }

        self::mark_repeat_purchase_method_removed($user_id, $provider_id, $order_id);

        wc_add_notice(__('Payment method removed.', 'growtype-wc'));
        wp_safe_redirect(remove_query_arg([self::REMOVE_SAVED_METHOD_QUERY_ARG, 'provider', 'order_id', '_wpnonce'], wc_get_endpoint_url('payment-methods')));
        exit;
    }

    public static function order_supports_saved_payment_charge($order): bool
    {
        if (!$order instanceof WC_Order) {
            return false;
        }

        if ($order->get_payment_method() !== Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID) {
            return false;
        }

        $customer_id = $order->get_meta('stripe_customer_id');
        $payment_method_id = $order->get_meta('stripe_payment_method_id');

        return !empty($customer_id) && !empty($payment_method_id);
    }

    public static function order_supports_repeat_purchase($order): bool
    {
        if (!$order instanceof WC_Order) {
            return false;
        }

        if (self::order_supports_saved_payment_charge($order)) {
            return true;
        }

        if ($order->get_payment_method() === Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID) {
            return true;
        }

        return false;
    }

    public static function sanitize_return_url(string $return_url): string
    {
        $return_url = esc_url_raw(wp_unslash($return_url));

        if (empty($return_url)) {
            return '';
        }

        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $return_host = wp_parse_url($return_url, PHP_URL_HOST);
        $return_path = wp_parse_url($return_url, PHP_URL_PATH);

        if ((!empty($return_host) && !empty($site_host) && strtolower($return_host) === strtolower($site_host)) &&
            (empty($return_path) || false === strpos($return_path, '/wp-admin'))) {
            return $return_url;
        }

        return '';
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
        if (!isset($_GET['action']) || $_GET['action'] !== self::CHARGE_INTENT_ACTION) {
            return;
        }

        if (!is_user_logged_in()) {
            wp_die('Unauthorized.', 'Error', ['response' => 403]);
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(wp_unslash($_GET['_wpnonce']), self::CHARGE_INTENT_NONCE_ACTION)) {
            wp_die('Invalid nonce.', 'Error', ['response' => 403]);
        }

        $order_id = absint($_GET['order_id'] ?? 0);
        $product_id = absint($_GET['product_id'] ?? 0);
        $explicit_return_url = self::sanitize_return_url($_GET[self::RETURN_URL_QUERY_ARG] ?? '');

        if (!$order_id || !$product_id) {
            error_log('Upsell endpoint error: Missing order or product ID.');
            wp_die('Invalid parameters.', 'Error', ['response' => 400]);
        }

        // Prevent overlapping double clicks while the current charge is in flight.
        $lock_key = $this->instant_charge_lock_key($order_id, $product_id, get_current_user_id());

        if (!$this->acquire_instant_charge_lock($lock_key)) {
            error_log('Upsell in-flight double-click prevented: Order ' . $order_id . ', Product ' . $product_id);

            $current_url = !empty($explicit_return_url)
                ? $explicit_return_url
                : remove_query_arg(['action', 'order_id', 'product_id', '_wpnonce']);

            wp_safe_redirect($current_url);
            exit;
        }

        try {
            $order = wc_get_order($order_id);

            $product = wc_get_product($product_id);

            if (!$order || !$product) {
                $this->release_instant_charge_lock($lock_key);
                throw new \Exception('Order or product not found.');
            }

            if ((int)$order->get_customer_id() !== (int)get_current_user_id()) {
                $this->release_instant_charge_lock($lock_key);
                throw new \Exception('Order does not belong to current user.');
            }

            $charge_source_order = $this->resolve_charge_source_order($order);

            if (!$charge_source_order) {
                $this->release_instant_charge_lock($lock_key);
                throw new \Exception('No reusable payment source order found.');
            }

            $gateway = WC()->payment_gateways()->payment_gateways()[$charge_source_order->get_payment_method()] ?? null;

            if (!$gateway) {
                $this->release_instant_charge_lock($lock_key);
                throw new \Exception('Payment gateway is unavailable.');
            }

            if (!method_exists($gateway, 'charge_intent')) {
                $this->release_instant_charge_lock($lock_key);
                wp_safe_redirect($this->build_gateway_checkout_fallback_url($product_id, $charge_source_order->get_payment_method(), $explicit_return_url));
                exit;
            }

            $description = sprintf('Upsell #%d for Order #%d', $product_id, $charge_source_order->get_id());

            $charge_results = $gateway->charge_intent($charge_source_order->get_id(), $product_id, $description);
            $pi = is_array($charge_results) ? ($charge_results['pi'] ?? null) : $charge_results;
            $new_order_id = is_array($charge_results) ? ($charge_results['order_id'] ?? 0) : 0;

            if (!is_object($pi) || !isset($pi->status) || $pi->status !== 'succeeded') {
                $this->release_instant_charge_lock($lock_key);
                $status = is_object($pi) && isset($pi->status) ? $pi->status : 'unknown';
                throw new \Exception('Payment Intent status: ' . $status);
            }

            $new_order = $new_order_id ? wc_get_order($new_order_id) : $order;
            $redirect_url = !empty($explicit_return_url) ? $explicit_return_url : $gateway->get_return_url($new_order);

            /**
             * Determine next upsell
             */
            if (empty($explicit_return_url) && class_exists('Growtype_Wc_Upsell_Catalog')) {
                $upsells = Growtype_Wc_Upsell_Catalog::get();
                $current_product_slug = $product->get_slug();

                $current_index = -1;
                foreach ($upsells as $index => $u) {
                    if ($u['slug'] === $current_product_slug) {
                        $current_index = $index;
                        break;
                    }
                }

                $next_slug = '';
                if ($current_index !== -1 && isset($upsells[$current_index + 1])) {
                    $next_slug = $upsells[$current_index + 1]['slug'];
                }

                if ($next_slug) {
                    $redirect_url = add_query_arg('upsell', $next_slug, $redirect_url);
                } else {
                    $redirect_url = remove_query_arg('upsell', $redirect_url);
                }
            }

            $this->refresh_instant_charge_lock($lock_key, 10);

            // Redirect back to order received (or wherever)
            wp_safe_redirect($redirect_url);
            exit;

        } catch (\Exception $e) {
            $this->release_instant_charge_lock($lock_key);
            error_log('Upsell endpoint error: ' . $e->getMessage());

            $back_url = !empty($explicit_return_url)
                ? $explicit_return_url
                : (wp_get_referer() ?: home_url('/'));

            $back_url = remove_query_arg(['action', 'order_id', 'product_id', '_wpnonce', self::RETURN_URL_QUERY_ARG], $back_url);
            $back_url = add_query_arg([
                'upsell_error'  => rawurlencode($e->getMessage()),
                'upsell_failed' => '1',
            ], $back_url);

            wp_safe_redirect($back_url);
            exit;
        }
    }

    protected function instant_charge_lock_key(int $order_id, int $product_id, int $user_id): string
    {
        return 'gwc_instant_charge_lock_' . md5($order_id . '|' . $product_id . '|' . $user_id);
    }

    protected function acquire_instant_charge_lock(string $lock_key, int $ttl = 10): bool
    {
        $expires_at = time() + $ttl;
        $added = add_option($lock_key, $expires_at, '', false);

        if ($added) {
            return true;
        }

        $existing = (int)get_option($lock_key, 0);

        if ($existing > 0 && $existing < time()) {
            delete_option($lock_key);
            return add_option($lock_key, $expires_at, '', false);
        }

        return false;
    }

    protected function refresh_instant_charge_lock(string $lock_key, int $ttl = 10): void
    {
        update_option($lock_key, time() + $ttl, false);
    }

    protected function release_instant_charge_lock(string $lock_key): void
    {
        delete_option($lock_key);
    }

    protected function resolve_charge_source_order($requested_order)
    {
        if (!$requested_order instanceof WC_Order) {
            return null;
        }

        if ($requested_order->get_payment_method() === Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID) {
            if (self::order_supports_saved_payment_charge($requested_order)) {
                return $requested_order;
            }

            $latest_saved_order = self::get_latest_saved_payment_order((int)$requested_order->get_customer_id());
            if (self::order_supports_saved_payment_charge($latest_saved_order)) {
                return $latest_saved_order;
            }

            return null;
        }

        if ($requested_order->get_payment_method() === Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID) {
            if (self::order_supports_repeat_purchase($requested_order)) {
                return $requested_order;
            }

            $latest_repeat_order = self::get_latest_repeat_purchase_order((int)$requested_order->get_customer_id(), Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID);
            if (self::order_supports_repeat_purchase($latest_repeat_order)) {
                return $latest_repeat_order;
            }

            return null;
        }

        return $requested_order;
    }

    protected function build_gateway_checkout_fallback_url(int $product_id, string $payment_method, string $return_url = ''): string
    {
        $payment_method_key = $this->payment_method_provider_id_to_key($payment_method);

        $args = [
            'add-to-cart' => $product_id,
        ];

        if (!empty($payment_method_key)) {
            $args['payment_method'] = $payment_method_key;
        }

        if (!empty($return_url)) {
            $args[self::RETURN_URL_QUERY_ARG] = rawurlencode($return_url);
        }

        return add_query_arg($args, wc_get_checkout_url());
    }

    protected function payment_method_provider_id_to_key(string $provider_id): string
    {
        $map = [];

        if (class_exists('Growtype_Wc_Payment_Gateway_Stripe')) {
            $map[Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID] = Growtype_Wc_Payment_Gateway_Stripe::PAYMENT_METHOD_KEY;
        }

        if (class_exists('Growtype_Wc_Payment_Gateway_Paypal')) {
            $map[Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID] = Growtype_Wc_Payment_Gateway_Paypal::PAYMENT_METHOD_KEY;
        }

        if (class_exists('Growtype_Wc_Payment_Gateway_Coinbase')) {
            $map[Growtype_Wc_Payment_Gateway_Coinbase::PROVIDER_ID] = Growtype_Wc_Payment_Gateway_Coinbase::PAYMENT_METHOD_KEY;
        }

        return $map[$provider_id] ?? '';
    }

    /**
     * Create an order instantly from product ID, applying current cart context (coupons, user).
     *
     * @param int $product_id
     * @param int $qty
     * @param string $payment_method
     * @return \WC_Order
     * @throws \Exception
     */
    public static function create_instant_order($product_id, $qty = 1, $payment_method = '', $payment_method_type = '')
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            throw new \Exception('Product not found');
        }

        $order = wc_create_order();
        $order->add_product($product, $qty);

        if ($payment_method) {
            $order->set_payment_method($payment_method);

            // Enhance title with specific type if provided
            if ($payment_method === Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID && !empty($payment_method_type)) {
                $gateways = WC()->payment_gateways()->payment_gateways();
                $stripe_gateway = $gateways[$payment_method] ?? null;
                $base_title = $stripe_gateway ? $stripe_gateway->method_title : 'Growtype WC - ' . Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID;

                $type_formatted = ucfirst($payment_method_type);
                $order->set_payment_method_title("$base_title ($type_formatted)");
                $order->update_meta_data('_stripe_payment_method_type', $payment_method_type);
            }
        }

        // Apply coupons from current session
        if (WC()->cart) {
            $applied_coupons = WC()->cart->get_applied_coupons();
            if (!empty($applied_coupons)) {
                foreach ($applied_coupons as $applied_coupon) {
                    $order->apply_coupon($applied_coupon);
                }
            }
        }

        if (is_user_logged_in()) {
            $order->set_customer_id(get_current_user_id());
        }

        $order->calculate_totals();
        $order->save();

        return $order;
    }

    /**
     * AJAX: Create Payment Intent for Instant Checkout
     */
    public function ajax_create_payment_intent()
    {
        check_ajax_referer('growtype_wc_ajax_nonce', 'nonce');

        try {
            $product_id = absint($_POST['product_id'] ?? 0);
            if (!$product_id) {
                throw new \Exception('Invalid product ID');
            }

            $payment_method_type = sanitize_text_field($_POST['payment_method_type'] ?? '');
            $return_url = esc_url_raw(wp_unslash($_POST['return_url'] ?? ''));

            // 1. Create Order using shared logic
            $order = self::create_instant_order($product_id, 1, Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID, $payment_method_type);
            $order_id = $order->get_id();

            if (!empty($return_url)) {
                $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
                $return_host = wp_parse_url($return_url, PHP_URL_HOST);
                $return_path = wp_parse_url($return_url, PHP_URL_PATH);

                if ((!empty($return_host) && !empty($site_host) && strtolower($return_host) === strtolower($site_host)) &&
                    (empty($return_path) || false === strpos($return_path, '/wp-admin'))) {
                    $order->update_meta_data('_growtype_return_after_payment_url', $return_url);
                } else {
                    $return_url = '';
                }
            }

            // 2. Get Stripe Gateway to use its config
            $gateways = WC()->payment_gateways()->payment_gateways();
            $stripe_gateway = $gateways[Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID] ?? null;

            if (!$stripe_gateway) {
                throw new \Exception('Stripe gateway not available');
            }

            $secret_key = $stripe_gateway->get_secret_key();
            if (empty($secret_key)) {
                throw new \Exception('Stripe secret key is missing in settings');
            }

            $stripe = new \Stripe\StripeClient($secret_key);

            // 3. Create Payment Intent
            $intent_params = [
                'amount' => intval(round($order->get_total() * 100)),
                'currency' => strtolower($order->get_currency()),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'setup_future_usage' => 'off_session',
                'metadata' => [
                    'order_id' => $order_id,
                ],
            ];

            // If user is logged in, ensure we have a Stripe customer
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $customer_id = get_user_meta($user_id, 'stripe_customer_id', true);

                if (!$customer_id) {
                    $user = get_userdata($user_id);
                    try {
                        $customer = $stripe->customers->create([
                            'email' => $user->user_email,
                            'name' => $user->display_name,
                            'metadata' => ['user_id' => $user_id]
                        ]);
                        $customer_id = $customer->id;
                        update_user_meta($user_id, 'stripe_customer_id', $customer_id);
                    } catch (\Exception $e) {
                        error_log('Growtype WC: Failed to create customer during intent: ' . $e->getMessage());
                    }
                }

                if ($customer_id) {
                    $intent_params['customer'] = $customer_id;
                }
            }

            $intent = $stripe->paymentIntents->create($intent_params);

            // Fetch account info if possible or just log the intent's account if returned
            // Stripe-PHP doesn't always return the account ID on the intent unless expanded or using Connect
            // But we can check if the client has a default account or just log more info.

            error_log(sprintf(
                'Growtype WC: Created Payment Intent %s for Order %d. Key prefix: %s. Status: %s',
                $intent->id,
                $order_id,
                substr($secret_key, 0, 20),
                $intent->status
            ));

            $order->update_meta_data('stripe_intent_id', $intent->id);
            $order->save();

            $success_url = Growtype_Wc_Payment_Gateway::success_url($order_id, Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID);
            if (!empty($return_url)) {
                $success_url = add_query_arg('growtype_return_after_payment_url', rawurlencode($return_url), $success_url);
            }

            // 4. Return Data
            wp_send_json_success([
                'order_id' => $order_id,
                'clientSecret' => $intent->client_secret,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'label' => 'Total',
                'success_url' => $success_url,
                'debug' => [
                    'test_mode' => $stripe_gateway->test_mode ? 'yes' : 'no',
                    'key_prefix' => substr($secret_key, 0, 7),
                ]
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Get Payment Info for Instant Checkout (without creating order)
     */
    public function ajax_get_payment_info()
    {
        check_ajax_referer('growtype_wc_ajax_nonce', 'nonce');

        try {
            $product_id = absint($_POST['product_id'] ?? 0);
            if (!$product_id) {
                throw new \Exception('Invalid product ID');
            }

            $product = wc_get_product($product_id);
            if (!$product) {
                throw new \Exception('Product not found');
            }

            // Mock an order total for element initialization
            $amount = $product->get_price();
            $currency = get_woocommerce_currency();

            wp_send_json_success([
                'amount' => intval(round($amount * 100)),
                'currency' => strtolower($currency),
                'label' => $product->get_name(),
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Finalize Order after successful payment
     */
    public function ajax_finalize_order()
    {
        check_ajax_referer('growtype_wc_ajax_nonce', 'nonce');

        try {
            $order_id = absint($_POST['order_id'] ?? 0);
            $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');

            if (!$order_id) {
                throw new \Exception('Invalid order ID');
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            // Optional: verify status with Stripe if intent ID is provided
            if ($payment_intent_id) {
                $gateways = WC()->payment_gateways()->payment_gateways();
                $stripe_gateway = $gateways[Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID] ?? null;
                if ($stripe_gateway) {
                    $stripe = new \Stripe\StripeClient($stripe_gateway->get_secret_key());
                    $pi = $stripe->paymentIntents->retrieve($payment_intent_id);
                    if ($pi->status !== 'succeeded' && $pi->status !== 'processing') {
                        throw new \Exception('Payment not successful');
                    }

                    error_log(sprintf('Growtype WC Debug - Finalizing Order %d: PI Response - Customer: %s, PM: %s', $order_id, $pi->customer, $pi->payment_method));

                    if ($pi->customer) {
                        $order->update_meta_data('stripe_customer_id', $pi->customer);
                    }

                    if ($pi->payment_method) {
                        $order->update_meta_data('stripe_payment_method_id', $pi->payment_method);
                    }

                    $order->update_meta_data('stripe_transaction_id', $pi->id);
                    self::persist_stripe_display_details_from_payment_intent($order, $pi);

                    // Capture wallet type (google_pay, apple_pay etc) from Stripe if available
                    $wallet_type = '';
                    try {
                        if (isset($pi->payment_method_details->wallet->type)) {
                            $wallet_type = $pi->payment_method_details->wallet->type;
                        } elseif (isset($pi->payment_method)) {
                            // If not expanded, try to get more info from the payment method object or fallback to meta
                            $pm_obj = $stripe->paymentMethods->retrieve($pi->payment_method);
                            if (isset($pm_obj->card->wallet->type)) {
                                $wallet_type = $pm_obj->card->wallet->type;
                            }
                        }
                    } catch (\Exception $e) {
                        error_log('Growtype WC: Could not retrieve wallet type: ' . $e->getMessage());
                    }

                    // If we found a wallet type, update the order title and meta
                    if ($wallet_type) {
                        $order->update_meta_data('_stripe_payment_method_type', $wallet_type);
                        $gateways = WC()->payment_gateways()->payment_gateways();
                        $stripe_gateway = $gateways[Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID] ?? null;
                        $base_title = $stripe_gateway ? $stripe_gateway->method_title : 'Stripe';
                        $order->set_payment_method_title($base_title . ' (' . ucfirst(str_replace('_', ' ', $wallet_type)) . ')');
                    }

                    if ($order->get_customer_id() && $pi->customer) {
                        update_user_meta($order->get_customer_id(), 'stripe_customer_id', $pi->customer);
                    }
                    $order->save();
                }
            }

            if (!$order->is_paid()) {
                $order->payment_complete();
            }

            wp_send_json_success();

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
