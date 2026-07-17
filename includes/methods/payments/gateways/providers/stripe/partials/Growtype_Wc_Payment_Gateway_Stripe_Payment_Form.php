<?php

class Growtype_Wc_Payment_Gateway_Stripe_Payment_Form
{
    /**
     * Register the AJAX action that dynamically renders the Stripe payment form.
     */
    public static function init(): void
    {
        add_action("wp_ajax_gwc_stripe_payment_form", [
            self::class,
            "ajax_render",
        ]);
        add_action("wp_ajax_nopriv_gwc_stripe_payment_form", [
            self::class,
            "ajax_render",
        ]);
    }

    /**
     * AJAX handler for rendering Stripe express form inside the shared payment modal.
     */
    public static function ajax_render(): void
    {
        check_ajax_referer("growtype_wc_ajax_nonce", "nonce");

        $product_id = absint($_POST["product_id"] ?? 0);
        $methods_raw = sanitize_text_field(
            $_POST["methods"] ?? "applepay,googlepay",
        );
        $container_id = "gwc-stripe-express-" .
            $product_id .
            "-" .
            wp_rand(1000, 9999);

        $html = self::render([
            "product_id" => $product_id,
            "container_id" => $container_id,
            "methods" => $methods_raw,
        ]);

        $product = function_exists("wc_get_product")
            ? wc_get_product($product_id)
            : null;
        $total_price = "";
        if ($product) {
            $total_price = html_entity_decode(
                strip_tags(wc_price($product->get_price())),
            );
        }

        wp_send_json_success([
            "html" => $html,
            "container_id" => $container_id,
            "product_id" => $product_id,
            "methods" => $methods_raw,
            "total_price" => $total_price,
        ]);
    }

    /**
     * Render Stripe express checkout container for modal usage.
     */
    public static function render(array $args = []): string
    {
        $product_id = absint($args["product_id"] ?? 0);
        $container_id = sanitize_html_class(
            $args["container_id"] ??
                ("gwc-stripe-express-" . $product_id . "-" . wp_rand(1000, 9999)),
        );
        $methods_raw = sanitize_text_field(
            $args["methods"] ?? "applepay,googlepay",
        );

        ob_start(); ?>
        <div class="gwc-payment-form gwc-payment-form--stripe" data-product-id="<?php echo esc_attr(
            $product_id,
        ); ?>">
            <div id="<?php echo esc_attr($container_id); ?>"
                 class="gwc-payment-form__express"
                 data-product-id="<?php echo esc_attr($product_id); ?>"
                 data-method="<?php echo esc_attr($methods_raw); ?>"
                 style="min-height:48px; position:relative;">
                <div class="gwc-paypal-form-loader" style="display:flex; align-items:center; justify-content:center;">
                    <div class="gwc-hf-spinner"></div>
                </div>
            </div>

            <?php if (
                class_exists(
                    "Growtype_Wc_Payment_Gateway_Paypal_Payment_Form",
                ) &&
                method_exists(
                    "Growtype_Wc_Payment_Gateway_Paypal_Payment_Form",
                    "render_badge",
                )
            ) {
                Growtype_Wc_Payment_Gateway_Paypal_Payment_Form::render_badge();
            } ?>
        </div>
        <?php return (string) ob_get_clean();
    }
}
