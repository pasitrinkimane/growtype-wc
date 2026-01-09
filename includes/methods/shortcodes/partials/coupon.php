<?php

class Growtype_Wc_Coupon_Shortcode
{
    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            /**
             * Woocommerce coupon discount
             */
            add_shortcode('growtype_wc_coupon_form', array ($this, 'growtype_wc_coupon_form_callback'));

            /**
             * Woocommerce coupon discount
             */
            add_shortcode('growtype_wc_coupon_discount', array ($this, 'growtype_wc_coupon_discount_callback'));
        }

        add_action('wp_loaded', function () {
            if (self::coupon_form_visible()) {
                $this->set_coupon_form_visible_cookie();
            }

            $this->apply_coupon_by_default();
        });
    }

    public function set_coupon_form_visible_cookie()
    {
        setcookie(self::coupon_form_visible_transient_key(), true, time() + (30 * 24 * 60 * 60), '/');
    }

    public function apply_coupon_by_default()
    {
        if (is_admin()) {
            return;
        }

        if (!function_exists('WC') || is_null(WC()->cart)) {
            return;
        }

        if (!isset($_GET['growtype_wc_coupon'])) {
            return;
        }

        $coupon_code = sanitize_text_field($_GET['growtype_wc_coupon']);

        if (empty($coupon_code)) {
            return;
        }

        if (!WC()->cart->has_discount($coupon_code)) {
            WC()->cart->add_discount($coupon_code);
        }

        $this->set_coupon_form_visible_cookie();

        $redirect_url = remove_query_arg('growtype_wc_coupon');

        wp_safe_redirect($redirect_url);
        exit;
    }

    public static function coupon_form_visible_transient_key()
    {
        return 'growtype_wc_coupon_form_visible_' . self::get_persistent_user_token();
    }

    public static function get_persistent_user_token()
    {
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        $unique_string = $ip_address . $user_agent . '_unique_site_salt';
        $token = md5($unique_string);

        return $token;
    }

    public static function coupon_form_visible()
    {
        return isset($_GET['growtype_wc_coupon_form_visible']) && $_GET['growtype_wc_coupon_form_visible'];
    }

    function growtype_wc_coupon_form_callback($params)
    {
        $preview_on_request = isset($params['preview_on_request']) ? filter_var($params['preview_on_request'], FILTER_VALIDATE_BOOLEAN) : false;

        /**
         * Check if visible on request
         */
        if ($preview_on_request) {
            if (
                !self::coupon_form_visible()
                && !isset($_COOKIE[self::coupon_form_visible_transient_key()])
                && !empty(WC()->cart)
            ) {
                return '';
            }
        }

        ob_start();

        $show_form = true;

        $applied_coupons = !empty(WC()->cart) ? WC()->cart->get_applied_coupons() : false;

        if (!empty($applied_coupons)) {
            $show_form = false;
        }

        $show_form = apply_filters('growtype_wc_show_coupon_form', $show_form);

        if ($show_form) {
            echo self::growtypw_wc_render_coupon_form();
        }

        $show_discount_info = false;

        if (!empty($applied_coupons)) {
            $show_discount_info = true;
        }

        if ($show_discount_info) {
            echo do_shortcode('[growtype_wc_coupon_discount]');
        }

        return ob_get_clean();
    }

    function growtype_wc_coupon_discount_callback($atts)
    {
        if (!WC()->cart) {
            return '';
        }

        ob_start();

        $applied_coupons = WC()->cart->get_applied_coupons();

        if (!empty($applied_coupons)) {
            foreach ($applied_coupons as $applied_coupon_code) {
                $coupon = new WC_Coupon($applied_coupon_code);

                if ($coupon->get_discount_type() === 'percent') {
                    $discount_amount = $coupon->get_amount() . '%';
                } else {
                    $discount_amount = wc_price($coupon->get_amount());
                }

                ?>
                <div class="woocommerce-form-coupon-discount">
                    <div class="woocommerce-message">
                        <?php echo sprintf('Code "%s" was applied with a discount of %s.', esc_html($applied_coupon_code), esc_html($discount_amount)); ?>
                        <button type="button" class="btn-close" aria-label="Close"></button>
                    </div>
                </div>
                <?php
            }
        }

        return ob_get_clean();
    }

    public static function growtypw_wc_render_coupon_form()
    {
        wp_enqueue_script('wc-checkout');
        growtype_wc_enqueue_coupon_scripts();
        echo growtype_wc_include_view('woocommerce/checkout/form-coupon');
    }
}
