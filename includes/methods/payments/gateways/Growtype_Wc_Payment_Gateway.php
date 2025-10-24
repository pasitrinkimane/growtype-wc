<?php

class Growtype_Wc_Payment_Gateway
{
    public function __construct()
    {
        add_action('init', function () {
            if (class_exists('woocommerce')) {
                $this->load_gateways();
            }
        });

        add_filter('woocommerce_payment_gateways', [$this, 'growtype_wc_payment_gateways']);
        add_action('woocommerce_review_order_before_payment', [$this, 'woocommerce_review_order_before_payment_callback']);
        add_action('woocommerce_checkout_order_processed', [$this, 'woocommerce_checkout_order_processed_callback'], 0, 3);
    }

    /**
     *
     */
    function woocommerce_checkout_order_processed_callback($order_id, $posted_data, $order)
    {
        error_log(sprintf('woocommerce_checkout_order_processed. Order id: %d', $order_id));

        if (!$order->is_paid()) {
            error_log('order not paid');
        }
    }

    function woocommerce_review_order_before_payment_callback()
    {
        $current = WC()->session->get('chosen_payment_method');
        $gateways = WC()->payment_gateways()->payment_gateways();
        if ($current && isset($gateways[$current])) {
            $gateway = $gateways[$current];
            $gateway->chosen = false;
        }
    }

    function growtype_wc_payment_gateways($gateways)
    {
        $gateways[] = 'Growtype_Wc_Payment_Gateway_Free';
        $gateways[] = 'Growtype_Wc_Payment_Gateway_Paypal';
        $gateways[] = 'Growtype_Wc_Payment_Gateway_Cc';
        $gateways[] = 'Growtype_Wc_Payment_Gateway_Stripe';
        $gateways[] = 'Growtype_Wc_Payment_Gateway_Coinbase';

        return $gateways;
    }

    public function load_gateways()
    {
        include_once 'providers/Growtype_Wc_Payment_Gateway_Free.php';
        include_once 'providers/Growtype_Wc_Payment_Gateway_Paypal.php';
        include_once 'providers/Growtype_Wc_Payment_Gateway_Cc.php';
        include_once 'providers/Growtype_Wc_Payment_Gateway_Stripe.php';
        include_once 'providers/Growtype_Wc_Payment_Gateway_Coinbase.php';
    }

    public static function success_url($order_id, $payment_provider = null)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return home_url(); // fallback if order not found
        }

        $query_data = [
            'key' => $order->get_order_key(),
        ];

        if ($payment_provider === Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID) {
            $query_data['checkout_session_id'] = '{CHECKOUT_SESSION_ID}';
        }

        // Get the correct payment gateway object from the order
        $payment_method = $order->get_payment_method();
        $payment_gateway = WC()->payment_gateways()->payment_gateways()[$payment_method] ?? null;

        if (!$payment_gateway) {
            return $order->get_checkout_order_received_url(); // fallback URL
        }

        return add_query_arg($query_data, $payment_gateway->get_return_url($order));
    }

    public static function cancel_url($order_id = null, $redirect_to_thankyou_page = false)
    {
        // 1) If we have an order and want to go to thank you...
        if ($order_id && $redirect_to_thankyou_page) {
            if ($order = wc_get_order($order_id)) {
                // build /checkout/order-received/{id}/?key=…
                $thankyou = wc_get_endpoint_url(
                    'order-received',
                    $order_id,
                    wc_get_page_permalink('checkout')
                );
                return esc_url_raw(add_query_arg('key', $order->get_order_key(), $thankyou));
            }
        }

        // 2) Otherwise, return “current page” minus checkout/query args.
        //    Start with the fully-qualified current URL:
        $current_url = (is_ssl() ? 'https://' : 'http://')
            . $_SERVER['HTTP_HOST']
            . $_SERVER['REQUEST_URI'];

        // 3) Strip out all checkout-related query params at once:
        $allowed = remove_query_arg(
            [
                'action',
                'add-to-cart',
                'payment_method',
                'checkout_session_id',
                'token',
                'ba_token',
                'order_id',
                'product_id',
            ],
            $current_url
        );

        return esc_url_raw($allowed);
    }

    public static function update_user_email_if_not_exists($wp_user_id, $new_email)
    {
        $wp_user = get_user_by('id', $wp_user_id);

        if ($wp_user && empty($wp_user->user_email) && !empty($new_email)) {
            $status = wp_update_user([
                'ID' => $wp_user_id,
                'user_email' => $new_email,
            ]);

            if (is_wp_error($status)) {
                error_log(sprintf('Growtype Wc - Failed to update user email: %s. Message: %s', $new_email, print_r($status->get_error_message(), true)));

                return false;
            }

            return true;
        }

        return false;
    }

    public static function update_order_email_if_not_exists($order_id, $new_email)
    {
        $order = wc_get_order($order_id);

        if ($order && empty($order->get_billing_email()) && !empty($new_email)) {
            $order->set_billing_email($new_email);
            $order->save();

            return true;
        }

        return false;
    }
}
