<?php

class Growtype_Wc_Upsell_Return_Url
{
    const ORDER_META_KEY = '_growtype_return_after_payment_url';
    const QUERY_ARG = 'growtype_return_after_payment_url';

    public static function init()
    {
        if (apply_filters('growtype_wc_traditional_upsells_enabled', false)) {
            add_filter('woocommerce_get_return_url', [self::class, 'filter_legacy'], 30, 2);
        }

        if (apply_filters('growtype_wc_upsell_modal_enabled', true)) {
            add_action('template_redirect', [self::class, 'redirect_paid_thankyou_to_return_url'], 20);
        }
    }

    public static function redirect_paid_thankyou_to_return_url()
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!function_exists('is_order_received_page') || !is_order_received_page()) {
            return;
        }

        $order = self::resolve_order_from_request();

        if (!$order || !$order->is_paid()) {
            return;
        }

        $return_url = self::get_from_order($order);

        if (empty($return_url)) {
            return;
        }

        wp_safe_redirect($return_url);
        exit();
    }

    public static function get_query_arg_name(): string
    {
        if (class_exists('Growtype_Wc_Child_Payment')) {
            return Growtype_Wc_Child_Payment::RETURN_URL_QUERY_ARG;
        }

        return self::QUERY_ARG;
    }

    public static function get_requested_from_query(): string
    {
        $query_arg = self::get_query_arg_name();

        if (!isset($_GET[$query_arg])) {
            return '';
        }

        return self::sanitize(rawurldecode(wp_unslash($_GET[$query_arg])));
    }

    public static function get_current_request_url(): string
    {
        if (empty($_SERVER['HTTP_HOST']) || empty($_SERVER['REQUEST_URI'])) {
            return '';
        }

        $scheme = is_ssl() ? 'https' : 'http';

        return self::sanitize($scheme . '://' . wp_unslash($_SERVER['HTTP_HOST']) . wp_unslash($_SERVER['REQUEST_URI']));
    }

    public static function get_from_order($order, bool $allow_query_fallback = true): string
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return '';
        }

        $return_url = $order->get_meta(self::ORDER_META_KEY);

        if (empty($return_url) && $allow_query_fallback) {
            $return_url = self::get_requested_from_query();
        }

        return self::sanitize($return_url);
    }

    public static function get_explicit($order): string
    {
        return self::get_from_order($order);
    }

    public static function persist_on_order($order, $return_url = null): string
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return '';
        }

        if ($return_url === null || $return_url === '') {
            $return_url = self::get_requested_from_query();
        }

        $return_url = self::sanitize($return_url);

        if (!empty($return_url)) {
            $order->update_meta_data(self::ORDER_META_KEY, $return_url);
        }

        return $return_url;
    }

    public static function resolve_order_from_request()
    {
        $order_id = absint(get_query_var('order-received'));

        if ($order_id < 1 && isset($_GET['order-received'])) {
            $order_id = absint(wp_unslash($_GET['order-received']));
        }

        if ($order_id < 1 && !empty($_GET['key'])) {
            $order_key = wc_clean(wp_unslash($_GET['key']));
            $order_id = (int)wc_get_order_id_by_order_key($order_key);
        }

        if ($order_id < 1) {
            return null;
        }

        return wc_get_order($order_id);
    }

    public static function sanitize($url, array $args = []): string
    {
        $defaults = [
            'allow_checkout' => false,
        ];

        $args = wp_parse_args($args, $defaults);

        if (empty($url) || !is_string($url)) {
            return '';
        }

        $url = trim($url);

        if (empty($url)) {
            return '';
        }

        if (0 === strpos($url, '/')) {
            $url = home_url($url);
        }

        $url = esc_url_raw($url);

        if (empty($url)) {
            return '';
        }

        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);

        if (!empty($url_host) && !empty($site_host) && strtolower($url_host) !== strtolower($site_host)) {
            return '';
        }

        $path = wp_parse_url($url, PHP_URL_PATH);

        if (!empty($path) && false !== strpos($path, '/wp-admin')) {
            return '';
        }

        if (!$args['allow_checkout'] && !empty($path)) {
            $normalized = untrailingslashit((string)$path);
            // Block pure checkout paths but allow the order-received thank-you page
            $is_checkout = false !== strpos($normalized, '/checkout');
            $is_order_received = false !== strpos($normalized, '/order-received');
            if ($is_checkout && !$is_order_received) {
                return '';
            }
        }

        return $url;
    }

    public static function filter_legacy($return_url, $order)
    {
        if (!$order || !is_a($order, 'WC_Order') || $order->get_meta('_growtype_wc_upsell_processed')) {
            return $return_url;
        }

        if (!empty(self::get_from_order($order))) {
            return $return_url;
        }

        $products = Growtype_Wc_Upsell_Catalog::get_products();

        if (empty($products)) {
            return $return_url;
        }

        return add_query_arg([
            'upsell' => $products[0]->get_slug(),
        ], $return_url);
    }
}
