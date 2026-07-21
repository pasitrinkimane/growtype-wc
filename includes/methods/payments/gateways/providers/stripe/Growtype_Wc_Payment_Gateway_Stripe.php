<?php

/**
 * Class WC_Gateway_Free
 * No charge payment method
 */
class Growtype_Wc_Payment_Gateway_Stripe extends WC_Payment_Gateway
{
    const PAYMENT_METHOD_KEY = "gwc-stripe";
    const PROVIDER_ID = "growtype_wc_stripe";
    private $visible_in_frontend;
    public $test_mode;
    private $secret_key;
    public $publishable_key;
    private $webhook_secret;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();

        $this->supports = [
            "products",
            "subscriptions",
            "tokenization",
            "refunds",
            "add_order_meta",
        ];

        $this->setup_extra_properties();

        add_action("woocommerce_update_options_payment_gateways_" . $this->id, [
            $this,
            "process_admin_options",
        ]);
        add_action("woocommerce_thankyou_" . $this->id, [
            $this,
            "thankyou_page",
        ]);
        add_filter(
            "woocommerce_payment_complete_order_status",
            [$this, "change_payment_complete_order_status"],
            10,
            3,
        );

        add_filter("template_redirect", [$this, "payment_redirect"]);

        add_action(
            "woocommerce_add_to_cart",
            [$this, "woocommerce_add_to_cart_extend"],
            20,
            6,
        );
        $this->load_partials();
    }

    protected function load_partials()
    {
        include_once __DIR__ .
            "/partials/Growtype_Wc_Payment_Gateway_Stripe_Webhook.php";
        new Growtype_Wc_Payment_Gateway_Stripe_Webhook($this);

        include_once __DIR__ .
            "/partials/Growtype_Wc_Payment_Gateway_Stripe_Identification.php";
        new Growtype_Wc_Payment_Gateway_Stripe_Identification($this);

        include_once __DIR__ .
            "/partials/Growtype_Wc_Payment_Gateway_Stripe_Payment_Form.php";
        Growtype_Wc_Payment_Gateway_Stripe_Payment_Form::init();
    }

    protected function setup_properties()
    {
        $this->id = self::PROVIDER_ID;
        $this->icon = apply_filters(
            "growtype_wc_payment_gateway_stripe_icon",
            "https://upload.wikimedia.org/wikipedia/commons/b/ba/Stripe_Logo%2C_revised_2016.svg",
        );
        $this->method_title = "Growtype WC - Stripe";
        $this->method_description = __(
            "Allows subscriptions and payments through Stripe.",
            "growtype-wc",
        );
        $this->has_fields = true;
        //        $this->chosen = false;
    }

    protected function setup_extra_properties()
    {
        $this->title = $this->get_option("title");
        $this->description = $this->get_option("description");
        $this->enabled = $this->get_option("enabled");
        $this->visible_in_frontend = $this->get_option("visible_in_frontend");

        $this->test_mode = "yes" === $this->get_option("test_mode");
        $this->secret_key = $this->test_mode
            ? $this->get_option("secret_key_test")
            : $this->get_option("secret_key_live");
        $this->publishable_key = $this->test_mode
            ? $this->get_option("publishable_key_test")
            : $this->get_option("publishable_key_live");
        $this->webhook_secret = $this->test_mode
            ? $this->get_option("webhook_secret_test")
            : $this->get_option("webhook_secret_live");
    }

    public function get_publishable_key()
    {
        return $this->publishable_key;
    }

    public function get_secret_key()
    {
        return $this->secret_key;
    }

    public function get_webhook_secret()
    {
        return $this->webhook_secret;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            "enabled" => [
                "title" => __("Enable/Disable", "growtype-wc"),
                "type" => "checkbox",
                "label" => __("Method is enabled", "growtype-wc"),
                "default" => "no",
            ],
            "test_mode" => [
                "title" => __("Test mode", "growtype-wc"),
                "type" => "checkbox",
                "label" => __("Testing mode is enabled", "growtype-wc"),
                "description" => "Test payments will be charged",
                "default" => "yes",
                "desc_tip" => true,
            ],
            "title" => [
                "title" => __("Method title", "growtype-wc"),
                "type" => "text",
                "description" => __(
                    "This controls the title which the user sees during checkout.",
                    "growtype-wc",
                ),
                "default" => __("Stripe", "growtype-wc"),
                "desc_tip" => true,
            ],
            "description" => [
                "title" => __("Description", "growtype-wc"),
                "type" => "textarea",
                "description" => __(
                    "Payment method description that the customer will see on your checkout.",
                    "growtype-wc",
                ),
                "default" => __("", "growtype-wc"),
                "desc_tip" => true,
            ],
            "add_to_card_redirect_stripe_checkout" => [
                "title" => __("Stripe checkout - add to cart", "growtype-wc"),
                "type" => "checkbox",
                "label" => __(
                    "Redirect to stripe checkout after add to cart",
                    "growtype-wc",
                ),
                "default" => "no",
            ],
            "show_payment_form" => [
                "title" => __(
                    "Show payment form",
                    "growtype-wc",
                ),
                "type" => "checkbox",
                "label" => __(
                    "Show payment form instead of redirect",
                    "growtype-wc",
                ),
                "default" => "no",
            ],
            "secret_key_test" => [
                "title" => __("Secret key - Test", "growtype-wc"),
                "type" => "text",
            ],
            "secret_key_live" => [
                "title" => __("Secret key - Live", "growtype-wc"),
                "type" => "text",
            ],
            "publishable_key_test" => [
                "title" => __("Publishable key - Test", "growtype-wc"),
                "type" => "text",
            ],
            "publishable_key_live" => [
                "title" => __("Publishable key - Live", "growtype-wc"),
                "type" => "text",
            ],
            "webhook_secret_test" => [
                "title" => __("Webhook Secret - Test", "growtype-wc"),
                "type" => "text",
                "description" => __(
                    "Find this in your Stripe Dashboard -> Developers -> Webhooks.",
                    "growtype-wc",
                ),
            ],
            "webhook_secret_live" => [
                "title" => __("Webhook Secret - Live", "growtype-wc"),
                "type" => "text",
                "description" => __(
                    "Find this in your Stripe Dashboard -> Developers -> Webhooks.",
                    "growtype-wc",
                ),
            ],
        ];
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__("Invalid order.", "growtype-wc"), "error");
            return ["result" => "failure"];
        }

        try {
            wc_reduce_stock_levels($order_id);

            $order->payment_complete();
            $order->update_status("completed");
            WC()->cart->empty_cart();

            return [
                "result" => "success",
                "redirect" => Growtype_Wc_Payment_Gateway::success_url(
                    $order_id,
                    self::PROVIDER_ID,
                ),
            ];
        } catch (Exception $e) {
            wc_add_notice(
                sprintf(
                    __(
                        "Payment failed. Please try again or contact our support %s",
                        "growtype-wc",
                    ),
                    get_option("admin_email"),
                ),
                "error",
            );
            error_log("Stripe Payment Error: " . $e->getMessage());
            return ["result" => "failure"];
        }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page() {}

    /**
     * Change payment complete order status to completed for COD orders.
     *
     * @param string $status Current order status.
     * @param int $order_id Order ID.
     * @param WC_Order|false $order Order object.
     * @return string
     * @since  3.1.0
     */
    public function change_payment_complete_order_status(
        $status,
        $order_id,
        $order
    ) {
        if ($order && $order->get_payment_method() === $this->id) {
            return "completed";
        }

        return $status;
    }

    public function payment_fields()
    {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description));
        }

        if (is_account_page() && is_wc_endpoint_url("add-payment-method")) {
            $this->render_add_payment_method_fields();
            return;
        }

        $cc_form = new WC_Payment_Gateway_CC();
        $cc_form->id = $this->id;
        $cc_form->supports = $this->supports;
        $cc_form->form();
    }

    protected function render_add_payment_method_fields()
    {
        ?>
        <fieldset id="wc-<?php echo esc_attr(
            $this->id,
        ); ?>-cc-form" class="wc-credit-card-form wc-payment-form">
            <p class="form-row form-row-wide">
                <label for="<?php echo esc_attr(
                    $this->id,
                ); ?>-card-holder-name"><?php esc_html_e(
    "Name on card",
    "woocommerce",
); ?>&nbsp;<span class="required">*</span></label>
                <input id="<?php echo esc_attr(
                    $this->id,
                ); ?>-card-holder-name" class="input-text wc-credit-card-form-card-holder-name" inputmode="text" autocomplete="cc-name" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="<?php esc_attr_e(
    "Full name",
    "growtype-wc",
); ?>" name="<?php echo esc_attr($this->id); ?>_card_holder_name">
            </p>
            <p class="form-row form-row-wide">
                <label for="<?php echo esc_attr(
                    $this->id,
                ); ?>-card-number"><?php esc_html_e(
    "Card number",
    "woocommerce",
); ?>&nbsp;<span class="required">*</span></label>
                <input id="<?php echo esc_attr(
                    $this->id,
                ); ?>-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="•••• •••• •••• ••••" name="<?php echo esc_attr(
    $this->id,
); ?>_card_number">
            </p>
            <p class="form-row form-row-first">
                <label for="<?php echo esc_attr(
                    $this->id,
                ); ?>-card-expiry"><?php esc_html_e(
    "Expiry (MM/YY)",
    "woocommerce",
); ?>&nbsp;<span class="required">*</span></label>
                <input id="<?php echo esc_attr(
                    $this->id,
                ); ?>-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="MM / YY" name="<?php echo esc_attr(
    $this->id,
); ?>_card_expiry">
            </p>
            <p class="form-row form-row-last">
                <label for="<?php echo esc_attr(
                    $this->id,
                ); ?>-card-cvc"><?php esc_html_e(
    "Card code",
    "woocommerce",
); ?>&nbsp;<span class="required">*</span></label>
                <input id="<?php echo esc_attr(
                    $this->id,
                ); ?>-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="CVC" style="width:100px" name="<?php echo esc_attr(
    $this->id,
); ?>_card_cvc">
            </p>
            <div class="clear"></div>
        </fieldset>
        <?php
    }

    public function add_payment_method()
    {
        if (!is_user_logged_in()) {
            wc_add_notice(
                __(
                    "You must be logged in to add a payment method.",
                    "growtype-wc",
                ),
                "error",
            );
            return ["result" => "failure"];
        }

        $holder_name = sanitize_text_field(
            wp_unslash($_POST[$this->id . "_card_holder_name"] ?? ""),
        );
        $card_number = preg_replace(
            "/\D+/",
            "",
            (string) wp_unslash($_POST[$this->id . "_card_number"] ?? ""),
        );
        $card_expiry = sanitize_text_field(
            wp_unslash($_POST[$this->id . "_card_expiry"] ?? ""),
        );
        $card_cvc = preg_replace(
            "/\D+/",
            "",
            (string) wp_unslash($_POST[$this->id . "_card_cvc"] ?? ""),
        );

        $passed_validation = true;

        if (empty($holder_name)) {
            wc_add_notice(
                __("Please enter card holder name.", "growtype-wc"),
                "error",
            );
            $passed_validation = false;
        }

        if (!growtype_wc_card_number_is_valid($card_number)) {
            wc_add_notice(
                __("Please enter a valid card number.", "growtype-wc"),
                "error",
            );
            $passed_validation = false;
        }

        if (!growtype_wc_card_expiry_is_valid($card_expiry)) {
            wc_add_notice(
                __("Please enter a valid card expiry date.", "growtype-wc"),
                "error",
            );
            $passed_validation = false;
        }

        if (empty($card_cvc)) {
            wc_add_notice(__("Please enter card cvc.", "growtype-wc"), "error");
            $passed_validation = false;
        }

        if (!$passed_validation) {
            return ["result" => "failure"];
        }

        [$exp_month, $exp_year] = array_pad(
            array_map("trim", explode("/", $card_expiry)),
            2,
            "",
        );
        $exp_month = absint($exp_month);
        $exp_year = absint(
            strlen($exp_year) === 2 ? "20" . $exp_year : $exp_year,
        );

        if (
            $exp_month < 1 ||
            $exp_month > 12 ||
            $exp_year < (int) gmdate("Y")
        ) {
            wc_add_notice(
                __("Please enter a valid card expiry date.", "growtype-wc"),
                "error",
            );
            return ["result" => "failure"];
        }

        try {
            $user_id = get_current_user_id();
            $user = get_userdata($user_id);
            $email =
                $user->user_email ?:
                Growtype_Wc_Payment_Gateway::resolve_user_email();

            $stripe = new \Stripe\StripeClient($this->secret_key);

            $customer_id = get_user_meta($user_id, "stripe_customer_id", true);

            if (empty($customer_id)) {
                $customer = $stripe->customers->create([
                    "name" => $holder_name,
                    "email" => $email,
                    "metadata" => [
                        "user_id" => $user_id,
                        "site" => home_url(),
                    ],
                ]);
                $customer_id = $customer->id ?? "";

                if (empty($customer_id)) {
                    throw new \Exception(
                        __("Unable to create customer profile.", "growtype-wc"),
                    );
                }

                update_user_meta($user_id, "stripe_customer_id", $customer_id);
            }

            $payment_method = $stripe->paymentMethods->create([
                "type" => "card",
                "card" => [
                    "number" => $card_number,
                    "exp_month" => $exp_month,
                    "exp_year" => $exp_year,
                    "cvc" => $card_cvc,
                ],
                "billing_details" => [
                    "name" => $holder_name,
                    "email" => $email,
                ],
            ]);

            if (empty($payment_method->id)) {
                throw new \Exception(
                    __("Unable to create payment method.", "growtype-wc"),
                );
            }

            $stripe->paymentMethods->attach($payment_method->id, [
                "customer" => $customer_id,
            ]);

            $stripe->customers->update($customer_id, [
                "invoice_settings" => [
                    "default_payment_method" => $payment_method->id,
                ],
            ]);

            $token = new WC_Payment_Token_CC();
            $token->set_token($payment_method->id);
            $token->set_gateway_id($this->id);
            $token->set_user_id($user_id);
            $token->set_card_type($payment_method->card->brand ?? "card");
            $token->set_last4(
                $payment_method->card->last4 ?? substr($card_number, -4),
            );
            $token->set_expiry_month(
                $payment_method->card->exp_month ?? $exp_month,
            );
            $token->set_expiry_year(
                $payment_method->card->exp_year ?? $exp_year,
            );
            $token->set_default(true);

            if (!$token->save()) {
                throw new \Exception(
                    __("Unable to save payment method.", "growtype-wc"),
                );
            }

            wc_add_notice(
                __("Payment method successfully added.", "growtype-wc"),
            );

            return [
                "result" => "success",
                "redirect" => wc_get_endpoint_url("payment-methods"),
            ];
        } catch (\Throwable $e) {
            error_log(
                "growtype_wc_add_payment_method_stripe_error: " .
                    $e->getMessage(),
            );
            wc_add_notice($e->getMessage(), "error");

            return ["result" => "failure"];
        }
    }

    public function subscription_details(
        $stripe_subscription_id,
        $existing_subscription_id,
    ) {
        try {
            $stripe = new \Stripe\StripeClient($this->secret_key);
            $subscription = $stripe->subscriptions->retrieve(
                $stripe_subscription_id,
            );

            if (!empty($subscription)) {
                $status = $subscription->status;
                $canceled_at = $subscription->canceled_at;
                $canceled_at = !empty($canceled_at)
                    ? date(
                        get_option("date_format") .
                            " " .
                            get_option("time_format"),
                        $canceled_at,
                    )
                    : null;
                $customer_id = $subscription->customer;
                $current_billing_period_end = $subscription->current_period_end;
                $renewal_date = !empty($current_billing_period_end)
                    ? date(
                        get_option("date_format") .
                            " " .
                            get_option("time_format"),
                        $current_billing_period_end,
                    )
                    : null;
                $return_url =
                    Growtype_Wc_Subscription::manage_url(
                        $existing_subscription_id,
                    ) . "&status=updated";

                $session = $stripe->billingPortal->sessions->create([
                    "customer" => $customer_id,
                    "return_url" => $return_url,
                ]);

                $billing_portal_url = !empty($session) ? $session->url : null;

                return [
                    "status" => $status,
                    "canceled_at" => $canceled_at,
                    "renewal_date" => $renewal_date,
                    "billing_portal_url" => $billing_portal_url,
                ];
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log(
                sprintf(
                    "growtype_wc_stripe_billing_portal_error %s",
                    $e->getMessage(),
                ),
            );
        } catch (Exception $e) {
            error_log(
                sprintf(
                    "growtype_wc_stripe_billing_portal_error %s",
                    $e->getMessage(),
                ),
            );
        }

        return [];
    }

    public function payment_redirect()
    {
        // 1) Only on our thank-you page
        if (!growtype_wc_is_thankyou_page()) {
            return;
        }

        global $wp;
        $order_id = absint($wp->query_vars["order-received"] ?? 0);
        $order = wc_get_order($order_id);

        // 2) Bail if invalid order, already completed, or not our gateway
        if (!$order || $order->get_status() === "completed") {
            return;
        }

        if ($order->get_payment_method() !== self::PROVIDER_ID) {
            return;
        }

        // 3) Validate session or intent ID and guard “run once”
        $session_id = sanitize_text_field($_GET["checkout_session_id"] ?? "");
        $intent_id = sanitize_text_field($_GET["payment_intent"] ?? "");

        $saved_session_id = $order->get_meta("stripe_session_id");
        $saved_intent_id = $order->get_meta("stripe_intent_id");

        if (!$session_id && !$intent_id) {
            return;
        }

        if ($session_id && $session_id !== $saved_session_id) {
            return;
        }

        if ($intent_id && $intent_id !== $saved_intent_id) {
            return;
        }

        if (
            $order->get_meta("stripe_customer_id") &&
            $order->get_meta("stripe_payment_method_id")
        ) {
            return;
        }

        $stripe = new \Stripe\StripeClient($this->secret_key);

        $customer_id = "";

        // 4) Fetch the Checkout Session or Payment Intent
        try {
            if ($session_id) {
                $session = $stripe->checkout->sessions->retrieve($session_id);
                $customer_id = $session->customer ?? "";

                // Sync email — prefer Stripe, fall back to resolved user email
                $email = $session->customer_details->email ?? "";

                if (empty($email)) {
                    $email = Growtype_Wc_Payment_Gateway::resolve_user_email();
                }

                if (!empty($email)) {
                    Growtype_Wc_Payment_Gateway::update_user_email_if_not_exists(
                        $order->get_customer_id(),
                        $email,
                    );

                    Growtype_Wc_Payment_Gateway::update_order_email_if_not_exists(
                        $order_id,
                        $email,
                    );
                }

                if (
                    $session->mode === "subscription" &&
                    !empty($session->subscription)
                ) {
                    // (existing subscription logic)
                    $sub = $stripe->subscriptions->retrieve(
                        $session->subscription,
                        ["expand" => ["latest_invoice.payment_intent"]],
                    );
                    $customer_id = $sub->customer;
                    $order->update_meta_data(
                        "stripe_subscription_id",
                        $sub->id,
                    );
                    $order->update_meta_data(
                        "stripe_payment_method_id",
                        $sub->latest_invoice->payment_intent->payment_method,
                    );
                } else {
                    $pi = $stripe->paymentIntents->retrieve(
                        $session->payment_intent,
                    );
                    if ($pi->status === "succeeded") {
                        $customer_id = $pi->customer;
                        $order->update_meta_data(
                            "stripe_transaction_id",
                            $pi->id,
                        );
                        $order->update_meta_data(
                            "stripe_payment_method_id",
                            $pi->payment_method,
                        );
                        Growtype_Wc_Payment::persist_stripe_display_details_from_payment_intent(
                            $order,
                            $pi,
                        );
                    }
                }
            } elseif ($intent_id) {
                $pi = $stripe->paymentIntents->retrieve($intent_id);
                if ($pi->status === "succeeded") {
                    $customer_id = $pi->customer;
                    $order->update_meta_data("stripe_transaction_id", $pi->id);
                    $order->update_meta_data(
                        "stripe_payment_method_id",
                        $pi->payment_method,
                    );
                    Growtype_Wc_Payment::persist_stripe_display_details_from_payment_intent(
                        $order,
                        $pi,
                    );
                }
            }
        } catch (\Exception $e) {
            error_log(
                "growtype_wc_stripe_redirect_handler_error: " .
                    $e->getMessage(),
            );
            return;
        }

        if ($customer_id) {
            update_user_meta(
                $order->get_customer_id(),
                "stripe_customer_id",
                $customer_id,
            );
            $order->update_meta_data("stripe_customer_id", $customer_id);
        }

        $order->save();
        $order->payment_complete();
    }

    public function webhooks()
    {
        $order_id = $_GET["id"] ?? "";

        if (!empty($order_id)) {
            $order = wc_get_order($order_id);

            if (!empty($order)) {
                error_log(
                    sprintf(
                        "growtype_wc_stripe_webhook %s",
                        print_r($order, true),
                    ),
                );

                $order->payment_complete();

                update_option("webhook_debug", $_GET);
            }
        }
    }

    function woocommerce_add_to_cart_extend(
        $cart_item_key,
        $product_id,
        $quantity,
        $variation_id,
        $variation_attributes,
        $cart_item_data
    ) {
        static $already_running = false;

        if ($already_running) {
            return; // Exit if already running
        }

        $already_running = true;

        try {
            do_action(
                "growtype_wc_before_add_to_cart",
                $cart_item_key,
                $product_id,
                $quantity,
                $variation_id,
                $variation_attributes,
                $cart_item_data,
            );

            if (
                $this->get_option("add_to_card_redirect_stripe_checkout") ===
                    "yes" &&
                $this->get_option("show_payment_form") !== "yes" &&
                isset($_GET["payment_method"]) &&
                $_GET["payment_method"] === self::PAYMENT_METHOD_KEY
            ) {
                // Use shared method to create order
                $order = Growtype_Wc_Payment::create_instant_order(
                    $product_id,
                    1,
                    $this->id,
                );

                $product = wc_get_product($product_id); // Re-fetch product object if needed for logic below

                $order_id = $order->get_id();

                Growtype_Wc_Order::apply_trial_price($order, $product_id);

                $cancel_url = Growtype_Wc_Payment_Gateway::cancel_url(
                    $order_id,
                    false,
                    WC()->cart->get_applied_coupons(),
                );

                WC()->cart->empty_cart();

                try {
                    $current_user = wp_get_current_user();

                    $stripe = new \Stripe\StripeClient($this->secret_key);

                    $product_name = $product->get_name();
                    $product_name = sanitize_text_field($product_name);

                    if (
                        growtype_wc_product_is_subscription($product->get_id())
                    ) {
                        try {
                            $stripe_product = $stripe->products->create([
                                "name" => $product_name,
                            ]);

                            $stripe_price_details = [
                                "product" => $stripe_product->id,
                                "unit_amount" =>
                                    growtype_wc_get_subscription_price(
                                        $product_id,
                                    ) * 100, // Amount in cents
                                "currency" => get_woocommerce_currency(),
                                "recurring" => [
                                    "interval" => growtype_wc_get_subscription_period(
                                        $product_id,
                                    ),
                                    "interval_count" => growtype_wc_get_subscription_duration(
                                        $product_id,
                                    ),
                                ],
                            ];

                            $stripe_price = $stripe->prices->create(
                                $stripe_price_details,
                            );

                            $checkout_session_data = [
                                "line_items" => [
                                    [
                                        "price" => $stripe_price->id,
                                        "quantity" => $quantity,
                                    ],
                                ],
                                "mode" => "subscription",
                                "success_url" => Growtype_Wc_Payment_Gateway::success_url(
                                    $order_id,
                                    self::PROVIDER_ID,
                                    true,
                                ),
                                "cancel_url" => $cancel_url,
                                "subscription_data" => [
                                    "description" => sprintf(
                                        "Order #%s - %s",
                                        $order_id,
                                        $product_name,
                                    ),
                                    "metadata" => [
                                        "order_id" => $order_id,
                                        "product_id" => $product_id,
                                        "user_id" => $current_user->ID,
                                        "site" => home_url(),
                                    ],
                                ],
                            ];

                            // Only set trial_period_days for genuinely FREE trials (trial price = 0).
                            // For paid trials, generate a Stripe coupon from the trial settings.
                            if (growtype_wc_product_is_trial($product_id)) {
                                $trial_price = (float) growtype_wc_get_trial_price($product_id);
                                $trial_duration_raw = (int) growtype_wc_get_trial_duration($product_id);
                                $trial_period = growtype_wc_get_trial_period($product_id) ?: 'day';

                                if ($trial_price <= 0) {
                                    // Free trial — convert to days for Stripe
                                    $period_days_map = [
                                        'day'   => 1,
                                        'week'  => 7,
                                        'month' => 30,
                                        'year'  => 365,
                                    ];
                                    $multiplier = $period_days_map[strtolower($trial_period)] ?? 1;
                                    $trial_days = $trial_duration_raw * $multiplier;

                                    error_log("[Stripe Checkout] FREE trial: duration={$trial_duration_raw} period={$trial_period} trial_days={$trial_days}");

                                    $checkout_session_data["subscription_data"][
                                        "trial_period_days"
                                    ] = $trial_days;
                                } else {
                                    // Paid introductory period — generate a Stripe coupon equal to the price difference,
                                    // applied only to the first invoice.
                                    $recurring_price = (float) growtype_wc_get_subscription_price($product_id);
                                    $discount_amount_cents = (int) round(($recurring_price - $trial_price) * 100);

                                    if ($discount_amount_cents > 0) {
                                        try {
                                            $trial_coupon = $stripe->coupons->create([
                                                "amount_off" => $discount_amount_cents,
                                                "currency"   => get_woocommerce_currency(),
                                                "duration"   => "once",
                                                "name"       => "Introductory offer",
                                            ]);

                                            $checkout_session_data["discounts"] = [
                                                ["coupon" => $trial_coupon->id],
                                            ];

                                            error_log("[Stripe Checkout] PAID intro: recurring={$recurring_price} trial_price={$trial_price} discount_cents={$discount_amount_cents} stripe_coupon={$trial_coupon->id}");
                                        } catch (\Exception $e) {
                                            error_log("[Stripe Checkout] Failed to create trial discount coupon: " . $e->getMessage());
                                        }
                                    }
                                }
                            }

                            $email =
                                $current_user->user_email ?:
                                Growtype_Wc_Payment_Gateway::resolve_user_email();

                            if (!empty($email)) {
                                $checkout_session_data[
                                    "customer_email"
                                ] = $email;
                                $checkout_session_data["subscription_data"][
                                    "metadata"
                                ]["user_email"] = $email;
                            }

                            /**
                             * Apply coupon
                             */
                            if (!empty($applied_coupons)) {
                                $applied_coupon_code = reset($applied_coupons);
                                $wc_coupon = new WC_Coupon(
                                    $applied_coupon_code,
                                );

                                if ($wc_coupon->is_valid()) {
                                    $discount_type = $wc_coupon->get_discount_type();
                                    $discount_amount = (float) $wc_coupon->get_amount();

                                    try {
                                        if ($discount_type === "percent") {
                                            $stripe_coupon = $stripe->coupons->create(
                                                [
                                                    "percent_off" => $discount_amount,
                                                    "duration" => "once",
                                                ],
                                            );
                                        } else {
                                            $stripe_coupon = $stripe->coupons->create(
                                                [
                                                    "amount_off" =>
                                                        $discount_amount * 100, // cents
                                                    "currency" => get_woocommerce_currency(),
                                                    "duration" => "once",
                                                ],
                                            );
                                        }

                                        // Attach the Stripe coupon to the subscription
                                        $checkout_session_data["discounts"] = [
                                            ["coupon" => $stripe_coupon->id],
                                        ];
                                    } catch (Exception $e) {
                                        error_log(
                                            "Stripe coupon creation failed: " .
                                                $e->getMessage(),
                                        );
                                    }
                                }
                            }

                            $checkout_session = $stripe->checkout->sessions->create(
                                $checkout_session_data,
                            );
                        } catch (Exception $e) {
                            error_log(
                                sprintf(
                                    "growtype_wc_stripe_add_to_cart_error. %s",
                                    $e->getMessage(),
                                ),
                            );
                            wp_redirect($cancel_url);
                        }
                    } else {
                        $checkout_session_data = [
                            "line_items" => [
                                [
                                    "price_data" => [
                                        "product_data" => [
                                            "name" => $product_name,
                                            "metadata" => [
                                                "pro_id" => $product->get_id(),
                                            ],
                                        ],
                                        "unit_amount" =>
                                            $order->get_total() * 100,
                                        "currency" => get_woocommerce_currency(),
                                    ],
                                    "quantity" => $quantity,
                                ],
                            ],
                            "mode" => "payment",
                            // Always create a Stripe customer so we can charge upsells later
                            "customer_creation" => "always",
                            "success_url" => Growtype_Wc_Payment_Gateway::success_url(
                                $order_id,
                                self::PROVIDER_ID,
                                true,
                            ),
                            "cancel_url" => $cancel_url,
                            "payment_intent_data" => [
                                "description" => sprintf(
                                    "Order #%s - %s",
                                    $order_id,
                                    $product_name,
                                ),
                                "statement_descriptor" => sprintf(
                                    "%s - %s",
                                    get_bloginfo("name"),
                                    $order_id,
                                ),
                                "setup_future_usage" => "off_session",
                                "metadata" => [
                                    "order_id" => $order_id,
                                    "product_id" => $product_id,
                                    "user_id" => $current_user->ID,
                                    "site" => home_url(),
                                ],
                            ],
                        ];

                        $email =
                            $current_user->user_email ?:
                            Growtype_Wc_Payment_Gateway::resolve_user_email();

                        if (!empty($email)) {
                            $checkout_session_data["customer_email"] = $email;
                            $checkout_session_data["metadata"][
                                "user_email"
                            ] = $email;
                        }

                        $checkout_session = $stripe->checkout->sessions->create(
                            $checkout_session_data,
                        );
                    }
                } catch (Exception $e) {
                    error_log(
                        sprintf(
                            "growtype_wc_stripe_add_to_cart_error. %s",
                            $e->getMessage(),
                        ),
                    );

                    $order->update_status(
                        "failed",
                        sprintf(
                            __("Reason %s.", "growtype-wc"),
                            wc_clean($e->getMessage()),
                        ),
                    );
                }

                if (isset($checkout_session) && $checkout_session) {
                    $order->update_meta_data(
                        "payment_provider_checkout_url",
                        $checkout_session->url,
                    );
                    $order->update_meta_data(
                        "stripe_session_id",
                        $checkout_session->id,
                    );

                    do_action(
                        "woocommerce_checkout_create_order",
                        $order,
                        $cart_item_data,
                    );

                    $order->save();

                    wp_redirect($checkout_session->url);
                } else {
                    wp_redirect($cancel_url);
                }

                exit();
            }
        } catch (\Exception $e) {
            error_log("Stripe add_to_cart error: " . $e->getMessage());
        }

        $already_running = false;
    }

    public function charge_intent($parent_order_id, $product_id, $description)
    {
        // 1) Load the original order
        $parent = wc_get_order($parent_order_id);
        if (!$parent) {
            error_log("{$log_prefix} FAILED: Invalid parent order ID: {$parent_order_id}");
            throw new \Exception("Invalid parent order ID: {$parent_order_id}");
        }

        // 2) Create a new WC order for the upsell
        $upsell_order = wc_create_order();
        // Set parent reference
        $upsell_order->update_meta_data("parent_order_id", $parent_order_id);
        // Assign same customer
        if ($parent->get_customer_id()) {
            $upsell_order->set_customer_id($parent->get_customer_id());
        }

        $product = wc_get_product($product_id);

        $upsell_order->add_product($product, 1);

        $upsell_order->set_payment_method($this->id);

        // Calculate totals
        $upsell_order->set_currency($parent->get_currency());

        $upsell_order->calculate_totals();

        $amount = (float) $product->get_price();
        $order_total = (float) $upsell_order->get_total();
        error_log("{$log_prefix} Step 3 OK: Amounts - product_price={$amount} order_total={$order_total} currency={$upsell_order->get_currency()}");

        // Validate that amount is valid for Stripe (must be > 0 for confirmed PaymentIntents)
        if ($amount <= 0 && $order_total <= 0) {
            error_log("{$log_prefix} FAILED: Cannot charge zero amount. Product price={$amount} order_total={$order_total}. This may be a trial/subscription product that requires checkout flow.");
            throw new \Exception("This subscription includes a free trial. Please complete your purchase through the regular checkout.");
        }

        // 4) Prepare Stripe off-session charge
        $customer_id = $parent->get_meta("stripe_customer_id");

        // Fallback to user meta if order meta is missing
        if (!$customer_id && $parent->get_customer_id()) {
            $customer_id = get_user_meta(
                $parent->get_customer_id(),
                "stripe_customer_id",
                true,
            );
        }

        $payment_method = $parent->get_meta("stripe_payment_method_id");

        if (!$customer_id) {
            error_log("{$log_prefix} FAILED: Missing Stripe customer.");
            throw new \Exception("Missing Stripe customer.");
        }

        if (!$payment_method) {
            error_log("{$log_prefix} FAILED: Missing Stripe payment method.");
            throw new \Exception("Missing Stripe payment method.");
        }

        $upsell_order->update_meta_data("stripe_customer_id", $customer_id);
        $upsell_order->update_meta_data(
            "stripe_payment_method_id",
            $payment_method,
        );

        $stripe = new \Stripe\StripeClient($this->secret_key);

        // Build the PaymentIntent params
        $pi_amount = intval(round($amount * 100));
        $pi_currency = strtolower($upsell_order->get_currency());
        $pi_params = [
            "amount" => $pi_amount,
            "currency" => $pi_currency,
            "customer" => $customer_id,
            "payment_method" => $payment_method,
            "off_session" => true,
            "confirm" => true,
            "description" => $description,
            "metadata" => [
                "parent_order_id" => $parent_order_id,
                "upsell_order_id" => $upsell_order->get_id(),
                "product_id" => $product_id,
            ],
        ];
        error_log("{$log_prefix} Step 5: Creating Stripe PaymentIntent with params: amount={$pi_amount} currency={$pi_currency} customer={$customer_id} pm={$payment_method} off_session=true confirm=true");

        try {
            $pi = $stripe->paymentIntents->create($pi_params);
            error_log("{$log_prefix} Step 5 OK: PaymentIntent created. id={$pi->id} status={$pi->status}");
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("{$log_prefix} FAILED Step 5: Stripe API error. Type=" . get_class($e) . " Code={$e->getStripeCode()} HttpStatus={$e->getHttpStatus()} Message={$e->getMessage()}");
            // record failure on upsell order
            $upsell_order->add_order_note(
                sprintf(
                    __("Upsell charge failed: %s", "growtype-wc"),
                    $e->getMessage(),
                ),
            );
            $upsell_order->update_status('failed');
            $upsell_order->save();
            throw new \Exception(
                "Upsell charge failed: " . $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }

        // 5) Mark the new order as paid
        $upsell_order->update_meta_data("stripe_transaction_id", $pi->id);
        error_log("{$log_prefix} Step 6: Marking order as paid. pi_id={$pi->id}");

        // Inherit specific payment method info (Google Pay, Apple Pay etc) from parent
        $parent_method_type = $parent->get_meta("_stripe_payment_method_type");
        $parent_method_title = $parent->get_payment_method_title();

        if ($parent_method_type) {
            $upsell_order->update_meta_data(
                "_stripe_payment_method_type",
                $parent_method_type,
            );
        }

        if ($parent_method_title) {
            $upsell_order->set_payment_method_title($parent_method_title);
        }

        $upsell_order->add_order_note(
            sprintf(
                __("Upsell PaymentIntent succeeded: %s", "growtype-wc"),
                $pi->id,
            ),
        );

        $upsell_order->payment_complete();
        error_log("{$log_prefix} Step 7 OK: Order #{$upsell_order->get_id()} marked as completed.");

        // 6) Save everything
        $upsell_order->save();

        if ($upsell_order->get_customer_id() && $customer_id) {
            update_user_meta(
                $upsell_order->get_customer_id(),
                "stripe_customer_id",
                $customer_id,
            );
            error_log("{$log_prefix} Step 8 OK: Updated user_meta stripe_customer_id for user={$upsell_order->get_customer_id()}");
        }

        return [
            "pi" => $pi,
            "order_id" => $upsell_order->get_id(),
        ];
    }

    /**
     * Process a refund via the Stripe Refunds API.
     *
     * Called by WooCommerce when the admin triggers a refund (our custom order action
     * or the built-in refund UI). Uses the stripe_transaction_id (PaymentIntent ID)
     * stored on the order as the charge to refund.
     *
     * @param int        $order_id WooCommerce order ID.
     * @param float|null $amount   Amount to refund (null = full order total).
     * @param string     $reason   Reason shown in order notes.
     * @return bool|WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Order not found.', 'growtype-wc' ) );
        }

        if ( empty( $this->secret_key ) ) {
            return new WP_Error( 'missing_key', __( 'Stripe secret key is not configured.', 'growtype-wc' ) );
        }

        // Prefer the WC-standard _transaction_id, fall back to stripe_transaction_id meta.
        $pi_id = $order->get_transaction_id();
        if ( empty( $pi_id ) ) {
            $pi_id = (string) $order->get_meta( 'stripe_transaction_id' );
        }

        // Subscription orders: no direct transaction_id — resolve via subscription's latest invoice.
        if ( empty( $pi_id ) ) {
            $sub_id = (string) $order->get_meta( 'stripe_subscription_id' );
            if ( ! empty( $sub_id ) ) {
                try {
                    $stripe      = new \Stripe\StripeClient( $this->secret_key );
                    $sub         = $stripe->subscriptions->retrieve( $sub_id, [ 'expand' => [ 'latest_invoice.payment_intent' ] ] );
                    $pi_id       = $sub->latest_invoice->payment_intent->id ?? '';

                    error_log( sprintf(
                        '[GWC Stripe] process_refund: resolved PI via subscription %s → %s',
                        $sub_id, $pi_id
                    ) );
                } catch ( \Throwable $e ) {
                    error_log( '[GWC Stripe] process_refund: failed to resolve PI from subscription: ' . $e->getMessage() );
                }
            }
        }

        if ( empty( $pi_id ) ) {
            // Debug: dump stripe/payment meta so we can identify the correct key.
            $all_meta    = $order->get_meta_data();
            $meta_summary = [];
            foreach ( $all_meta as $meta ) {
                $key = $meta->key;
                if ( stripos( $key, 'stripe' ) !== false || stripos( $key, 'transaction' ) !== false || stripos( $key, 'payment' ) !== false ) {
                    $meta_summary[ $key ] = $meta->value;
                }
            }
            error_log( sprintf(
                '[GWC Stripe] process_refund DEBUG order=%d transaction_id="%s" stripe+payment meta=%s',
                $order_id,
                $order->get_transaction_id(),
                wp_json_encode( $meta_summary )
            ) );

            return new WP_Error(
                'missing_transaction_id',
                __( 'Cannot refund: no Stripe PaymentIntent ID found on this order.', 'growtype-wc' )
            );
        }

        $refund_amount = ( $amount !== null )
            ? intval( round( (float) $amount * 100 ) )        // Stripe uses cents
            : intval( round( (float) $order->get_total() * 100 ) );

        $refund_params = [
            'payment_intent' => $pi_id,
            'amount'         => $refund_amount,
        ];

        if ( ! empty( $reason ) ) {
            // Stripe accepts: 'duplicate', 'fraudulent', 'requested_by_customer'
            $stripe_reasons = [ 'duplicate', 'fraudulent', 'requested_by_customer' ];
            if ( in_array( $reason, $stripe_reasons, true ) ) {
                $refund_params['reason'] = $reason;
            }
        }

        try {
            $stripe  = new \Stripe\StripeClient( $this->secret_key );
            $refund  = $stripe->refunds->create( $refund_params );
            $refund_id = $refund->id ?? '';
            $status    = $refund->status ?? '';

            error_log( sprintf(
                '[GWC Stripe] process_refund: order=%d pi=%s refund_id=%s status=%s',
                $order_id, $pi_id, $refund_id, $status
            ) );

            if ( ! in_array( $status, [ 'succeeded', 'pending' ], true ) ) {
                return new WP_Error(
                    'refund_failed',
                    sprintf( __( 'Stripe refund status: %s', 'growtype-wc' ), $status )
                );
            }

            $order->add_order_note( sprintf(
                __( 'Stripe refund of %1$s %2$s processed. Refund ID: %3$s.%4$s', 'growtype-wc' ),
                number_format( (float) $amount ?? $order->get_total(), 2 ),
                $order->get_currency(),
                $refund_id,
                ! empty( $reason ) ? ' Reason: ' . $reason : ''
            ) );

            return true;

        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( '[GWC Stripe] process_refund API error: ' . $e->getMessage() );
            return new WP_Error( 'stripe_error', $e->getMessage() );
        } catch ( \Throwable $e ) {
            error_log( '[GWC Stripe] process_refund error: ' . $e->getMessage() );
            return new WP_Error( 'refund_error', $e->getMessage() );
        }
    }
}
