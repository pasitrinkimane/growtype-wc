<?php

/**
 * PayPal Subscriptions (Billing API) implementation.
 */
class Growtype_Wc_Payment_Gateway_Paypal_Subscriptions
{
    const STATUS_META_KEY = '_paypal_subscription_status';
    const STATUS_CHECKED_AT_META_KEY = '_paypal_subscription_status_checked_at';
    const STATUS_UPDATED_AT_META_KEY = '_paypal_subscription_status_updated_at';
    const STATUS_REASON_META_KEY = '_paypal_subscription_status_reason';
    const STATUS_CHECK_SOURCE_META_KEY = '_paypal_subscription_status_check_source';
    const STATUS_CHECK_ERROR_META_KEY = '_paypal_subscription_status_check_error';
    const STATUS_CHECK_FAILED_AT_META_KEY = '_paypal_subscription_status_check_failed_at';
    const CANCELLATION_SOURCE_META_KEY = '_cancellation_source';
    const CANCELLATION_REASON_META_KEY = '_cancellation_reason';
    const CANCELLED_AT_META_KEY = '_cancelled_at';
    const PROVIDER_INACTIVE_AT_META_KEY = '_provider_inactive_at';
    const RECURRING_CARD_VERIFIED_META_KEY = '_paypal_recurring_vault_verified';
    const RECURRING_CARD_RENEWAL_OPTION_PREFIX = 'gwc_pp_recurring_card_renewal_';

    /** @var Growtype_Wc_Payment_Gateway_Paypal */
    private $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;

        // Provider changes must succeed before the account handler changes local state.
        add_filter(
            "growtype_wc_pre_change_subscription_status",
            [$this, "change_subscription_status"],
            10,
            3,
        );
        add_filter(
            'growtype_wc_can_activate_subscription_for_order',
            [$this, 'can_activate_local_subscription'],
            10,
            2
        );
        add_filter(
            'growtype_wc_can_process_subscription_benefits',
            [$this, 'can_process_subscription_benefits'],
            10,
            3
        );

        add_filter('manage_growtype_wc_subs_posts_columns', [$this, 'add_admin_columns'], 20);
        add_action('manage_growtype_wc_subs_posts_custom_column', [$this, 'render_admin_column'], 20, 2);
        add_action('add_meta_boxes_growtype_wc_subs', [$this, 'add_admin_meta_box']);
    }

    public function create_product($access_token, $wc_product_id)
    {
        $wc_product = wc_get_product($wc_product_id);

        $paypal_product_url = $this->gateway->get_api_url(
            "/v1/catalogs/products",
        );

        $headers = [
            "Authorization" => "Bearer " . $access_token,
            "Content-Type" => "application/json",
        ];

        $wc_product_name = $wc_product->get_name();
        $wc_product_name = sanitize_text_field($wc_product_name);

        $wc_product_description = $wc_product->get_short_description();
        $wc_product_description = sanitize_text_field($wc_product_description);

        $body = wp_json_encode([
            "name" => !empty($wc_product_name)
                ? $wc_product_name
                : "Wc product",
            "description" => !empty($wc_product_description)
                ? $wc_product_description
                : "Wc product description",
            "type" => "SERVICE",
            "category" => "SOFTWARE",
        ]);

        $response = wp_remote_post($paypal_product_url, [
            "headers" => $headers,
            "body" => $body,
            "timeout" => 15,
        ]);

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true) ?: [];

        return $data;
    }

    public function create_billing_plan(
        $access_token,
        $paypal_product,
        $wc_product_id,
        $applied_coupons = null,
    ) {
        $plan_url = $this->gateway->get_api_url("/v1/billing/plans");

        $plan_headers = [
            "Authorization" => "Bearer " . $access_token,
            "Content-Type" => "application/json",
        ];

        $billing_cycles = [];
        $billing_sequence = 1;

        if (growtype_wc_product_is_trial($wc_product_id)) {
            $billing_cycles[] = [
                "frequency" => [
                    "interval_unit" => growtype_wc_get_trial_period(
                        $wc_product_id,
                    ),
                    "interval_count" => growtype_wc_get_trial_duration(
                        $wc_product_id,
                    ),
                ],
                "tenure_type" => "TRIAL",
                "sequence" => $billing_sequence,
                "total_cycles" => 1,
                "pricing_scheme" => [
                    "fixed_price" => [
                        "value" => growtype_wc_get_trial_price($wc_product_id),
                        "currency_code" => get_woocommerce_currency(),
                    ],
                ],
            ];

            $billing_sequence++;
        }

        if (!empty($applied_coupons)) {
            $product = wc_get_product($wc_product_id);
            $sale_price = $product->get_sale_price();

            $billing_cycles[] = [
                "frequency" => [
                    "interval_unit" => "MONTH",
                    "interval_count" => 1,
                ],
                "tenure_type" => "TRIAL",
                "sequence" => $billing_sequence,
                "total_cycles" => 1,
                "pricing_scheme" => [
                    "fixed_price" => [
                        "value" => growtype_wc_price_apply_coupon_discount(
                            $wc_product_id,
                            $sale_price,
                            $applied_coupons,
                        ),
                        "currency_code" => get_woocommerce_currency(),
                    ],
                ],
            ];

            $billing_sequence++;
        }

        if (growtype_wc_product_is_subscription($wc_product_id)) {
            $billing_cycles[] = [
                "frequency" => [
                    "interval_unit" => growtype_wc_get_subscription_period(
                        $wc_product_id,
                    ),
                    "interval_count" => growtype_wc_get_subscription_duration(
                        $wc_product_id,
                    ),
                ],
                "tenure_type" => "REGULAR",
                "sequence" => $billing_sequence,
                "total_cycles" => 0,
                "pricing_scheme" => [
                    "fixed_price" => [
                        "value" => growtype_wc_get_subscription_price(
                            $wc_product_id,
                        ),
                        "currency_code" => get_woocommerce_currency(),
                    ],
                ],
            ];
        }

        $plan_details = [
            "product_id" => $paypal_product["id"],
            "name" => $paypal_product["name"],
            "description" => $paypal_product["description"],
            "status" => "ACTIVE",
            "billing_cycles" => $billing_cycles,
            "payment_preferences" => [
                "auto_bill_outstanding" => true,
                "setup_fee" => [
                    "value" => "0",
                    "currency_code" => get_woocommerce_currency(),
                ],
                "setup_fee_failure_action" => "CONTINUE",
                "payment_failure_threshold" => 3,
            ],
        ];

        $plan_body = wp_json_encode($plan_details);

        $response = wp_remote_post($plan_url, [
            "headers" => $plan_headers,
            "body" => $plan_body,
            "timeout" => 15,
        ]);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true) ?: [];

        return $data;
    }

    /**
     * Return a cached PayPal billing plan ID for the given WC product + coupon set,
     * creating a new PayPal product + plan only when no valid cached entry exists.
     *
     * Cache key: 'gwc_paypal_plan_<product_id>_<md5 of sorted coupon codes>'
     * Stored in wp_options (autoload=no). Evicted if the API returns an error.
     */
    public function get_or_create_plan(
        string $access_token,
        int $wc_product_id,
        array $applied_coupons = [],
    ): string {
        sort($applied_coupons);
        // Include trial settings in the cache key so changing trial parameters
        // (price/period/duration) automatically invalidates the cached plan.
        $trial_sig = '';
        if (growtype_wc_product_is_trial($wc_product_id)) {
            $trial_sig = '_t' . growtype_wc_get_trial_price($wc_product_id)
                . '_' . growtype_wc_get_trial_period($wc_product_id)
                . '_' . growtype_wc_get_trial_duration($wc_product_id);
        }
        $coupon_sig = md5(implode(",", $applied_coupons));
        $option_key = "gwc_paypal_plan_{$wc_product_id}_{$coupon_sig}{$trial_sig}";

        $cached = get_option($option_key, "");
        if (!empty($cached)) {
            $cached_plan = $this->fetch_billing_plan($access_token, (string) $cached);
            if (is_wp_error($cached_plan)) {
                if ($cached_plan->get_error_code() === 'paypal_billing_plan_not_found') {
                    delete_option($option_key);
                } else {
                    error_log(sprintf(
                        '[GWC PayPal] Cached billing plan %s could not be verified for product %d: %s',
                        $cached,
                        $wc_product_id,
                        $cached_plan->get_error_message()
                    ));
                    return '';
                }
            } elseif ($this->billing_plan_matches_product($cached_plan, (string) $cached, $wc_product_id)) {
                error_log(sprintf(
                    "[GWC PayPal] Reusing verified billing plan %s for product %d (coupons: %s)",
                    $cached,
                    $wc_product_id,
                    $coupon_sig,
                ));
                return (string) $cached;
            } else {
                error_log(sprintf(
                    '[GWC PayPal] Evicting cached billing plan %s because it is inactive or no longer matches product %d.',
                    $cached,
                    $wc_product_id
                ));
                delete_option($option_key);
            }
        }

        error_log(
            sprintf(
                "[GWC PayPal] No cached plan for product %d (coupons: %s) — creating new PayPal product + plan.",
                $wc_product_id,
                $coupon_sig,
            ),
        );

        $paypal_product = $this->create_product($access_token, $wc_product_id);
        $subscription_plan = $this->create_billing_plan(
            $access_token,
            $paypal_product,
            $wc_product_id,
            !empty($applied_coupons) ? $applied_coupons : null,
        );
        $plan_id = $subscription_plan["id"] ?? "";

        if (empty($plan_id)) {
            error_log(
                sprintf(
                    "[GWC PayPal] Billing plan creation failed for product %d. Response: %s",
                    $wc_product_id,
                    wp_json_encode($subscription_plan),
                ),
            );
            return "";
        }

        // Persist the provider ID only. Every checkout verifies that PayPal still
        // has an active plan matching the current local billing configuration.
        update_option($option_key, $plan_id, false); // autoload=false
        error_log(
            sprintf(
                "[GWC PayPal] Created and cached billing plan %s for product %d (coupons: %s)",
                $plan_id,
                $wc_product_id,
                $coupon_sig,
            ),
        );

        return $plan_id;
    }

    public function fetch_billing_plan(string $access_token, string $plan_id)
    {
        $response = wp_remote_get(
            $this->gateway->get_api_url('/v1/billing/plans/' . rawurlencode($plan_id)),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 15,
            ]
        );
        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($status_code === 404) {
            return new WP_Error('paypal_billing_plan_not_found', __('The cached PayPal billing plan no longer exists.', 'growtype-wc'));
        }
        if ($status_code < 200 || $status_code >= 300 || !is_array($data)) {
            return new WP_Error('paypal_billing_plan_check_failed', __('PayPal could not verify the cached billing plan.', 'growtype-wc'));
        }

        return $data;
    }

    public function billing_plan_matches_product(array $plan, string $plan_id, int $wc_product_id): bool
    {
        if (
            trim((string) ($plan['id'] ?? '')) !== $plan_id
            || strtoupper(trim((string) ($plan['status'] ?? ''))) !== 'ACTIVE'
        ) {
            return false;
        }

        $regular_cycle = null;
        foreach ((array) ($plan['billing_cycles'] ?? []) as $billing_cycle) {
            if (strtoupper((string) ($billing_cycle['tenure_type'] ?? '')) === 'REGULAR') {
                $regular_cycle = $billing_cycle;
                break;
            }
        }
        if (!is_array($regular_cycle)) {
            return false;
        }

        $expected_price_raw = trim((string) growtype_wc_get_subscription_price($wc_product_id));
        $provider_price_raw = trim((string) ($regular_cycle['pricing_scheme']['fixed_price']['value'] ?? ''));
        if ($expected_price_raw === '' || $provider_price_raw === '' || !is_numeric($provider_price_raw)) {
            return false;
        }
        $expected_price = wc_format_decimal($expected_price_raw, 2);
        $provider_price = wc_format_decimal($provider_price_raw, 2);

        return strtoupper((string) ($regular_cycle['frequency']['interval_unit'] ?? ''))
                === strtoupper((string) growtype_wc_get_subscription_period($wc_product_id))
            && (int) ($regular_cycle['frequency']['interval_count'] ?? 0)
                === (int) growtype_wc_get_subscription_duration($wc_product_id)
            && strtoupper((string) ($regular_cycle['pricing_scheme']['fixed_price']['currency_code'] ?? ''))
                === strtoupper((string) get_woocommerce_currency())
            && $provider_price === $expected_price;
    }

    public function create_subscription(
        $access_token,
        $plan_id,
        $order_id,
        $applied_coupons = null,
    ) {
        $subscription_url = $this->gateway->get_api_url(
            "/v1/billing/subscriptions",
        );

        $order = wc_get_order($order_id);
        $customer = $order->get_user();
        $current_user = wp_get_current_user();

        $given_name = $customer
            ? $customer->get_first_name()
            : $order->get_billing_first_name();
        $given_name = !empty($given_name)
            ? $given_name
            : $order->get_shipping_first_name();
        $given_name =
            empty($given_name) && !empty($current_user)
                ? $current_user->first_name
                : $given_name;

        $surname = $customer
            ? $customer->get_last_name()
            : $order->get_billing_last_name();
        $surname = !empty($surname)
            ? $surname
            : $order->get_shipping_last_name();
        $surname =
            empty($surname) && !empty($current_user)
                ? $current_user->last_name
                : $surname;

        $email = $customer
            ? $customer->get_email()
            : $order->get_billing_email();
        $email =
            empty($email) && !empty($current_user)
                ? $current_user->user_email
                : $email;
        $email = empty($email)
            ? Growtype_Wc_Payment_Gateway::resolve_user_email()
            : $email;

        $requires_shipping = false;
        foreach ($order->get_items() as $item_id => $item) {
            $wc_product = $item->get_product();
            if ($wc_product->needs_shipping()) {
                $requires_shipping = true;
                break;
            }
        }

        $shipping_preference = $requires_shipping
            ? "SET_PROVIDED_ADDRESS"
            : "NO_SHIPPING";

        $shipping_details = [];
        if ($requires_shipping) {
            $shipping_details = [
                "name" => [
                    "full_name" =>
                        $order->get_shipping_first_name() .
                        " " .
                        $order->get_shipping_last_name(),
                ],
                "address" => [
                    "address_line_1" => $order->get_shipping_address_1(),
                    "address_line_2" => $order->get_shipping_address_2(),
                    "admin_area_2" => $order->get_shipping_city(),
                    "admin_area_1" => $order->get_shipping_state(),
                    "postal_code" => $order->get_shipping_postcode(),
                    "country_code" => $order->get_shipping_country(),
                ],
            ];
        }

        $subscriber_data = [
            "name" => [
                "given_name" => $given_name,
                "surname" => $surname,
            ],
        ];

        if (!empty($email)) {
            $subscriber_data["email_address"] = $email;
        }

        if ($requires_shipping && !empty($shipping_details)) {
            $subscriber_data["shipping_address"] = $shipping_details;
        }

        $cancel_url = Growtype_Wc_Payment_Gateway::cancel_url(
            $order_id,
            false,
            $applied_coupons,
        );

        $subscription_data = [
            "plan_id" => $plan_id,
            "subscriber" => $subscriber_data,
            "application_context" => [
                "brand_name" => get_bloginfo("name"),
                "locale" => "en-US",
                "shipping_preference" => $shipping_preference,
                "user_action" => "SUBSCRIBE_NOW",
                "return_url" => Growtype_Wc_Payment_Gateway::success_url(
                    $order_id,
                ),
                "cancel_url" => $cancel_url,
            ],
            "description" => "Subscription plan",
            "invoice_id" => $order_id,
        ];

        $subscription_body = wp_json_encode($subscription_data);
        $subscription_args = [
            "headers" => [
                "Authorization" => "Bearer " . $access_token,
                "Content-Type" => "application/json",
                // Stable for this checkout so a timeout/retry cannot create a
                // second PayPal Billing Subscription.
                "PayPal-Request-Id" => "gwc-subscription-" . (int) $order_id,
            ],
            "body" => $subscription_body,
            "timeout" => 15,
        ];

        $response = wp_remote_post($subscription_url, $subscription_args);
        $body = wp_remote_retrieve_body($response);

        return json_decode($body, true) ?: [];
    }

    public function change_subscription_status($allowed, $subscription_id, $status)
    {
        if (is_wp_error($allowed) || $allowed === false) {
            return $allowed;
        }

        $subscription_id = (int) $subscription_id;
        $status = sanitize_key((string) $status);
        $order_id = (int) get_post_meta($subscription_id, "_order_id", true);

        if ($order_id <= 0) {
            // No order means there is no PayPal billing agreement this gateway
            // can act on. Preserve legacy local-only status changes.
            return $allowed;
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order || $order->get_payment_method() !== $this->gateway->id) {
            return $allowed;
        }

        $paypal_subscription_id = trim((string) $order->get_meta('paypal_subscription_id'));
        if ($paypal_subscription_id === '' && $this->is_recurring_vault_order($order)) {
            if ($status === Growtype_Wc_Subscription::STATUS_CANCELLED) {
                $now = gmdate('Y-m-d H:i:s');
                update_post_meta($subscription_id, self::STATUS_META_KEY, 'MERCHANT_MANAGED_CANCELLED');
                update_post_meta($subscription_id, self::STATUS_CHECKED_AT_META_KEY, $now);
                update_post_meta($subscription_id, self::STATUS_UPDATED_AT_META_KEY, $now);
                update_post_meta($subscription_id, self::CANCELLED_AT_META_KEY, $now);
                update_post_meta($subscription_id, self::PROVIDER_INACTIVE_AT_META_KEY, $now);
                update_post_meta($subscription_id, self::CANCELLATION_SOURCE_META_KEY, 'customer_request');
                update_post_meta($subscription_id, self::CANCELLATION_REASON_META_KEY, 'Customer stopped merchant-managed recurring billing.');
                $order->add_order_note(sprintf(
                    __('Merchant-managed recurring billing stopped for local subscription #%d.', 'growtype-wc'),
                    $subscription_id
                ));
                $order->save();

                return $allowed;
            }

            if (
                $status === Growtype_Wc_Subscription::STATUS_ACTIVE
                && (
                    Growtype_Wc_Subscription::status($subscription_id) === Growtype_Wc_Subscription::STATUS_CANCELLED
                    || trim((string) get_post_meta($subscription_id, self::CANCELLED_AT_META_KEY, true)) !== ''
                )
            ) {
                return new WP_Error(
                    'paypal_recurring_card_reauthorization_required',
                    __('A cancelled recurring subscription requires a new customer-authorized checkout.', 'growtype-wc')
                );
            }
        }

        // Legacy one-time PayPal captures have no external billing agreement or
        // verified merchant-managed schedule. A local cancellation cannot leave
        // PayPal continuing to bill them.
        if ($paypal_subscription_id === '') {
            return $allowed;
        }

        if (!in_array($status, [Growtype_Wc_Subscription::STATUS_CANCELLED, Growtype_Wc_Subscription::STATUS_ACTIVE], true)) {
            return new WP_Error(
                'paypal_subscription_status_invalid',
                __('This subscription status change is not supported.', 'growtype-wc')
            );
        }

        $access_token = $this->gateway->get_access_token(
            $this->gateway->get_client_id(),
            $this->gateway->get_client_secret()
        );
        if (empty($access_token)) {
            return new WP_Error(
                'paypal_subscription_access_token_missing',
                __('PayPal could not be reached. The subscription was not changed.', 'growtype-wc')
            );
        }

        $result = $status === Growtype_Wc_Subscription::STATUS_CANCELLED
            ? $this->cancel_paypal_subscription($access_token, $paypal_subscription_id)
            : $this->resume_paypal_subscription($access_token, $paypal_subscription_id);

        if (is_wp_error($result)) {
            return $result;
        }

        $now = gmdate('Y-m-d H:i:s');
        $provider_status = $status === Growtype_Wc_Subscription::STATUS_CANCELLED ? 'CANCELLED' : 'ACTIVE';
        update_post_meta($subscription_id, self::STATUS_META_KEY, $provider_status);
        update_post_meta($subscription_id, self::STATUS_CHECKED_AT_META_KEY, $now);
        update_post_meta($subscription_id, self::STATUS_UPDATED_AT_META_KEY, $now);
        delete_post_meta($subscription_id, self::STATUS_CHECK_ERROR_META_KEY);
        delete_post_meta($subscription_id, self::STATUS_CHECK_FAILED_AT_META_KEY);

        if ($status === Growtype_Wc_Subscription::STATUS_CANCELLED) {
            update_post_meta($subscription_id, self::CANCELLED_AT_META_KEY, $now);
            update_post_meta($subscription_id, self::PROVIDER_INACTIVE_AT_META_KEY, $now);
            update_post_meta($subscription_id, self::CANCELLATION_SOURCE_META_KEY, 'customer_request');
            update_post_meta($subscription_id, self::CANCELLATION_REASON_META_KEY, 'Customer requested cancellation.');
        }

        $order->add_order_note(sprintf(
            __('PayPal subscription %1$s confirmed for billing agreement %2$s.', 'growtype-wc'),
            strtolower($provider_status),
            $paypal_subscription_id
        ));
        $order->save();

        return true;
    }

    public function can_activate_local_subscription($allowed, $order)
    {
        if (is_wp_error($allowed) || $allowed === false) {
            return $allowed;
        }

        if (!$order instanceof WC_Order || $order->get_payment_method() !== $this->gateway->id) {
            return $allowed;
        }

        if ($this->is_recurring_vault_order($order)) {
            if (
                $order->get_meta(self::RECURRING_CARD_VERIFIED_META_KEY) !== 'yes'
                || trim((string) $order->get_meta('paypal_vault_id')) === ''
                || trim((string) $order->get_meta('paypal_customer_id')) === ''
                || trim((string) $order->get_meta('_paypal_capture_id')) === ''
            ) {
                return new WP_Error(
                    'paypal_recurring_card_not_verified',
                    __('PayPal recurring billing was not verified.', 'growtype-wc')
                );
            }

            return true;
        }

        if (
            trim((string) $order->get_meta('paypal_subscription_id')) === ''
            || $order->get_meta('_paypal_subscription_verified_active') !== 'yes'
        ) {
            return new WP_Error(
                'paypal_recurring_billing_not_verified',
                __('PayPal recurring billing was not verified.', 'growtype-wc')
            );
        }

        return true;
    }

    public function can_process_subscription_benefits($allowed, int $subscription_id, $order)
    {
        if (is_wp_error($allowed) || $allowed === false) {
            return $allowed;
        }

        if (!$order instanceof WC_Order || $order->get_payment_method() !== $this->gateway->id) {
            return $allowed;
        }

        if ($this->is_recurring_vault_order($order)) {
            return $this->process_recurring_card_subscription($subscription_id, $order);
        }

        if (trim((string) $order->get_meta('paypal_subscription_id')) === '') {
            return new WP_Error(
                'paypal_subscription_not_linked',
                __('The PayPal subscription is not linked to recurring billing.', 'growtype-wc')
            );
        }

        $reconciliation = $this->reconcile_subscription($subscription_id);
        if (
            strtoupper((string) ($reconciliation['provider_status'] ?? '')) !== 'ACTIVE'
            || Growtype_Wc_Subscription::status($subscription_id) !== Growtype_Wc_Subscription::STATUS_ACTIVE
        ) {
            return new WP_Error(
                'paypal_subscription_not_active',
                __('PayPal did not verify an active subscription.', 'growtype-wc')
            );
        }

        return true;
    }

    public function is_recurring_vault_order($order): bool
    {
        if (!$order instanceof WC_Order || $order->get_payment_method() !== $this->gateway->id) {
            return false;
        }

        $checkout_flow = (string) $order->get_meta('_growtype_wc_checkout_flow');
        $vault_source = (string) $order->get_meta('_paypal_vault_source');

        return (
            $checkout_flow === Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_ORDERS_API_RECURRING_CARD
            && $vault_source === 'card'
        ) || (
            $checkout_flow === Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_ORDERS_API_RECURRING_APPLE_PAY
            && $vault_source === 'applepay'
        );
    }

    public function is_recurring_card_order($order): bool
    {
        return $this->is_recurring_vault_order($order)
            && $order->get_meta('_paypal_vault_source') === 'card';
    }

    /**
     * Charge one due merchant-managed card renewal before recurring benefits run.
     * Every billing date has one durable claim and one deterministic PayPal request
     * ID. Ambiguous failures move the subscription on hold instead of retrying a
     * charge whose provider outcome is unknown.
     */
    public function process_recurring_card_subscription(
        int $subscription_id,
        $parent_order,
        ?DateTimeImmutable $now = null
    ) {
        if (
            !$this->is_recurring_vault_order($parent_order)
            || get_post_type($subscription_id) !== 'growtype_wc_subs'
            || Growtype_Wc_Subscription::status($subscription_id) !== Growtype_Wc_Subscription::STATUS_ACTIVE
        ) {
            return new WP_Error(
                'paypal_recurring_card_invalid_subscription',
                __('The recurring card subscription is not active or valid.', 'growtype-wc')
            );
        }

        if (
            $parent_order->get_meta(self::RECURRING_CARD_VERIFIED_META_KEY) !== 'yes'
            || trim((string) $parent_order->get_meta('paypal_vault_id')) === ''
            || trim((string) $parent_order->get_meta('paypal_customer_id')) === ''
        ) {
            return $this->hold_recurring_card_subscription(
                $subscription_id,
                $parent_order,
                __('The recurring PayPal card token is missing or unverified.', 'growtype-wc')
            );
        }

        $now = ($now ?: new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $next_charge = $this->parse_local_subscription_date(
            (string) get_post_meta($subscription_id, '_next_charge_date', true)
        );
        if (!$next_charge) {
            return $this->hold_recurring_card_subscription(
                $subscription_id,
                $parent_order,
                __('The next recurring charge date is invalid.', 'growtype-wc')
            );
        }

        if ($now < $next_charge) {
            return true;
        }

        $due_key = $next_charge->format('YmdHis');
        $marker_key = self::RECURRING_CARD_RENEWAL_OPTION_PREFIX . $subscription_id . '_' . $due_key;
        $request_id = 'gwc-r-' . substr(hash('sha256', $marker_key), 0, 30);
        $marker = get_option($marker_key, null);
        if (is_array($marker)) {
            if (($marker['status'] ?? '') === 'completed') {
                $this->apply_completed_recurring_card_dates($subscription_id, $marker);
                return true;
            }

            $claimed_at = isset($marker['claimed_at']) ? strtotime((string) $marker['claimed_at'] . ' UTC') : false;
            if (($marker['status'] ?? '') === 'claimed' && $claimed_at && $claimed_at > (time() - 300)) {
                return new WP_Error(
                    'paypal_recurring_card_charge_in_progress',
                    __('The recurring card charge is already being processed.', 'growtype-wc')
                );
            }

            return $this->hold_recurring_card_subscription(
                $subscription_id,
                $parent_order,
                __('A previous recurring charge has an uncertain result and requires review.', 'growtype-wc')
            );
        }

        $marker = [
            'status' => 'claimed',
            'subscription_id' => $subscription_id,
            'parent_order_id' => (int) $parent_order->get_id(),
            'due_at' => $next_charge->format('Y-m-d H:i:s'),
            'claimed_at' => $now->format('Y-m-d H:i:s'),
            'request_id' => $request_id,
        ];
        if (!add_option($marker_key, $marker, '', false)) {
            return new WP_Error(
                'paypal_recurring_card_claim_conflict',
                __('The recurring card charge was already claimed by another worker.', 'growtype-wc')
            );
        }

        $renewal_order = null;
        try {
            $renewal_order = $this->create_recurring_card_renewal_order(
                $subscription_id,
                $parent_order,
                $next_charge
            );
            if (is_wp_error($renewal_order)) {
                throw new RuntimeException($renewal_order->get_error_message());
            }

            $marker['renewal_order_id'] = (int) $renewal_order->get_id();
            update_option($marker_key, $marker, false);

            $access_token = $this->gateway->get_access_token(
                $this->gateway->get_client_id(),
                $this->gateway->get_client_secret()
            );
            if (empty($access_token)) {
                throw new RuntimeException(__('PayPal access token could not be obtained.', 'growtype-wc'));
            }

            $provider_order = $this->gateway->orders->create_recurring_vault_order(
                $access_token,
                trim((string) $parent_order->get_meta('paypal_vault_id')),
                trim((string) $parent_order->get_meta('paypal_customer_id')),
                $renewal_order,
                $request_id,
                (string) $parent_order->get_meta('_paypal_vault_source')
            );
            if (is_wp_error($provider_order)) {
                throw new RuntimeException($provider_order->get_error_message());
            }

            $paypal_order_id = trim((string) ($provider_order['id'] ?? ''));
            $marker['paypal_order_id'] = $paypal_order_id;
            $marker['provider_create_status'] = sanitize_key((string) ($provider_order['status'] ?? ''));
            update_option($marker_key, $marker, false);

            $capture_result = $provider_order;
            if (strtoupper((string) ($provider_order['status'] ?? '')) !== 'COMPLETED') {
                $capture_result = $this->gateway->capture_order(
                    $access_token,
                    $paypal_order_id,
                    $request_id . '-c'
                );
                if (
                    strtoupper((string) ($capture_result['status'] ?? '')) !== 'COMPLETED'
                    && strtoupper((string) ($capture_result['name'] ?? '')) === 'UNPROCESSABLE_ENTITY'
                ) {
                    $capture_result = $this->gateway->get_order_data($access_token, $paypal_order_id);
                }
            }

            $capture_id = $this->verified_completed_capture_id($capture_result, $renewal_order);
            if (is_wp_error($capture_id)) {
                throw new RuntimeException($capture_id->get_error_message());
            }

            $renewal_order->update_meta_data('_paypal_hosted_order_id', $paypal_order_id);
            $renewal_order->update_meta_data('_paypal_capture_id', $capture_id);
            $renewal_order->save();
            $renewal_order->payment_complete($capture_id);

            $following_charge = $this->following_charge_after($next_charge, $now, $subscription_id);
            if (is_wp_error($following_charge)) {
                throw new RuntimeException($following_charge->get_error_message());
            }

            $marker['status'] = 'completed';
            $marker['capture_id'] = $capture_id;
            $marker['completed_at'] = gmdate('Y-m-d H:i:s');
            $marker['next_charge_date'] = $following_charge
                ->setTimezone(wp_timezone())
                ->format('Y-m-d H:i:s');
            update_option($marker_key, $marker, false);
            $this->apply_completed_recurring_card_dates($subscription_id, $marker);

            update_post_meta($subscription_id, self::STATUS_META_KEY, 'MERCHANT_MANAGED_ACTIVE');
            update_post_meta($subscription_id, self::STATUS_CHECKED_AT_META_KEY, gmdate('Y-m-d H:i:s'));
            update_post_meta($subscription_id, '_paypal_recurring_card_last_capture_id', $capture_id);
            update_post_meta($subscription_id, '_paypal_recurring_card_last_payment_at', gmdate('Y-m-d H:i:s'));
            delete_post_meta($subscription_id, '_paypal_recurring_card_payment_error');
            delete_post_meta($subscription_id, '_paypal_recurring_card_payment_failed_at');

            return true;
        } catch (Throwable $exception) {
            $marker['status'] = 'review_required';
            $marker['failed_at'] = gmdate('Y-m-d H:i:s');
            $marker['error'] = substr(sanitize_text_field($exception->getMessage()), 0, 500);
            update_option($marker_key, $marker, false);
            if ($renewal_order instanceof WC_Order && !$renewal_order->is_paid()) {
                $renewal_order->update_status('on-hold', $marker['error']);
            }

            return $this->hold_recurring_card_subscription(
                $subscription_id,
                $parent_order,
                $marker['error']
            );
        }
    }

    private function create_recurring_card_renewal_order(
        int $subscription_id,
        WC_Order $parent_order,
        DateTimeImmutable $due_at
    ) {
        $amount = (float) get_post_meta($subscription_id, '_price', true);
        if ($amount <= 0) {
            return new WP_Error(
                'paypal_recurring_card_invalid_amount',
                __('The recurring card renewal amount is invalid.', 'growtype-wc')
            );
        }

        $order = wc_create_order(['customer_id' => (int) $parent_order->get_customer_id()]);
        if (!$order instanceof WC_Order) {
            return new WP_Error(
                'paypal_recurring_card_order_failed',
                __('The recurring card renewal order could not be created.', 'growtype-wc')
            );
        }

        $order->set_currency($parent_order->get_currency());
        $order->set_payment_method($this->gateway->id);
        $order->set_payment_method_title($this->gateway->get_hosted_fields_title());
        $order->set_address($parent_order->get_address('billing'), 'billing');
        $order->update_meta_data('_growtype_wc_is_subscription_renewal', 'yes');
        $order->update_meta_data('_growtype_wc_subscription_id', $subscription_id);
        $order->update_meta_data('parent_order_id', (int) $parent_order->get_id());
        $order->update_meta_data('_growtype_wc_subscription_due_at', $due_at->format('Y-m-d H:i:s'));

        $fee = new WC_Order_Item_Fee();
        $fee->set_name(sprintf(__('Subscription #%d renewal', 'growtype-wc'), $subscription_id));
        $fee->set_amount($amount);
        $fee->set_total($amount);
        $order->add_item($fee);
        $order->calculate_totals(false);
        $order->save();

        return $order;
    }

    private function verified_completed_capture_id(array $provider_data, WC_Order $order)
    {
        if (strtoupper((string) ($provider_data['status'] ?? '')) !== 'COMPLETED') {
            return new WP_Error(
                'paypal_recurring_card_not_completed',
                __('PayPal did not complete the recurring card charge.', 'growtype-wc')
            );
        }

        foreach (($provider_data['purchase_units'] ?? []) as $purchase_unit) {
            if (trim((string) ($purchase_unit['invoice_id'] ?? '')) !== (string) $order->get_id()) {
                continue;
            }
            foreach (($purchase_unit['payments']['captures'] ?? []) as $capture) {
                if (
                    strtoupper((string) ($capture['status'] ?? '')) === 'COMPLETED'
                    && !empty($capture['id'])
                    && isset($capture['amount']['value'])
                    && abs((float) $capture['amount']['value'] - (float) $order->get_total()) < 0.00001
                    && strtoupper((string) ($capture['amount']['currency_code'] ?? '')) === strtoupper($order->get_currency())
                ) {
                    return sanitize_text_field((string) $capture['id']);
                }
            }
        }

        return new WP_Error(
            'paypal_recurring_card_capture_mismatch',
            __('PayPal recurring charge details did not match the renewal order.', 'growtype-wc')
        );
    }

    private function parse_local_subscription_date(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, wp_timezone());
        return $date ? $date->setTimezone(new DateTimeZone('UTC')) : null;
    }

    private function following_charge_after(
        DateTimeImmutable $due_at,
        DateTimeImmutable $now,
        int $subscription_id
    ) {
        $duration = max(1, (int) get_post_meta($subscription_id, '_duration', true));
        $period = sanitize_key((string) get_post_meta($subscription_id, '_period', true));
        $interval_spec = [
            'day' => 'P%dD',
            'week' => 'P%dW',
            'month' => 'P%dM',
            'year' => 'P%dY',
        ][$period] ?? '';
        if ($interval_spec === '') {
            return new WP_Error(
                'paypal_recurring_card_invalid_period',
                __('The recurring card billing period is invalid.', 'growtype-wc')
            );
        }

        $interval = new DateInterval(sprintf($interval_spec, $duration));
        $next = $due_at->add($interval);
        while ($next <= $now) {
            // Never issue catch-up charges in a burst. One successful payment
            // advances the schedule to the first future cycle.
            $next = $next->add($interval);
        }

        return $next;
    }

    private function apply_completed_recurring_card_dates(int $subscription_id, array $marker): void
    {
        $next_charge_date = sanitize_text_field((string) ($marker['next_charge_date'] ?? ''));
        if ($next_charge_date === '') {
            return;
        }

        update_post_meta($subscription_id, '_next_charge_date', $next_charge_date);
        update_post_meta($subscription_id, '_end_date', $next_charge_date);
    }

    private function hold_recurring_card_subscription(
        int $subscription_id,
        WC_Order $parent_order,
        string $reason
    ) {
        $reason = substr(sanitize_text_field($reason), 0, 500);
        Growtype_Wc_Subscription::change_status($subscription_id, 'on-hold');
        update_post_meta($subscription_id, self::STATUS_META_KEY, 'MERCHANT_MANAGED_ON_HOLD');
        update_post_meta($subscription_id, '_paypal_recurring_card_payment_error', $reason);
        update_post_meta($subscription_id, '_paypal_recurring_card_payment_failed_at', gmdate('Y-m-d H:i:s'));
        $parent_order->add_order_note(sprintf(
            __('Recurring card subscription #%1$d placed on hold: %2$s', 'growtype-wc'),
            $subscription_id,
            $reason
        ));
        $parent_order->save();

        return new WP_Error('paypal_recurring_card_on_hold', $reason);
    }

    public function resume_paypal_subscription($access_token, $subscription_id)
    {
        $resume_url = $this->gateway->get_api_url(
            "/v1/billing/subscriptions/{$subscription_id}/activate",
        );

        $resume_body = wp_json_encode([
            "reason" => "Resuming subscription as requested by customer",
        ]);
        return $this->perform_subscription_action(
            $resume_url,
            $access_token,
            $resume_body,
            'ACTIVE',
            $subscription_id
        );
    }

    public function suspend_paypal_subscription($access_token, $subscription_id)
    {
        $suspend_url = $this->gateway->get_api_url(
            "/v1/billing/subscriptions/{$subscription_id}/suspend",
        );

        $suspend_body = wp_json_encode([
            "reason" => "Customer requested suspension",
        ]);
        return $this->perform_subscription_action(
            $suspend_url,
            $access_token,
            $suspend_body,
            'SUSPENDED',
            $subscription_id
        );
    }

    public function cancel_paypal_subscription($access_token, $subscription_id)
    {
        $cancel_url = $this->gateway->get_api_url(
            "/v1/billing/subscriptions/{$subscription_id}/cancel",
        );

        $cancel_body = wp_json_encode([
            "reason" => "Customer requested cancellation",
        ]);
        return $this->perform_subscription_action(
            $cancel_url,
            $access_token,
            $cancel_body,
            'CANCELLED',
            $subscription_id
        );
    }

    private function perform_subscription_action($url, $access_token, $body, $expected_status, $subscription_id)
    {
        $response = wp_remote_post($url, [
            "headers" => [
                "Authorization" => "Bearer " . $access_token,
                "Content-Type" => "application/json",
            ],
            "body" => $body,
            "timeout" => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'paypal_subscription_status_change_failed',
                __('PayPal could not be reached. The subscription was not changed.', 'growtype-wc')
            );
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        if (in_array($response_code, [200, 201, 204], true)) {
            return true;
        }

        // A retry may receive an error after PayPal already applied the original
        // request. Read provider state before declaring the action unsuccessful.
        $provider_data = $this->fetch_subscription($access_token, $subscription_id);
        if (
            is_array($provider_data)
            && strtoupper((string) ($provider_data['status'] ?? '')) === $expected_status
        ) {
            return true;
        }

        return new WP_Error(
            'paypal_subscription_status_change_failed',
            sprintf(
                __('PayPal returned HTTP %d. The local subscription was not changed.', 'growtype-wc'),
                $response_code
            )
        );
    }

    /**
     * Validate that a provider response belongs to the expected local checkout
     * and represents an active PayPal Billing Subscription.
     */
    public function validate_activation_response(array $provider_data, string $subscription_id, string $plan_id, int $order_id)
    {
        if (trim((string) ($provider_data['id'] ?? '')) !== $subscription_id) {
            return new WP_Error('paypal_subscription_id_mismatch', __('PayPal returned an unexpected subscription.', 'growtype-wc'));
        }

        if (strtoupper(trim((string) ($provider_data['status'] ?? ''))) !== 'ACTIVE') {
            return new WP_Error('paypal_subscription_not_active', __('The PayPal subscription is not active.', 'growtype-wc'));
        }

        if ($plan_id !== '' && trim((string) ($provider_data['plan_id'] ?? '')) !== $plan_id) {
            return new WP_Error('paypal_subscription_plan_mismatch', __('PayPal returned an unexpected billing plan.', 'growtype-wc'));
        }

        $provider_order_id = trim((string) ($provider_data['custom_id'] ?? $provider_data['invoice_id'] ?? ''));
        if ($provider_order_id !== '' && $provider_order_id !== (string) $order_id) {
            return new WP_Error('paypal_subscription_order_mismatch', __('PayPal returned an unexpected order reference.', 'growtype-wc'));
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order || $order->get_payment_method() !== $this->gateway->id) {
            return new WP_Error('paypal_subscription_order_invalid', __('The PayPal subscription order is invalid.', 'growtype-wc'));
        }

        $checkout_flow = trim((string) $order->get_meta('_growtype_wc_checkout_flow'));
        if (
            $checkout_flow !== ''
            && $checkout_flow !== Growtype_Wc_Payment_Gateway_Paypal::CHECKOUT_FLOW_BILLING_SUBSCRIPTION
        ) {
            return new WP_Error(
                'paypal_subscription_checkout_flow_mismatch',
                __('The order was not created through PayPal recurring checkout.', 'growtype-wc')
            );
        }

        // ACTIVE is sufficient for a zero-value trial. When checkout requires an
        // immediate charge, also verify PayPal's authoritative last-payment amount
        // and currency before granting access.
        $expected_amount = (float) $order->get_total();
        if ($expected_amount > 0) {
            $last_payment = is_array($provider_data['billing_info']['last_payment'] ?? null)
                ? $provider_data['billing_info']['last_payment']
                : [];
            $paid_amount = $last_payment['amount']['value'] ?? null;
            $paid_currency = strtoupper((string) ($last_payment['amount']['currency_code'] ?? ''));

            if (
                $paid_amount === null
                || abs((float) $paid_amount - $expected_amount) >= 0.00001
                || $paid_currency !== strtoupper((string) $order->get_currency())
            ) {
                return new WP_Error(
                    'paypal_subscription_initial_payment_mismatch',
                    __('PayPal has not verified the expected initial subscription payment.', 'growtype-wc')
                );
            }
        }

        return true;
    }

    public function get_subscription($access_token, $subscription_id)
    {
        $result = $this->fetch_subscription($access_token, $subscription_id);

        return is_wp_error($result) ? [] : $result;
    }

    public function fetch_subscription($access_token, $subscription_id)
    {
        $get_url = $this->gateway->get_api_url(
            "/v1/billing/subscriptions/{$subscription_id}",
        );

        $response = wp_remote_get($get_url, [
            "headers" => [
                "Authorization" => "Bearer " . $access_token,
                "Content-Type" => "application/json",
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($response_code !== 200 || !is_array($data)) {
            return new WP_Error(
                'paypal_subscription_status_request_failed',
                sprintf('PayPal returned HTTP %d.', $response_code)
            );
        }

        return $data;
    }

    public function get_subscription_data($access_token, $subscription_id)
    {
        return $this->get_subscription($access_token, $subscription_id);
    }

    public function reconcile_subscription(
        int $subscription_id,
        ?array $provider_data = null,
        ?DateTimeImmutable $checked_at = null,
        string $source = 'paypal_reconciliation'
    ): array {
        $checked_at = ($checked_at ?: new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $checked_at_mysql = $checked_at->format('Y-m-d H:i:s');

        $current_local_status = Growtype_Wc_Subscription::status($subscription_id);
        if (
            get_post_type($subscription_id) !== 'growtype_wc_subs'
            || !in_array(
                $current_local_status,
                [Growtype_Wc_Subscription::STATUS_ACTIVE, 'on-hold', Growtype_Wc_Subscription::STATUS_CANCELLED, 'expired'],
                true
            )
        ) {
            return ['outcome' => 'ignored', 'reason' => 'unsupported_local_subscription_status'];
        }

        $order_id = (int) get_post_meta($subscription_id, '_order_id', true);
        $order = $order_id > 0 ? wc_get_order($order_id) : false;
        if (!$order instanceof WC_Order || $order->get_payment_method() !== $this->gateway->id) {
            return ['outcome' => 'ignored', 'reason' => 'not_a_paypal_subscription_order'];
        }

        $paypal_subscription_id = trim((string) $order->get_meta('paypal_subscription_id'));
        if ($paypal_subscription_id === '') {
            return ['outcome' => 'ignored', 'reason' => 'missing_paypal_subscription_id'];
        }

        if ($provider_data === null) {
            $access_token = $this->gateway->get_access_token(
                $this->gateway->get_client_id(),
                $this->gateway->get_client_secret()
            );
            if (empty($access_token)) {
                return $this->record_check_error(
                    $subscription_id,
                    'Unable to obtain a PayPal access token.',
                    $checked_at_mysql
                );
            }

            $provider_data = $this->fetch_subscription($access_token, $paypal_subscription_id);
            if (is_wp_error($provider_data)) {
                return $this->record_check_error(
                    $subscription_id,
                    $provider_data->get_error_message(),
                    $checked_at_mysql
                );
            }
        }

        $returned_id = trim((string) ($provider_data['id'] ?? ''));
        $provider_status = strtoupper(trim((string) ($provider_data['status'] ?? '')));
        $status_map = apply_filters(
            'growtype_wc_paypal_subscription_status_map',
            [
                'APPROVAL_PENDING' => null,
                'APPROVED' => null,
                'ACTIVE' => Growtype_Wc_Subscription::STATUS_ACTIVE,
                'SUSPENDED' => 'on-hold',
                'CANCELLED' => Growtype_Wc_Subscription::STATUS_CANCELLED,
                'EXPIRED' => 'expired',
            ],
            $subscription_id,
            $provider_data
        );
        if (
            $returned_id !== $paypal_subscription_id
            || !is_array($status_map)
            || !array_key_exists($provider_status, $status_map)
        ) {
            return $this->record_check_error(
                $subscription_id,
                'PayPal returned an unmatched subscription ID or unknown status.',
                $checked_at_mysql
            );
        }

        $provider_updated_at = $this->normalize_provider_time($provider_data['status_update_time'] ?? '');
        $provider_reason = sanitize_text_field((string) ($provider_data['status_change_note'] ?? ''));

        // PayPal cancellation is terminal. A stale ACTIVE event must not erase
        // the recorded provider cancellation or reopen local access.
        if (
            $provider_status === 'ACTIVE'
            && in_array($current_local_status, [Growtype_Wc_Subscription::STATUS_CANCELLED, 'expired'], true)
        ) {
            update_post_meta($subscription_id, self::STATUS_CHECKED_AT_META_KEY, $checked_at_mysql);
            update_post_meta($subscription_id, self::STATUS_CHECK_SOURCE_META_KEY, sanitize_key($source));
            delete_post_meta($subscription_id, self::STATUS_CHECK_ERROR_META_KEY);
            delete_post_meta($subscription_id, self::STATUS_CHECK_FAILED_AT_META_KEY);

            return [
                'outcome' => 'ignored',
                'reason' => 'local_subscription_is_terminal',
                'provider_status' => $provider_status,
            ];
        }

        update_post_meta($subscription_id, self::STATUS_META_KEY, $provider_status);
        update_post_meta($subscription_id, self::STATUS_CHECKED_AT_META_KEY, $checked_at_mysql);
        update_post_meta($subscription_id, self::STATUS_UPDATED_AT_META_KEY, $provider_updated_at);
        update_post_meta($subscription_id, self::STATUS_REASON_META_KEY, $provider_reason);
        update_post_meta($subscription_id, self::STATUS_CHECK_SOURCE_META_KEY, sanitize_key($source));
        delete_post_meta($subscription_id, self::STATUS_CHECK_ERROR_META_KEY);
        delete_post_meta($subscription_id, self::STATUS_CHECK_FAILED_AT_META_KEY);

        $next_billing_at = $this->normalize_provider_time($provider_data['billing_info']['next_billing_time'] ?? '');
        if ($next_billing_at !== '') {
            update_post_meta($subscription_id, '_next_charge_date', $next_billing_at);
        }

        $last_payment_at = $this->normalize_provider_time($provider_data['billing_info']['last_payment']['time'] ?? '');
        if ($last_payment_at !== '') {
            update_post_meta($subscription_id, '_paypal_last_payment_at', $last_payment_at);
        }
        if (isset($provider_data['billing_info']['last_payment']['amount']['value'])) {
            update_post_meta(
                $subscription_id,
                '_paypal_last_payment_amount',
                sanitize_text_field((string) $provider_data['billing_info']['last_payment']['amount']['value'])
            );
        }

        $local_status = $status_map[$provider_status];
        // A provider ACTIVE event may restore a temporary hold, but must never
        // reopen a locally-cancelled or expired subscription automatically.
        if ($provider_status === 'ACTIVE' && $current_local_status !== 'on-hold') {
            $local_status = null;
        }

        $supported_local_statuses = apply_filters(
            'growtype_wc_paypal_supported_local_statuses',
            [Growtype_Wc_Subscription::STATUS_ACTIVE, 'on-hold', Growtype_Wc_Subscription::STATUS_CANCELLED, 'expired']
        );
        if (
            $local_status !== null
            && (!is_array($supported_local_statuses) || !in_array($local_status, $supported_local_statuses, true))
        ) {
            return $this->record_check_error(
                $subscription_id,
                'PayPal status mapping returned an unsupported local status.',
                $checked_at_mysql
            );
        }

        if ($local_status === null) {
            return ['outcome' => 'checked', 'provider_status' => $provider_status];
        }

        if ($local_status === $current_local_status) {
            if (in_array($local_status, [Growtype_Wc_Subscription::STATUS_CANCELLED, 'expired', 'on-hold'], true)) {
                $inactive_at = !empty($provider_updated_at) ? $provider_updated_at : $checked_at_mysql;
                update_post_meta($subscription_id, self::PROVIDER_INACTIVE_AT_META_KEY, $inactive_at);
                if (get_post_meta($subscription_id, self::CANCELLATION_SOURCE_META_KEY, true) === '') {
                    update_post_meta($subscription_id, self::CANCELLATION_SOURCE_META_KEY, sanitize_key($source));
                }
                if (
                    $provider_reason !== ''
                    && get_post_meta($subscription_id, self::CANCELLATION_REASON_META_KEY, true) === ''
                ) {
                    update_post_meta($subscription_id, self::CANCELLATION_REASON_META_KEY, $provider_reason);
                }
                if (
                    $local_status === Growtype_Wc_Subscription::STATUS_CANCELLED
                    && get_post_meta($subscription_id, self::CANCELLED_AT_META_KEY, true) === ''
                ) {
                    update_post_meta($subscription_id, self::CANCELLED_AT_META_KEY, $inactive_at);
                }
            }

            return ['outcome' => 'checked', 'provider_status' => $provider_status];
        }

        if ($local_status === Growtype_Wc_Subscription::STATUS_ACTIVE) {
            Growtype_Wc_Subscription::change_status($subscription_id, $local_status);
            delete_post_meta($subscription_id, self::PROVIDER_INACTIVE_AT_META_KEY);
            delete_post_meta($subscription_id, self::CANCELLATION_SOURCE_META_KEY);
            delete_post_meta($subscription_id, self::CANCELLATION_REASON_META_KEY);
            delete_post_meta($subscription_id, self::CANCELLED_AT_META_KEY);
            $order->add_order_note(sprintf(
                'Local subscription #%d restored to active after PayPal reported ACTIVE.',
                $subscription_id
            ));

            return [
                'outcome' => 'status_changed',
                'provider_status' => $provider_status,
                'local_status' => $local_status,
            ];
        }

        $inactive_at = !empty($provider_updated_at) ? $provider_updated_at : $checked_at_mysql;
        $reason = !empty($provider_reason)
            ? $provider_reason
            : sprintf('PayPal reported subscription status %s.', $provider_status);

        Growtype_Wc_Subscription::change_status($subscription_id, $local_status);
        update_post_meta($subscription_id, self::PROVIDER_INACTIVE_AT_META_KEY, $inactive_at);
        update_post_meta($subscription_id, self::CANCELLATION_SOURCE_META_KEY, sanitize_key($source));
        update_post_meta($subscription_id, self::CANCELLATION_REASON_META_KEY, $reason);
        if ($provider_status === 'CANCELLED') {
            update_post_meta($subscription_id, self::CANCELLED_AT_META_KEY, $inactive_at);
        }

        $order->add_order_note(sprintf(
            'Local subscription #%d changed to %s after PayPal reconciliation reported %s at %s. Reason: %s',
            $subscription_id,
            $local_status,
            $provider_status,
            $inactive_at,
            $reason
        ));

        do_action(
            'growtype_wc_paypal_subscription_reconciled',
            $subscription_id,
            $local_status,
            $provider_data
        );

        return [
            'outcome' => 'status_changed',
            'provider_status' => $provider_status,
            'local_status' => $local_status,
            'inactive_at' => $inactive_at,
        ];
    }

    public function add_admin_columns(array $columns): array
    {
        $columns['paypal_status'] = __('PayPal Status', 'growtype-wc');
        $columns['provider_cancellation'] = __('Provider Cancellation', 'growtype-wc');

        return $columns;
    }

    public function add_admin_meta_box(): void
    {
        add_meta_box(
            'growtype-wc-paypal-status',
            __('PayPal Status', 'growtype-wc'),
            [$this, 'render_admin_meta_box'],
            'growtype_wc_subs',
            'side',
            'default'
        );
    }

    public function render_admin_meta_box($post): void
    {
        $subscription_id = (int) ($post->ID ?? 0);
        $local_status = (string) get_post_meta($subscription_id, '_status', true);
        $provider_status = (string) get_post_meta($subscription_id, self::STATUS_META_KEY, true);
        $checked_at = (string) get_post_meta($subscription_id, self::STATUS_CHECKED_AT_META_KEY, true);
        $updated_at = (string) get_post_meta($subscription_id, self::STATUS_UPDATED_AT_META_KEY, true);
        $check_error = (string) get_post_meta($subscription_id, self::STATUS_CHECK_ERROR_META_KEY, true);
        $check_failed_at = (string) get_post_meta($subscription_id, self::STATUS_CHECK_FAILED_AT_META_KEY, true);
        $inactive_at = (string) get_post_meta($subscription_id, self::PROVIDER_INACTIVE_AT_META_KEY, true);
        $cancellation_source = (string) get_post_meta($subscription_id, self::CANCELLATION_SOURCE_META_KEY, true);
        $cancellation_reason = (string) get_post_meta($subscription_id, self::CANCELLATION_REASON_META_KEY, true);
        $order_id = (int) get_post_meta($subscription_id, '_order_id', true);
        $order = $order_id > 0 ? wc_get_order($order_id) : false;
        $is_paypal = $order instanceof WC_Order && $order->get_payment_method() === $this->gateway->id;
        $paypal_subscription_id = $is_paypal ? trim((string) $order->get_meta('paypal_subscription_id')) : '';
        $is_recurring_vault = $is_paypal && $this->is_recurring_vault_order($order);
        $vault_source = $is_recurring_vault ? (string) $order->get_meta('_paypal_vault_source') : '';
        $vault_label = $vault_source === 'applepay' ? __('Apple Pay', 'growtype-wc') : __('card', 'growtype-wc');
        $vault_id = $is_recurring_vault ? trim((string) $order->get_meta('paypal_vault_id')) : '';
        $capture_id = $is_paypal ? trim((string) $order->get_meta('_paypal_capture_id')) : '';
        $hosted_order_id = $is_paypal ? trim((string) $order->get_meta('_paypal_hosted_order_id')) : '';

        echo '<p><strong>' . esc_html__('Local status:', 'growtype-wc') . '</strong> ';
        echo esc_html($local_status !== '' ? ucfirst($local_status) : __('Unknown', 'growtype-wc')) . '</p>';

        if (!$is_paypal) {
            echo '<p>' . esc_html__('This subscription was not purchased through PayPal.', 'growtype-wc') . '</p>';
            return;
        }

        if ($is_recurring_vault) {
            echo '<p><strong>' . esc_html__('Billing mode:', 'growtype-wc') . '</strong><br>';
            echo esc_html(sprintf(__('Merchant-managed recurring %s', 'growtype-wc'), $vault_label)) . '</p>';
            echo '<p><strong>' . esc_html__('Provider status:', 'growtype-wc') . '</strong> ';
            echo esc_html($provider_status !== '' ? $provider_status : __('Verified recurring payment method', 'growtype-wc')) . '</p>';
            if ($vault_id !== '') {
                echo '<p><strong>' . esc_html__('PayPal vault token:', 'growtype-wc') . '</strong><br>';
                echo esc_html(substr($vault_id, 0, 6) . '...' . substr($vault_id, -4)) . '</p>';
            }
        } elseif ($paypal_subscription_id === '') {
            echo '<p><strong>' . esc_html__('Provider status:', 'growtype-wc') . '</strong> ';
            echo '<span style="color:#b32d2e"><strong>' . esc_html__('Not linked', 'growtype-wc') . '</strong></span><br>';
            echo '<small>' . esc_html__('No PayPal billing-subscription ID. A completed PayPal payment is not the same as a PayPal billing subscription.', 'growtype-wc') . '</small></p>';
        } else {
            echo '<p><strong>' . esc_html__('PayPal subscription ID:', 'growtype-wc') . '</strong><br>' . esc_html($paypal_subscription_id) . '</p>';
            echo '<p><strong>' . esc_html__('Provider status:', 'growtype-wc') . '</strong> ';
            echo esc_html($provider_status !== '' ? $provider_status : __('Not checked yet', 'growtype-wc')) . '</p>';
        }

        if ($capture_id !== '') {
            echo '<p><strong>' . esc_html__('Payment capture ID:', 'growtype-wc') . '</strong><br>' . esc_html($capture_id) . '</p>';
        }
        if ($hosted_order_id !== '') {
            echo '<p><strong>' . esc_html__('PayPal checkout order ID:', 'growtype-wc') . '</strong><br>' . esc_html($hosted_order_id) . '</p>';
        }
        if ($checked_at !== '') {
            echo '<p><strong>' . esc_html__('Last checked:', 'growtype-wc') . '</strong><br>' . esc_html(sprintf(__('%s UTC', 'growtype-wc'), $checked_at)) . '</p>';
        }
        if ($updated_at !== '') {
            echo '<p><strong>' . esc_html__('Provider updated:', 'growtype-wc') . '</strong><br>' . esc_html(sprintf(__('%s UTC', 'growtype-wc'), $updated_at)) . '</p>';
        }
        if ($inactive_at !== '') {
            echo '<p><strong>' . esc_html__('Provider inactive:', 'growtype-wc') . '</strong><br>' . esc_html(sprintf(__('%s UTC', 'growtype-wc'), $inactive_at)) . '</p>';
        }
        if ($cancellation_source !== '') {
            echo '<p><strong>' . esc_html__('Cancellation source:', 'growtype-wc') . '</strong><br>' . esc_html($cancellation_source) . '</p>';
        }
        if ($cancellation_reason !== '') {
            echo '<p><strong>' . esc_html__('Cancellation reason:', 'growtype-wc') . '</strong><br>' . esc_html($cancellation_reason) . '</p>';
        }
        if ($check_error !== '') {
            echo '<p><strong style="color:#b32d2e">' . esc_html__('Last check failed:', 'growtype-wc') . '</strong><br>';
            if ($check_failed_at !== '') {
                echo '<small>' . esc_html(sprintf(__('%s UTC', 'growtype-wc'), $check_failed_at)) . '</small><br>';
            }
            echo esc_html($check_error) . '</p>';
        }
    }

    public function render_admin_column(string $column, int $subscription_id): void
    {
        if ($column === 'paypal_status') {
            $status = get_post_meta($subscription_id, self::STATUS_META_KEY, true);
            $checked_at = get_post_meta($subscription_id, self::STATUS_CHECKED_AT_META_KEY, true);
            $check_error = get_post_meta($subscription_id, self::STATUS_CHECK_ERROR_META_KEY, true);
            $check_failed_at = get_post_meta($subscription_id, self::STATUS_CHECK_FAILED_AT_META_KEY, true);
            $order_id = (int) get_post_meta($subscription_id, '_order_id', true);
            $order = $order_id > 0 ? wc_get_order($order_id) : false;
            $paypal_subscription_id = $order instanceof WC_Order
                ? trim((string) $order->get_meta('paypal_subscription_id'))
                : '';
            $is_recurring_vault = $order instanceof WC_Order && $this->is_recurring_vault_order($order);
            $vault_source = $is_recurring_vault ? (string) $order->get_meta('_paypal_vault_source') : '';
            $vault_label = $vault_source === 'applepay' ? __('Apple Pay', 'growtype-wc') : __('card', 'growtype-wc');

            if (empty($status)) {
                if (
                    $order instanceof WC_Order
                    && $order->get_payment_method() === $this->gateway->id
                    && empty($paypal_subscription_id)
                ) {
                    if ($is_recurring_vault) {
                        echo '<strong>' . esc_html(sprintf(__('Merchant-managed recurring %s', 'growtype-wc'), $vault_label)) . '</strong>';
                    } else {
                        echo '<strong>' . esc_html__('Not linked', 'growtype-wc') . '</strong>';
                        echo '<br><small>' . esc_html__('No PayPal billing-subscription ID', 'growtype-wc') . '</small>';
                    }
                } elseif (empty($check_error)) {
                    echo '&mdash;';
                }
            } else {
                echo '<strong>' . esc_html($status) . '</strong>';
                if (!empty($paypal_subscription_id)) {
                    echo '<br><small>' . esc_html($paypal_subscription_id) . '</small>';
                } elseif ($is_recurring_vault) {
                    echo '<br><small>' . esc_html(sprintf(__('PayPal vaulted %s', 'growtype-wc'), $vault_label)) . '</small>';
                }
                if (!empty($checked_at)) {
                    echo '<br><small>' . esc_html(sprintf(__('Checked: %s UTC', 'growtype-wc'), $checked_at)) . '</small>';
                }
            }

            if (!empty($check_error)) {
                echo '<br><strong>' . esc_html__('Check failed', 'growtype-wc') . '</strong>';
                if (!empty($check_failed_at)) {
                    echo '<br><small>' . esc_html(sprintf(__('%s UTC', 'growtype-wc'), $check_failed_at)) . '</small>';
                }
                echo '<br><small>' . esc_html($check_error) . '</small>';
            }
            return;
        }

        if ($column === 'provider_cancellation') {
            $inactive_at = get_post_meta($subscription_id, self::PROVIDER_INACTIVE_AT_META_KEY, true);
            if (empty($inactive_at)) {
                echo '&mdash;';
                return;
            }

            $reason = get_post_meta($subscription_id, self::CANCELLATION_REASON_META_KEY, true);
            $source = get_post_meta($subscription_id, self::CANCELLATION_SOURCE_META_KEY, true);
            echo esc_html(sprintf(__('%s UTC', 'growtype-wc'), $inactive_at));
            if (!empty($source)) {
                echo '<br><small>' . esc_html($source) . '</small>';
            }
            if (!empty($reason)) {
                echo '<br><small>' . esc_html($reason) . '</small>';
            }
        }
    }

    private function record_check_error(int $subscription_id, string $error, string $checked_at): array
    {
        $error = substr(sanitize_text_field($error), 0, 500);
        update_post_meta($subscription_id, self::STATUS_CHECK_ERROR_META_KEY, $error);
        update_post_meta($subscription_id, self::STATUS_CHECK_FAILED_AT_META_KEY, $checked_at);

        error_log(sprintf(
            '[GWC PayPal Reconciliation] Subscription #%d was not changed: %s',
            $subscription_id,
            $error
        ));

        return ['outcome' => 'error', 'error' => $error];
    }

    private function normalize_provider_time($value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return (new DateTimeImmutable((string) $value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (Exception $exception) {
            return '';
        }
    }
}
