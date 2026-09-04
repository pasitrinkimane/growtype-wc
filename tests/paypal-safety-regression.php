<?php

/**
 * Focused local regression for PayPal payment/subscription safety.
 *
 * Run inside the TalkieMate WordPress bootstrap:
 * wp --path=/path/to/web/wp --url=https://talkiemate.test eval-file \
 *   /path/to/growtype-wc/tests/paypal-safety-regression.php
 */

if (!defined('ABSPATH')) {
    throw new RuntimeException('Run this file through WP-CLI eval-file.');
}

function gwc_paypal_test_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$created_order_ids = [];
$created_subscription_ids = [];
$created_product_ids = [];
$created_option_keys = [];
$plan_option_key = '';
$previous_plan_option = false;
$previous_plan_option_exists = false;
$plan_http_mock = null;

try {
    $gateways = WC()->payment_gateways()->payment_gateways();
    $gateway = $gateways[Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID] ?? null;
    gwc_paypal_test_assert($gateway instanceof Growtype_Wc_Payment_Gateway_Paypal, 'PayPal gateway is unavailable.');
    gwc_paypal_test_assert(
        $gateway->resolve_checkout_flow(0, 'applepay') === Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_ORDERS_API_ONE_TIME,
        'Apple Pay one-time checkout did not retain the Orders API flow.'
    );
    gwc_paypal_test_assert(
        Growtype_Wc_Payment_Gateway_Paypal_Redirects::resolve_approval_url([[
            'rel' => 'approve',
            'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=TEST',
        ]]) === 'https://www.sandbox.paypal.com/checkoutnow?token=TEST',
        'A valid PayPal approval URL was rejected.'
    );
    gwc_paypal_test_assert(
        is_wp_error(Growtype_Wc_Payment_Gateway_Paypal_Redirects::resolve_approval_url([[
            'rel' => 'approve',
            'href' => 'https://evilpaypal.com/checkoutnow?token=TEST',
        ]])),
        'A lookalike non-PayPal approval domain was accepted.'
    );
    gwc_paypal_test_assert(
        is_wp_error(Growtype_Wc_Payment_Gateway_Paypal_Redirects::resolve_approval_url([[
            'rel' => 'approve',
            'href' => 'http://www.paypal.com/checkoutnow?token=TEST',
        ]])),
        'An insecure PayPal approval URL was accepted.'
    );
    gwc_paypal_test_assert(
        is_wp_error(Growtype_Wc_Payment_Gateway_Paypal_Redirects::resolve_approval_url([])),
        'A checkout response without an approval URL was accepted.'
    );

    $webhook = new Growtype_Wc_Payment_Gateway_Paypal_Webhook($gateway);
    $event_handlers = new ReflectionMethod($webhook, 'get_event_handlers');
    $event_handlers->setAccessible(true);
    $registered_handlers = $event_handlers->invoke($webhook);
    gwc_paypal_test_assert(
        isset($registered_handlers['BILLING.SUBSCRIPTION.CANCELLED']),
        'The webhook handler registry is missing subscription cancellation.'
    );
    $custom_event_handler = static function ($handlers) {
        $handlers['GWC.TEST.EVENT'] = static function () {
            return true;
        };
        return $handlers;
    };
    add_filter('growtype_wc_paypal_webhook_event_handlers', $custom_event_handler, 10, 1);
    $registered_handlers = $event_handlers->invoke($webhook);
    gwc_paypal_test_assert(
        isset($registered_handlers['GWC.TEST.EVENT']) && is_callable($registered_handlers['GWC.TEST.EVENT']),
        'A future verified PayPal webhook handler cannot be registered.'
    );
    remove_filter('growtype_wc_paypal_webhook_event_handlers', $custom_event_handler, 10);
    $verify_signature = new ReflectionMethod($webhook, 'verify_webhook_signature');
    $verify_signature->setAccessible(true);
    gwc_paypal_test_assert(
        $verify_signature->invoke($webhook, '{}', 'WH-GWC-REGRESSION') === false,
        'Webhook verification did not fail closed when signature headers were missing.'
    );

    $product_ids = get_posts([
        'post_type' => 'product',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_growtype_wc_subscription',
        'meta_value' => 'yes',
    ]);
    $product = !empty($product_ids) ? wc_get_product((int) $product_ids[0]) : null;
    gwc_paypal_test_assert($product instanceof WC_Product, 'No local subscription product is available for the regression.');
    ob_start();
    Growtype_Wc_Payment_Gateway_Paypal_Payment_Form::render([
        'product_id' => $product->get_id(),
        'boot_script' => false,
    ]);
    $subscription_payment_form = (string) ob_get_clean();
    gwc_paypal_test_assert(
        str_contains($subscription_payment_form, 'gwc-payment-form__card')
        && str_contains($subscription_payment_form, 'gwc-payment-form__express--subscription')
        && str_contains($subscription_payment_form, 'data-method="applepay"')
        && str_contains($subscription_payment_form, 'authorize recurring charges')
        && !str_contains($subscription_payment_form, 'Pay with PayPal')
        && !str_contains($subscription_payment_form, 'data-payment-form-mode="redirect"')
        && !str_contains($subscription_payment_form, 'gwc-payment-form__divider')
        && !str_contains($subscription_payment_form, 'gwc-paypal-dev-helper')
        && !str_contains($subscription_payment_form, 'googlepay'),
        'Subscription card modal did not stay limited to recurring card and Apple Pay.'
    );

    $previous_dev_helper_env = getenv(Growtype_Wc_Payment_Gateway_Paypal_Card_Form::DEV_HELPER_ENV_KEY);
    $sandbox_gateway_stub = new class {
        public function is_test_mode(): bool
        {
            return true;
        }
    };
    $live_gateway_stub = new class {
        public function is_test_mode(): bool
        {
            return false;
        }
    };
    putenv(Growtype_Wc_Payment_Gateway_Paypal_Card_Form::DEV_HELPER_ENV_KEY . '=true');
    gwc_paypal_test_assert(
        Growtype_Wc_Payment_Gateway_Paypal_Card_Form::should_show_dev_helper($sandbox_gateway_stub)
        && !Growtype_Wc_Payment_Gateway_Paypal_Card_Form::should_show_dev_helper($live_gateway_stub),
        'The PayPal development helper was not restricted to test mode.'
    );
    putenv(Growtype_Wc_Payment_Gateway_Paypal_Card_Form::DEV_HELPER_ENV_KEY . '=false');
    gwc_paypal_test_assert(
        !Growtype_Wc_Payment_Gateway_Paypal_Card_Form::should_show_dev_helper($sandbox_gateway_stub),
        'The PayPal development helper ignored its disabled environment flag.'
    );
    if ($previous_dev_helper_env === false) {
        putenv(Growtype_Wc_Payment_Gateway_Paypal_Card_Form::DEV_HELPER_ENV_KEY);
    } else {
        putenv(Growtype_Wc_Payment_Gateway_Paypal_Card_Form::DEV_HELPER_ENV_KEY . '=' . $previous_dev_helper_env);
    }

    $hosted_fields_source = (string) file_get_contents(
        dirname(__DIR__) . '/includes/methods/payments/gateways/providers/paypal/partials/Growtype_Wc_Payment_Gateway_Paypal_Hosted_Fields.php'
    );
    gwc_paypal_test_assert(
        str_contains($hosted_fields_source, "document.querySelector('.gwc-payment-form__express')")
        && !str_contains($hosted_fields_source, "document.querySelector('.gwc-payment-form') !== null"),
        'Hosted Fields loader still mistakes a modal-only card form for an express SDK mount.'
    );

    $one_time_product = new WC_Product_Simple();
    $one_time_product->set_name('PayPal one-time regression product');
    $one_time_product->set_regular_price('1.00');
    $one_time_product->set_status('private');
    $one_time_product_id = (int) $one_time_product->save();
    $created_product_ids[] = $one_time_product_id;
    ob_start();
    Growtype_Wc_Payment_Gateway_Paypal_Payment_Form::render([
        'product_id' => $one_time_product_id,
        'boot_script' => false,
    ]);
    $one_time_payment_form = (string) ob_get_clean();
    gwc_paypal_test_assert(
        str_contains($one_time_payment_form, 'gwc-payment-form__card')
        && str_contains($one_time_payment_form, 'applepay')
        && str_contains($one_time_payment_form, 'googlepay'),
        'One-time card, Apple Pay, or Google Pay UI was removed.'
    );

    $matching_plan = [
        'id' => 'P-GWC-MATCH',
        'status' => 'ACTIVE',
        'billing_cycles' => [[
            'tenure_type' => 'REGULAR',
            'frequency' => [
                'interval_unit' => strtoupper((string) growtype_wc_get_subscription_period($product->get_id())),
                'interval_count' => (int) growtype_wc_get_subscription_duration($product->get_id()),
            ],
            'pricing_scheme' => [
                'fixed_price' => [
                    'value' => wc_format_decimal(growtype_wc_get_subscription_price($product->get_id()), 2),
                    'currency_code' => get_woocommerce_currency(),
                ],
            ],
        ]],
    ];
    gwc_paypal_test_assert(
        $gateway->subscriptions->billing_plan_matches_product($matching_plan, 'P-GWC-MATCH', $product->get_id()),
        'An exact active PayPal plan was rejected.'
    );
    $wrong_price_plan = $matching_plan;
    $wrong_price_plan['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'] = '999.99';
    gwc_paypal_test_assert(
        !$gateway->subscriptions->billing_plan_matches_product($wrong_price_plan, 'P-GWC-MATCH', $product->get_id()),
        'A stale PayPal plan with the wrong price was accepted.'
    );

    $coupon_sig = md5('');
    $trial_sig = '';
    if (growtype_wc_product_is_trial($product->get_id())) {
        $trial_sig = '_t' . growtype_wc_get_trial_price($product->get_id())
            . '_' . growtype_wc_get_trial_period($product->get_id())
            . '_' . growtype_wc_get_trial_duration($product->get_id());
    }
    $plan_option_key = 'gwc_paypal_plan_' . $product->get_id() . '_' . $coupon_sig . $trial_sig;
    $previous_plan_option = get_option($plan_option_key, false);
    $previous_plan_option_exists = $previous_plan_option !== false;
    update_option($plan_option_key, 'P-STALE-REGRESSION', false);
    $plan_http_mock = static function ($preempt, $args, $url) {
        $method = strtoupper((string) ($args['method'] ?? 'GET'));
        if ($method === 'GET' && str_contains($url, '/v1/billing/plans/P-STALE-REGRESSION')) {
            return [
                'headers' => [],
                'body' => wp_json_encode(['name' => 'RESOURCE_NOT_FOUND']),
                'response' => ['code' => 404, 'message' => 'Not Found'],
                'cookies' => [],
                'filename' => null,
            ];
        }
        if ($method === 'POST' && str_ends_with($url, '/v1/catalogs/products')) {
            return [
                'headers' => [],
                'body' => wp_json_encode(['id' => 'PROD-GWC-NEW', 'name' => 'Regression product', 'description' => 'Regression product']),
                'response' => ['code' => 201, 'message' => 'Created'],
                'cookies' => [],
                'filename' => null,
            ];
        }
        if ($method === 'POST' && str_ends_with($url, '/v1/billing/plans')) {
            return [
                'headers' => [],
                'body' => wp_json_encode(['id' => 'P-GWC-NEW']),
                'response' => ['code' => 201, 'message' => 'Created'],
                'cookies' => [],
                'filename' => null,
            ];
        }

        return $preempt;
    };
    add_filter('pre_http_request', $plan_http_mock, PHP_INT_MAX, 3);
    $replacement_plan_id = $gateway->subscriptions->get_or_create_plan(
        'TEST-ACCESS-TOKEN',
        $product->get_id(),
        []
    );
    remove_filter('pre_http_request', $plan_http_mock, PHP_INT_MAX);
    $plan_http_mock = null;
    gwc_paypal_test_assert(
        $replacement_plan_id === 'P-GWC-NEW' && get_option($plan_option_key) === 'P-GWC-NEW',
        'A missing cached PayPal plan was not evicted and replaced.'
    );
    gwc_paypal_test_assert(
        $gateway->resolve_checkout_flow($product->get_id(), 'paypal') === Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_BILLING_SUBSCRIPTION,
        'PayPal subscription checkout did not resolve to Billing Subscriptions.'
    );
    gwc_paypal_test_assert(
        $gateway->resolve_checkout_flow($product->get_id(), 'card') === Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_ORDERS_API_RECURRING_CARD,
        'Hosted Fields card did not resolve to recurring-card checkout.'
    );
    gwc_paypal_test_assert(
        $gateway->resolve_checkout_flow($product->get_id(), 'applepay') === Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_ORDERS_API_RECURRING_APPLE_PAY,
        'Apple Pay did not resolve to recurring vault checkout.'
    );
    gwc_paypal_test_assert(
        $gateway->resolve_checkout_flow($product->get_id(), 'googlepay') === Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_UNAVAILABLE,
        'Google Pay incorrectly resolved a subscription product without a provider recurring-vault contract.'
    );

    $custom_flow = static function ($flow, $product_id, $payment_source, $is_subscription) use ($product) {
        if ($is_subscription && (int) $product_id === (int) $product->get_id() && $payment_source === 'googlepay') {
            return 'future_googlepay_recurring';
        }

        return $flow;
    };
    add_filter('growtype_wc_paypal_checkout_flow', $custom_flow, 10, 4);
    gwc_paypal_test_assert(
        $gateway->resolve_checkout_flow($product->get_id(), 'googlepay') === 'future_googlepay_recurring',
        'A future recurring checkout implementation cannot register its own flow.'
    );
    remove_filter('growtype_wc_paypal_checkout_flow', $custom_flow, 10);

    $recurring_card_source = $gateway->orders->build_vault_payment_source('card', '', '', '', true);
    gwc_paypal_test_assert(
        ($recurring_card_source['card']['attributes']['vault']['store_in_vault'] ?? '') === 'ON_SUCCESS'
        && ($recurring_card_source['card']['stored_credential']['payment_initiator'] ?? '') === 'CUSTOMER'
        && ($recurring_card_source['card']['stored_credential']['payment_type'] ?? '') === 'RECURRING'
        && ($recurring_card_source['card']['stored_credential']['usage'] ?? '') === 'FIRST',
        'Initial Hosted Fields subscription payload did not request recurring card consent and vaulting.'
    );

    $recurring_apple_pay_source = $gateway->orders->build_vault_payment_source('applepay', '', '', '', true);
    gwc_paypal_test_assert(
        ($recurring_apple_pay_source['apple_pay']['attributes']['vault']['store_in_vault'] ?? '') === 'ON_SUCCESS'
        && ($recurring_apple_pay_source['apple_pay']['stored_credential']['payment_initiator'] ?? '') === 'CUSTOMER'
        && ($recurring_apple_pay_source['apple_pay']['stored_credential']['payment_type'] ?? '') === 'RECURRING'
        && ($recurring_apple_pay_source['apple_pay']['stored_credential']['usage'] ?? '') === 'FIRST',
        'Initial Apple Pay subscription payload did not request recurring consent and vaulting.'
    );

    // The generic WooCommerce gateway entry point must never complete an order.
    $direct_order = wc_create_order();
    $created_order_ids[] = $direct_order->get_id();
    $direct_order->add_product($product, 1);
    $direct_order->set_payment_method($gateway->id);
    $direct_order->calculate_totals();
    $direct_order->save();

    $apple_renewal_payload = [];
    $apple_renewal_http = static function ($preempt, $args, $url) use (&$apple_renewal_payload) {
        if (str_ends_with($url, '/v2/checkout/orders')) {
            $apple_renewal_payload = json_decode((string) ($args['body'] ?? ''), true) ?: [];
            return [
                'headers' => [],
                'body' => wp_json_encode(['id' => 'PAYPAL-APPLE-RENEWAL-GWC', 'status' => 'APPROVED']),
                'response' => ['code' => 201, 'message' => 'Created'],
                'cookies' => [],
            ];
        }

        return $preempt;
    };
    add_filter('pre_http_request', $apple_renewal_http, PHP_INT_MAX, 3);
    $apple_renewal_result = $gateway->orders->create_recurring_vault_order(
        'TEST-ACCESS-TOKEN',
        'APPLE-VAULT-GWC',
        'CUSTOMER-GWC',
        $direct_order,
        'gwc-apple-renewal-test',
        'applepay'
    );
    remove_filter('pre_http_request', $apple_renewal_http, PHP_INT_MAX);
    gwc_paypal_test_assert(
        ($apple_renewal_result['id'] ?? '') === 'PAYPAL-APPLE-RENEWAL-GWC'
        && ($apple_renewal_payload['payment_source']['apple_pay']['vault_id'] ?? '') === 'APPLE-VAULT-GWC'
        && ($apple_renewal_payload['payment_source']['apple_pay']['stored_credential']['payment_initiator'] ?? '') === 'MERCHANT'
        && ($apple_renewal_payload['payment_source']['apple_pay']['stored_credential']['payment_type'] ?? '') === 'RECURRING'
        && ($apple_renewal_payload['payment_source']['apple_pay']['stored_credential']['usage'] ?? '') === 'SUBSEQUENT'
        && !isset($apple_renewal_payload['payment_source']['card']),
        'Apple Pay renewal did not use its vaulted source with recurring merchant-initiated indicators.'
    );

    $payment_matches = new ReflectionMethod($webhook, 'payment_matches_order');
    $payment_matches->setAccessible(true);
    $matching_resource = [
        'amount' => [
            'value' => (string) $direct_order->get_total(),
            'currency_code' => $direct_order->get_currency(),
        ],
    ];
    gwc_paypal_test_assert(
        $payment_matches->invoke($webhook, $matching_resource, $direct_order) === true,
        'Webhook rejected matching amount and currency evidence.'
    );
    $mismatched_resource = $matching_resource;
    $mismatched_resource['amount']['value'] = (string) ((float) $direct_order->get_total() + 1);
    gwc_paypal_test_assert(
        $payment_matches->invoke($webhook, $mismatched_resource, $direct_order) === false,
        'Webhook accepted a mismatched payment amount.'
    );

    $hosted_fields = new Growtype_Wc_Payment_Gateway_Paypal_Hosted_Fields($gateway);
    $extract_vault_details = new ReflectionMethod($hosted_fields, 'extract_vault_details');
    $extract_vault_details->setAccessible(true);
    $apple_vault_details = $extract_vault_details->invoke($hosted_fields, [
        'payment_source' => [
            'apple_pay' => [
                'attributes' => [
                    'vault' => [
                        'id' => 'APPLE-VAULT-RESPONSE',
                        'status' => 'VAULTED',
                        'customer' => ['id' => 'APPLE-CUSTOMER-RESPONSE'],
                    ],
                ],
            ],
            'card' => [
                'attributes' => [
                    'vault' => ['id' => 'WRONG-SOURCE-VAULT', 'status' => 'VAULTED'],
                ],
            ],
        ],
    ], 'applepay');
    gwc_paypal_test_assert(
        ($apple_vault_details['id'] ?? '') === 'APPLE-VAULT-RESPONSE'
        && ($apple_vault_details['customer_id'] ?? '') === 'APPLE-CUSTOMER-RESPONSE'
        && ($apple_vault_details['status'] ?? '') === 'VAULTED'
        && ($apple_vault_details['type'] ?? '') === 'apple_pay',
        'Apple Pay capture did not extract and bind the official vault response to the requested source.'
    );
    $capture_matches = new ReflectionMethod($hosted_fields, 'capture_matches_order');
    $capture_matches->setAccessible(true);
    $capture_result = [
        'purchase_units' => [[
            'invoice_id' => (string) $direct_order->get_id(),
            'payments' => [
                'captures' => [[
                    'amount' => [
                        'value' => (string) $direct_order->get_total(),
                        'currency_code' => $direct_order->get_currency(),
                    ],
                ]],
            ],
        ]],
    ];
    gwc_paypal_test_assert(
        $capture_matches->invoke($hosted_fields, $capture_result, $direct_order) === true,
        'Hosted capture rejected its exact order, amount and currency.'
    );
    $capture_result['purchase_units'][0]['invoice_id'] = (string) ($direct_order->get_id() + 1);
    gwc_paypal_test_assert(
        $capture_matches->invoke($hosted_fields, $capture_result, $direct_order) === false,
        'Hosted capture accepted a mismatched order reference.'
    );

    $result = $gateway->orders->process_payment($direct_order->get_id());
    $direct_order = wc_get_order($direct_order->get_id());
    gwc_paypal_test_assert(($result['result'] ?? '') === 'failure', 'Generic process_payment() did not fail closed.');
    gwc_paypal_test_assert(!$direct_order->is_paid(), 'Generic process_payment() marked an unpaid order as paid.');

    // Even if an ordinary capture completes a subscription product order, it
    // must not create a recurring local subscription without provider proof.
    $unverified_order = wc_create_order();
    $created_order_ids[] = $unverified_order->get_id();
    $unverified_order->add_product($product, 1);
    $unverified_order->set_payment_method($gateway->id);
    $unverified_order->calculate_totals();
    $unverified_order->save();
    $unverified_order->payment_complete('TEST-ONE-TIME-CAPTURE');

    $unverified_subscriptions = growtype_wc_get_subscriptions([
        'order_id' => $unverified_order->get_id(),
        'limit' => -1,
    ]);
    gwc_paypal_test_assert(count($unverified_subscriptions) === 0, 'An unverified PayPal order created a fake local subscription.');

    // A verified ACTIVE Billing Subscription creates exactly one local row,
    // even if WooCommerce dispatches payment_complete more than once.
    $verified_order = wc_create_order();
    $created_order_ids[] = $verified_order->get_id();
    $verified_order->add_product($product, 1);
    $verified_order->set_payment_method($gateway->id);
    $verified_order->calculate_totals();
    $verified_order->update_meta_data('paypal_subscription_id', 'I-GWC-REGRESSION');
    $verified_order->update_meta_data('_paypal_subscription_verified_active', 'yes');
    $verified_order->save();
    $verified_order->payment_complete('I-GWC-REGRESSION');
    do_action('woocommerce_payment_complete', $verified_order->get_id(), 'I-GWC-REGRESSION');

    $verified_subscriptions = growtype_wc_get_subscriptions([
        'order_id' => $verified_order->get_id(),
        'limit' => -1,
    ]);
    gwc_paypal_test_assert(count($verified_subscriptions) === 1, 'Verified PayPal callbacks did not create exactly one local subscription.');
    $local_subscription_id = (int) $verified_subscriptions[0]->ID;
    $created_subscription_ids[] = $local_subscription_id;

    $provider_payment = [
        'billing_info' => [
            'last_payment' => [
                'amount' => [
                    'value' => (string) $verified_order->get_total(),
                    'currency_code' => $verified_order->get_currency(),
                ],
            ],
        ],
    ];

    $approved = $gateway->subscriptions->validate_activation_response(
        array_merge($provider_payment, ['id' => 'I-GWC-REGRESSION', 'status' => 'APPROVED', 'plan_id' => 'P-GWC']),
        'I-GWC-REGRESSION',
        'P-GWC',
        $verified_order->get_id()
    );
    gwc_paypal_test_assert(is_wp_error($approved), 'APPROVED was accepted as an active PayPal subscription.');

    $verified_order->update_meta_data(
        '_growtype_wc_checkout_flow',
        Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_ORDERS_API_ONE_TIME
    );
    $verified_order->save();
    $wrong_flow = $gateway->subscriptions->validate_activation_response(
        array_merge($provider_payment, ['id' => 'I-GWC-REGRESSION', 'status' => 'ACTIVE', 'plan_id' => 'P-GWC']),
        'I-GWC-REGRESSION',
        'P-GWC',
        $verified_order->get_id()
    );
    gwc_paypal_test_assert(is_wp_error($wrong_flow), 'A one-time checkout flow was accepted as recurring billing.');
    $verified_order->update_meta_data(
        '_growtype_wc_checkout_flow',
        Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_BILLING_SUBSCRIPTION
    );
    $verified_order->save();

    $active = $gateway->subscriptions->validate_activation_response(
        array_merge($provider_payment, ['id' => 'I-GWC-REGRESSION', 'status' => 'ACTIVE', 'plan_id' => 'P-GWC']),
        'I-GWC-REGRESSION',
        'P-GWC',
        $verified_order->get_id()
    );
    gwc_paypal_test_assert($active === true, 'A matching ACTIVE PayPal subscription was rejected.');

    $wrong_payment = $provider_payment;
    $wrong_payment['billing_info']['last_payment']['amount']['value'] = (string) ((float) $verified_order->get_total() + 1);
    $wrong_payment = array_merge($wrong_payment, ['id' => 'I-GWC-REGRESSION', 'status' => 'ACTIVE', 'plan_id' => 'P-GWC']);
    gwc_paypal_test_assert(
        is_wp_error($gateway->subscriptions->validate_activation_response(
            $wrong_payment,
            'I-GWC-REGRESSION',
            'P-GWC',
            $verified_order->get_id()
        )),
        'ACTIVE subscription with the wrong initial payment amount was accepted.'
    );

    $recurring_apple_order = wc_create_order();
    $created_order_ids[] = $recurring_apple_order->get_id();
    $recurring_apple_order->add_product($product, 1);
    $recurring_apple_order->set_payment_method($gateway->id);
    $recurring_apple_order->calculate_totals();
    $recurring_apple_order->update_meta_data(
        '_growtype_wc_checkout_flow',
        Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_ORDERS_API_RECURRING_APPLE_PAY
    );
    $recurring_apple_order->update_meta_data('_paypal_vault_source', 'applepay');
    $recurring_apple_order->update_meta_data('paypal_vault_id', 'APPLE-VAULT-GWC-REGRESSION');
    $recurring_apple_order->update_meta_data('paypal_customer_id', 'CUSTOMER-GWC-APPLE');
    $recurring_apple_order->update_meta_data('_paypal_capture_id', 'CAPTURE-GWC-APPLE');
    $recurring_apple_order->update_meta_data(
        Growtype_Wc_Payment_Gateway_Paypal_Subscriptions::RECURRING_CARD_VERIFIED_META_KEY,
        'yes'
    );
    $recurring_apple_order->save();
    gwc_paypal_test_assert(
        $gateway->subscriptions->is_recurring_vault_order($recurring_apple_order)
        && !$gateway->subscriptions->is_recurring_card_order($recurring_apple_order)
        && $gateway->subscriptions->can_activate_local_subscription(true, $recurring_apple_order) === true,
        'A verified recurring Apple Pay order was not recognized as an activatable recurring vault order.'
    );

    // A verified Hosted Fields recurring card activates once and a due renewal
    // produces one provider charge even when the worker is invoked repeatedly.
    $recurring_card_order = wc_create_order();
    $created_order_ids[] = $recurring_card_order->get_id();
    $recurring_card_order->add_product($product, 1);
    $recurring_card_order->set_payment_method($gateway->id);
    $recurring_card_order->calculate_totals();
    $recurring_card_order->update_meta_data(
        '_growtype_wc_checkout_flow',
        Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_ORDERS_API_RECURRING_CARD
    );
    $recurring_card_order->update_meta_data('_paypal_vault_source', 'card');
    $recurring_card_order->update_meta_data('paypal_vault_id', 'CARD-VAULT-GWC-REGRESSION');
    $recurring_card_order->update_meta_data('paypal_customer_id', 'CUSTOMER-GWC-REGRESSION');
    $recurring_card_order->update_meta_data('_paypal_capture_id', 'CAPTURE-GWC-INITIAL');
    $recurring_card_order->update_meta_data(
        Growtype_Wc_Payment_Gateway_Paypal_Subscriptions::RECURRING_CARD_VERIFIED_META_KEY,
        'yes'
    );
    $recurring_card_order->save();
    $recurring_card_order->payment_complete('CAPTURE-GWC-INITIAL');
    do_action('woocommerce_payment_complete', $recurring_card_order->get_id(), 'CAPTURE-GWC-INITIAL');

    $recurring_card_subscriptions = growtype_wc_get_subscriptions([
        'order_id' => $recurring_card_order->get_id(),
        'limit' => -1,
    ]);
    gwc_paypal_test_assert(
        count($recurring_card_subscriptions) === 1,
        'Verified recurring card callbacks did not create exactly one local subscription.'
    );
    $recurring_card_subscription_id = (int) $recurring_card_subscriptions[0]->ID;
    $created_subscription_ids[] = $recurring_card_subscription_id;
    $renewal_now = new DateTimeImmutable('2026-09-03 12:00:00', new DateTimeZone('UTC'));
    $renewal_due = $renewal_now->sub(new DateInterval('PT1H'));
    update_post_meta(
        $recurring_card_subscription_id,
        '_next_charge_date',
        $renewal_due->setTimezone(wp_timezone())->format('Y-m-d H:i:s')
    );
    update_post_meta(
        $recurring_card_subscription_id,
        '_end_date',
        $renewal_due->setTimezone(wp_timezone())->format('Y-m-d H:i:s')
    );

    $renewal_marker_key = Growtype_Wc_Payment_Gateway_Paypal_Subscriptions::RECURRING_CARD_RENEWAL_OPTION_PREFIX
        . $recurring_card_subscription_id . '_' . $renewal_due->format('YmdHis');
    $created_option_keys[] = $renewal_marker_key;
    $renewal_invoice_id = 0;
    $renewal_create_calls = 0;
    $renewal_capture_calls = 0;
    $recurring_card_http = static function ($preempt, $args, $url) use (
        &$renewal_invoice_id,
        &$renewal_create_calls,
        &$renewal_capture_calls,
        $recurring_card_subscription_id
    ) {
        if (strpos($url, '/v1/oauth2/token') !== false) {
            return [
                'headers' => [],
                'body' => wp_json_encode(['access_token' => 'GWC-RECURRING-CARD-TOKEN', 'expires_in' => 300]),
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies' => [],
            ];
        }
        if (str_ends_with($url, '/v2/checkout/orders')) {
            $renewal_create_calls++;
            $body = json_decode((string) ($args['body'] ?? ''), true);
            $renewal_invoice_id = (int) ($body['purchase_units'][0]['invoice_id'] ?? 0);
            gwc_paypal_test_assert(
                ($body['payment_source']['card']['stored_credential']['payment_initiator'] ?? '') === 'MERCHANT'
                && ($body['payment_source']['card']['stored_credential']['payment_type'] ?? '') === 'RECURRING'
                && ($body['payment_source']['card']['stored_credential']['usage'] ?? '') === 'SUBSEQUENT',
                'Renewal payload was not marked as a subsequent merchant-initiated recurring card charge.'
            );
            return [
                'headers' => [],
                'body' => wp_json_encode(['id' => 'PAYPAL-RENEWAL-GWC', 'status' => 'APPROVED']),
                'response' => ['code' => 201, 'message' => 'Created'],
                'cookies' => [],
            ];
        }
        if (str_contains($url, '/v2/checkout/orders/PAYPAL-RENEWAL-GWC/capture')) {
            $renewal_capture_calls++;
            return [
                'headers' => [],
                'body' => wp_json_encode([
                    'id' => 'PAYPAL-RENEWAL-GWC',
                    'status' => 'COMPLETED',
                    'purchase_units' => [[
                        'invoice_id' => (string) $renewal_invoice_id,
                        'payments' => ['captures' => [[
                            'id' => 'CAPTURE-GWC-RENEWAL',
                            'status' => 'COMPLETED',
                            'amount' => [
                                'value' => wc_format_decimal(
                                    get_post_meta($recurring_card_subscription_id, '_price', true),
                                    2
                                ),
                                'currency_code' => get_woocommerce_currency(),
                            ],
                        ]]],
                    ]],
                ]),
                'response' => ['code' => 201, 'message' => 'Created'],
                'cookies' => [],
            ];
        }

        return $preempt;
    };
    add_filter('pre_http_request', $recurring_card_http, PHP_INT_MAX, 3);
    $first_renewal = $gateway->subscriptions->process_recurring_card_subscription(
        $recurring_card_subscription_id,
        $recurring_card_order,
        $renewal_now
    );
    $second_renewal = $gateway->subscriptions->process_recurring_card_subscription(
        $recurring_card_subscription_id,
        $recurring_card_order,
        $renewal_now
    );
    remove_filter('pre_http_request', $recurring_card_http, PHP_INT_MAX);

    gwc_paypal_test_assert($first_renewal === true && $second_renewal === true, 'Recurring card renewal did not complete idempotently.');
    gwc_paypal_test_assert(
        $renewal_create_calls === 1 && $renewal_capture_calls === 1,
        'Repeated recurring-card workers created more than one PayPal charge.'
    );
    $renewal_marker = get_option($renewal_marker_key);
    gwc_paypal_test_assert(
        is_array($renewal_marker)
        && ($renewal_marker['status'] ?? '') === 'completed'
        && ($renewal_marker['capture_id'] ?? '') === 'CAPTURE-GWC-RENEWAL',
        'Recurring-card renewal did not persist its durable completion marker.'
    );
    $renewal_order_id = (int) ($renewal_marker['renewal_order_id'] ?? 0);
    if ($renewal_order_id > 0) {
        $created_order_ids[] = $renewal_order_id;
    }
    gwc_paypal_test_assert(
        $renewal_order_id > 0 && wc_get_order($renewal_order_id)->is_paid(),
        'Recurring-card renewal order was not marked paid after verified capture.'
    );

    $failed_due = $renewal_now->sub(new DateInterval('PT30M'));
    update_post_meta(
        $recurring_card_subscription_id,
        '_next_charge_date',
        $failed_due->setTimezone(wp_timezone())->format('Y-m-d H:i:s')
    );
    Growtype_Wc_Subscription::change_status(
        $recurring_card_subscription_id,
        Growtype_Wc_Subscription::STATUS_ACTIVE
    );
    $failed_marker_key = Growtype_Wc_Payment_Gateway_Paypal_Subscriptions::RECURRING_CARD_RENEWAL_OPTION_PREFIX
        . $recurring_card_subscription_id . '_' . $failed_due->format('YmdHis');
    $created_option_keys[] = $failed_marker_key;
    $failed_create_calls = 0;
    $recurring_card_failure_http = static function ($preempt, $args, $url) use (&$failed_create_calls) {
        if (str_ends_with($url, '/v2/checkout/orders')) {
            $failed_create_calls++;
            return new WP_Error('gwc_recurring_timeout', 'Ambiguous provider timeout.');
        }
        return $preempt;
    };
    add_filter('pre_http_request', $recurring_card_failure_http, PHP_INT_MAX, 3);
    $failed_renewal = $gateway->subscriptions->process_recurring_card_subscription(
        $recurring_card_subscription_id,
        $recurring_card_order,
        $renewal_now
    );
    $repeated_failed_renewal = $gateway->subscriptions->process_recurring_card_subscription(
        $recurring_card_subscription_id,
        $recurring_card_order,
        $renewal_now
    );
    remove_filter('pre_http_request', $recurring_card_failure_http, PHP_INT_MAX);
    gwc_paypal_test_assert(
        is_wp_error($failed_renewal)
        && is_wp_error($repeated_failed_renewal)
        && Growtype_Wc_Subscription::status($recurring_card_subscription_id) === 'on-hold',
        'An ambiguous recurring-card failure did not fail closed on hold.'
    );
    gwc_paypal_test_assert(
        $failed_create_calls === 1,
        'An ambiguous recurring-card failure was retried and risked a duplicate charge.'
    );
    $failed_marker = get_option($failed_marker_key);
    $failed_renewal_order_id = (int) ($failed_marker['renewal_order_id'] ?? 0);
    if ($failed_renewal_order_id > 0) {
        $created_order_ids[] = $failed_renewal_order_id;
    }

    $recurring_card_cancellation = $gateway->subscriptions->change_subscription_status(
        true,
        $recurring_card_subscription_id,
        Growtype_Wc_Subscription::STATUS_CANCELLED
    );
    gwc_paypal_test_assert(
        $recurring_card_cancellation === true
        && get_post_meta(
            $recurring_card_subscription_id,
            Growtype_Wc_Payment_Gateway_Paypal_Subscriptions::STATUS_META_KEY,
            true
        ) === 'MERCHANT_MANAGED_CANCELLED'
        && get_post_meta($recurring_card_subscription_id, '_cancelled_at', true) !== '',
        'Merchant-managed recurring card cancellation was not persisted.'
    );
    $recurring_card_reactivation = $gateway->subscriptions->change_subscription_status(
        true,
        $recurring_card_subscription_id,
        Growtype_Wc_Subscription::STATUS_ACTIVE
    );
    gwc_paypal_test_assert(
        is_wp_error($recurring_card_reactivation)
        && $recurring_card_reactivation->get_error_code() === 'paypal_recurring_card_reauthorization_required',
        'A cancelled recurring card subscription could be reactivated without new customer consent.'
    );

    // Provider failure must not change local status.
    $http_failure = static function () {
        return new WP_Error('gwc_test_http_failure', 'Intentional PayPal test failure.');
    };
    add_filter('pre_http_request', $http_failure, PHP_INT_MAX, 3);
    $cancel_result = $gateway->subscriptions->change_subscription_status(
        true,
        $local_subscription_id,
        Growtype_Wc_Subscription::STATUS_CANCELLED
    );
    remove_filter('pre_http_request', $http_failure, PHP_INT_MAX);
    gwc_paypal_test_assert(is_wp_error($cancel_result), 'Provider cancellation failure was reported as successful.');
    gwc_paypal_test_assert(
        Growtype_Wc_Subscription::status($local_subscription_id) === Growtype_Wc_Subscription::STATUS_ACTIVE,
        'Local subscription changed after provider cancellation failed.'
    );

    $http_success = static function ($preempt, $args, $url) {
        if (strpos($url, '/v1/oauth2/token') !== false) {
            return [
                'headers' => [],
                'body' => wp_json_encode(['access_token' => 'GWC-TEST-TOKEN', 'expires_in' => 300]),
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies' => [],
            ];
        }
        if (strpos($url, '/v1/billing/subscriptions/I-GWC-REGRESSION/cancel') !== false) {
            return [
                'headers' => [],
                'body' => '',
                'response' => ['code' => 204, 'message' => 'No Content'],
                'cookies' => [],
            ];
        }

        return new WP_Error('gwc_test_unexpected_http', 'Unexpected HTTP request in regression.');
    };
    add_filter('pre_http_request', $http_success, PHP_INT_MAX, 3);
    $cancel_result = $gateway->subscriptions->change_subscription_status(
        true,
        $local_subscription_id,
        Growtype_Wc_Subscription::STATUS_CANCELLED
    );
    remove_filter('pre_http_request', $http_success, PHP_INT_MAX);
    gwc_paypal_test_assert($cancel_result === true, 'Confirmed provider cancellation was rejected.');
    Growtype_Wc_Subscription::change_status($local_subscription_id, Growtype_Wc_Subscription::STATUS_CANCELLED);

    // A later webhook/reconciliation event for the same external cancellation
    // must be accepted idempotently without reopening or duplicating state.
    $reconcile_result = $gateway->subscriptions->reconcile_subscription(
        $local_subscription_id,
        [
            'id' => 'I-GWC-REGRESSION',
            'status' => 'CANCELLED',
            'status_update_time' => '2026-09-03T10:00:00Z',
            'status_change_note' => 'Regression cancellation',
        ],
        new DateTimeImmutable('2026-09-03 10:01:00', new DateTimeZone('UTC')),
        'paypal_webhook_status'
    );
    gwc_paypal_test_assert(($reconcile_result['outcome'] ?? '') === 'checked', 'Repeated external cancellation was not idempotent.');
    gwc_paypal_test_assert(
        Growtype_Wc_Subscription::status($local_subscription_id) === Growtype_Wc_Subscription::STATUS_CANCELLED,
        'External cancellation did not cancel the local subscription.'
    );

    // The same provider data must also correct a stale locally-active record.
    Growtype_Wc_Subscription::change_status($local_subscription_id, Growtype_Wc_Subscription::STATUS_ACTIVE);
    $reconcile_result = $gateway->subscriptions->reconcile_subscription(
        $local_subscription_id,
        [
            'id' => 'I-GWC-REGRESSION',
            'status' => 'CANCELLED',
            'status_update_time' => '2026-09-03T10:00:00Z',
            'status_change_note' => 'Regression cancellation',
        ],
        new DateTimeImmutable('2026-09-03 10:02:00', new DateTimeZone('UTC')),
        'paypal_reconciliation'
    );
    gwc_paypal_test_assert(($reconcile_result['outcome'] ?? '') === 'status_changed', 'Stale active subscription was not cancelled by reconciliation.');

    $status_map_extension = static function ($status_map) {
        $status_map['PAUSED'] = 'on-hold';
        return $status_map;
    };
    Growtype_Wc_Subscription::change_status($local_subscription_id, Growtype_Wc_Subscription::STATUS_ACTIVE);
    add_filter('growtype_wc_paypal_subscription_status_map', $status_map_extension, 10, 1);
    $reconcile_result = $gateway->subscriptions->reconcile_subscription(
        $local_subscription_id,
        [
            'id' => 'I-GWC-REGRESSION',
            'status' => 'PAUSED',
            'status_update_time' => '2026-09-03T10:02:30Z',
        ],
        new DateTimeImmutable('2026-09-03 10:02:31', new DateTimeZone('UTC')),
        'paypal_reconciliation'
    );
    remove_filter('growtype_wc_paypal_subscription_status_map', $status_map_extension, 10);
    gwc_paypal_test_assert(
        ($reconcile_result['local_status'] ?? '') === 'on-hold'
        && Growtype_Wc_Subscription::status($local_subscription_id) === 'on-hold',
        'An explicitly registered provider status mapping was not applied safely.'
    );

    // A queued benefit job must verify PayPal immediately before granting. This
    // closes the race between external cancellation and the daily reconciliation.
    Growtype_Wc_Subscription::change_status($local_subscription_id, Growtype_Wc_Subscription::STATUS_ACTIVE);
    $benefit_calls = 0;
    $benefit_listener = static function ($processed_subscription_id) use (&$benefit_calls, $local_subscription_id) {
        if ((int) $processed_subscription_id === $local_subscription_id) {
            $benefit_calls++;
        }
    };
    $cancelled_provider_response = static function ($preempt, $args, $url) {
        if (strpos($url, '/v1/billing/subscriptions/I-GWC-REGRESSION') !== false) {
            return [
                'headers' => [],
                'body' => wp_json_encode([
                    'id' => 'I-GWC-REGRESSION',
                    'status' => 'CANCELLED',
                    'status_update_time' => '2026-09-03T10:03:00Z',
                ]),
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies' => [],
            ];
        }

        return new WP_Error('gwc_test_unexpected_http', 'Unexpected HTTP request in benefit regression.');
    };
    add_action('growtype_wc_process_subscription', $benefit_listener, 10, 1);
    add_filter('pre_http_request', $cancelled_provider_response, PHP_INT_MAX, 3);
    require_once GROWTYPE_WC_PATH . 'includes/plugins/growtype-cron/jobs/Process_Subscription_Job.php';
    (new Process_Subscription_Job())->run([
        'payload' => wp_json_encode(['subscription_id' => $local_subscription_id]),
    ]);
    remove_filter('pre_http_request', $cancelled_provider_response, PHP_INT_MAX);
    remove_action('growtype_wc_process_subscription', $benefit_listener, 10);
    gwc_paypal_test_assert($benefit_calls === 0, 'A cancelled PayPal subscription received recurring benefits.');
    gwc_paypal_test_assert(
        Growtype_Wc_Subscription::status($local_subscription_id) === Growtype_Wc_Subscription::STATUS_CANCELLED,
        'Benefit-time verification did not correct stale local subscription state.'
    );

    echo "PASS: PayPal payment and subscription safety regression\n";
} finally {
    if (is_callable($plan_http_mock)) {
        remove_filter('pre_http_request', $plan_http_mock, PHP_INT_MAX);
    }
    if ($plan_option_key !== '') {
        if ($previous_plan_option_exists) {
            update_option($plan_option_key, $previous_plan_option, false);
        } else {
            delete_option($plan_option_key);
        }
    }
    foreach ($created_subscription_ids as $subscription_id) {
        wp_delete_post((int) $subscription_id, true);
    }
    foreach ($created_order_ids as $order_id) {
        $order = wc_get_order((int) $order_id);
        if ($order) {
            $order->delete(true);
        }
    }
    foreach ($created_product_ids as $product_id) {
        wp_delete_post((int) $product_id, true);
    }
    foreach ($created_option_keys as $option_key) {
        delete_option($option_key);
    }
}
