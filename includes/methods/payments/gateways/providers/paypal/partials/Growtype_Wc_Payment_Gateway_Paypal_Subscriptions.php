<?php

/**
 * PayPal Subscriptions (Billing API) implementation.
 */
class Growtype_Wc_Payment_Gateway_Paypal_Subscriptions
{
    /** @var Growtype_Wc_Payment_Gateway_Paypal */
    private $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;

        // Register hooks for subscription status changes (from WooCommerce Subscriptions)
        add_action(
            "growtype_wc_change_subscription_status",
            [$this, "change_subscription_status"],
            10,
            2,
        );
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
        $coupon_sig = md5(implode(",", $applied_coupons));
        $option_key = "gwc_paypal_plan_{$wc_product_id}_{$coupon_sig}";

        $cached = get_option($option_key, "");
        if (!empty($cached)) {
            error_log(
                sprintf(
                    "[GWC PayPal] Reusing cached billing plan %s for product %d (coupons: %s)",
                    $cached,
                    $wc_product_id,
                    $coupon_sig,
                ),
            );
            return $cached;
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

        // Persist indefinitely — the plan never changes unless the product price changes.
        // If price changes, delete this option manually or via WP-CLI:
        //   wp option delete gwc_paypal_plan_<product_id>_<coupon_sig>
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
            ],
            "body" => $subscription_body,
            "timeout" => 15,
        ];

        $response = wp_remote_post($subscription_url, $subscription_args);
        $body = wp_remote_retrieve_body($response);

        return json_decode($body, true) ?: [];
    }

    public function change_subscription_status($subscription_id, $status)
    {
        $order_id = get_post_meta($subscription_id, "_order_id", true);

        if (!empty($order_id)) {
            $order = wc_get_order($order_id);

            if ($order->get_payment_method() === $this->gateway->id) {
                $access_token = $this->gateway->get_access_token(
                    $this->gateway->get_client_id(),
                    $this->gateway->get_client_secret(),
                );
                $paypal_subscription_id = $order->get_meta(
                    "paypal_subscription_id",
                );

                if (!empty($paypal_subscription_id)) {
                    if ($status === "cancelled") {
                        $this->suspend_paypal_subscription(
                            $access_token,
                            $paypal_subscription_id,
                        );
                    } elseif ($status === "active") {
                        $this->resume_paypal_subscription(
                            $access_token,
                            $paypal_subscription_id,
                        );
                    }
                }
            }
        }
    }

    public function resume_paypal_subscription($access_token, $subscription_id)
    {
        $resume_url = $this->gateway->get_api_url(
            "/v1/billing/subscriptions/{$subscription_id}/activate",
        );

        $resume_body = wp_json_encode([
            "reason" => "Resuming subscription as requested by customer",
        ]);
        $response = wp_remote_post($resume_url, [
            "headers" => [
                "Authorization" => "Bearer " . $access_token,
                "Content-Type" => "application/json",
            ],
            "body" => $resume_body,
            "timeout" => 15,
        ]);

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function suspend_paypal_subscription($access_token, $subscription_id)
    {
        $suspend_url = $this->gateway->get_api_url(
            "/v1/billing/subscriptions/{$subscription_id}/suspend",
        );

        $suspend_body = wp_json_encode([
            "reason" => "Customer requested suspension",
        ]);
        $response = wp_remote_post($suspend_url, [
            "headers" => [
                "Authorization" => "Bearer " . $access_token,
                "Content-Type" => "application/json",
            ],
            "body" => $suspend_body,
            "timeout" => 15,
        ]);

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function cancel_paypal_subscription($access_token, $subscription_id)
    {
        $cancel_url = $this->gateway->get_api_url(
            "/v1/billing/subscriptions/{$subscription_id}/cancel",
        );

        $cancel_body = wp_json_encode([
            "reason" => "Customer requested cancellation",
        ]);
        $response = wp_remote_post($cancel_url, [
            "headers" => [
                "Authorization" => "Bearer " . $access_token,
                "Content-Type" => "application/json",
            ],
            "body" => $cancel_body,
            "timeout" => 15,
        ]);

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function get_subscription($access_token, $subscription_id)
    {
        $get_url = $this->gateway->get_api_url(
            "/v1/billing/subscriptions/{$subscription_id}",
        );

        $response = wp_remote_get($get_url, [
            "headers" => [
                "Authorization" => "Bearer " . $access_token,
                "Content-Type" => "application/json",
            ],
        ]);

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function get_subscription_data($access_token, $subscription_id)
    {
        return $this->get_subscription($access_token, $subscription_id);
    }
}
