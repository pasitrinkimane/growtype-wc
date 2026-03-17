<?php

class Growtype_Wc_Upsell_Modal
{
    public static function init()
    {
        if (apply_filters('growtype_wc_upsell_modal_enabled', true)) {
            add_action('wp_footer', [self::class, 'render_modal'], 40);
            add_action('wp_ajax_' . Growtype_Wc_Upsell::DISMISS_AJAX_ACTION, [self::class, 'ajax_dismiss_upsell']);
            add_action('wp_ajax_' . Growtype_Wc_Upsell::GET_ITEM_AJAX_ACTION, [self::class, 'ajax_get_upsell_item']);
        }
    }

    public static function render_modal()
    {
        $is_allowed = apply_filters('growtype_wc_upsell_modal_render_allowed', is_user_logged_in());

        if (!$is_allowed) {
            return;
        }

        $user_id = get_current_user_id();
        $queue_ids = Growtype_Wc_Upsell_Queue::get_validated_queue($user_id);

        if (empty($queue_ids)) {
            return;
        }

        $current_url = Growtype_Wc_Upsell_Return_Url::get_current_request_url();

        if (empty($current_url)) {
            return;
        }

        $modal_args = apply_filters('growtype_wc_upsell_modal_args', [
            'modal_id' => Growtype_Wc_Upsell::MODAL_ID,
            'ajax_action' => Growtype_Wc_Upsell::DISMISS_AJAX_ACTION,
            'ajax_get_item_action' => Growtype_Wc_Upsell::GET_ITEM_AJAX_ACTION,
            'ajax_nonce' => wp_create_nonce('growtype_wc_ajax_nonce'),
            'upsell_ids' => $queue_ids,
            'auto_show_delay' => 0,
        ], $user_id);

        echo growtype_wc_include_view('components.modal.upsell', $modal_args);
    }

    public static function ajax_get_upsell_item()
    {
        check_ajax_referer('growtype_wc_ajax_nonce', 'nonce');

        $product_id = absint(wp_unslash($_GET['product_id'] ?? 0));

        if ($product_id < 1) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'growtype-wc')], 400);
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(['message' => __('Product not found.', 'growtype-wc')], 404);
        }

        $current_url = Growtype_Wc_Upsell_Return_Url::sanitize(wp_unslash($_GET['current_url'] ?? ''));

        if (empty($current_url)) {
            $current_url = home_url('/');
        }

        wp_send_json_success(self::format_upsell_item($product, $current_url));
    }

    public static function ajax_dismiss_upsell()
    {
        check_ajax_referer('growtype_wc_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('Unauthorized.', 'growtype-wc'),
            ], 403);
        }

        $product_id = absint(wp_unslash($_POST['product_id'] ?? 0));

        if ($product_id < 1) {
            wp_send_json_error([
                'message' => __('Invalid upsell.', 'growtype-wc'),
            ], 400);
        }

        $user_id = get_current_user_id();
        $queue_ids = Growtype_Wc_Upsell_Queue::dismiss_product($user_id, $product_id);

        wp_send_json_success(apply_filters('growtype_wc_dismiss_upsell_response', [
            'queue_length' => count($queue_ids),
            'product_id' => $product_id,
        ], $user_id));
    }

    private static function format_upsell_item($product, $return_url): array
    {
        $product_id = $product->get_id();
        $image_url = self::get_product_image_url($product);

        $item_data = [
            'product_id' => $product_id,
            'title' => $product->get_title(),
            'short_description' => apply_filters('growtype_the_content', $product->get_short_description()),
            'description' => apply_filters('growtype_the_content', $product->get_description()),
            'price_html' => $product->get_price_html(),
            'promo_label_html' => Growtype_Wc_Product::get_promo_label_formatted($product_id),
            'discount_label_html' => Growtype_Wc_Product::get_discount_percentage_label_formatted($product_id),
            'extra_details_html' => Growtype_Wc_Product::get_extra_details_formatted($product_id),
            'image_url' => $image_url,
            'button_html' => self::render_payment_button($product, $return_url),
        ];

        return apply_filters('growtype_wc_upsell_modal_item_data', $item_data, $product, $return_url);
    }

    private static function get_product_image_url($product): string
    {
        $image_id = $product->get_image_id();

        if (!empty($image_id)) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');

            if (!empty($image_url)) {
                return $image_url;
            }
        }

        return '';
    }

    private static function render_payment_button($product, $return_url): string
    {
        $product_id = $product->get_id();
        $button_text = get_post_meta($product_id, '_add_to_cart_button_custom_text', true);

        if (empty($button_text)) {
            $price = $product->get_price();
            $currency_symbol = get_woocommerce_currency_symbol();
            $button_text = sprintf(__('Unlock Now - %s%s', 'growtype-wc'), $currency_symbol, $price);
        }

        $return_url = Growtype_Wc_Upsell_Return_Url::sanitize($return_url);
        $return_query_key = Growtype_Wc_Upsell_Return_Url::get_query_arg_name();
        $saved_payment_url = '';

        if (is_user_logged_in() && class_exists('Growtype_Wc_Payment') && Growtype_Wc_Payment::user_can_repeat_purchase()) {
            $saved_payment_url = Growtype_Wc_Payment::get_repeat_purchase_url($product_id, $return_url);
        }

        if (!empty($saved_payment_url)) {
            return '<div class="gwc-upsell-payment-instant">
                        <a href="' . esc_url($saved_payment_url) . '" class="btn btn-primary btn-lg w-100 mx-auto"
                           style="font-size: 1.05rem; min-height: 50px;">' . esc_html($button_text) . '</a>
                    </div>';
        }

        $fallback_url = add_query_arg([
            'add-to-cart' => $product_id,
            'payment_method' => 'gwc-stripe',
        ], wc_get_checkout_url());

        if (!empty($return_url)) {
            $fallback_url = add_query_arg([
                $return_query_key => rawurlencode($return_url),
            ], $fallback_url);
        }

        return '<div class="growtype-wc-payment-button btn btn-primary btn-lg w-100 mx-auto"
                    style="font-size: 1.05rem; min-height: 50px;"
                    data-provider="stripe"
                    data-product-id="' . esc_attr($product_id) . '"
                    data-provider-extra-class=""
                    data-method="applePay,googlePay"
                    data-type="express"
                    data-label="' . esc_attr($button_text) . '"
                    data-return-url="' . esc_url($return_url) . '"
                    data-fallback="' . esc_url($fallback_url) . '">
                </div>';
    }
}
