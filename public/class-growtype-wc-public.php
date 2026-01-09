<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Wc
 * @subpackage Growtype_Wc/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for enqueueing scripts/styles
 * and localizing cart data only once the script is registered.
 *
 * @package    Growtype_Wc
 * @subpackage Growtype_Wc/public
 */
class Growtype_Wc_Public
{

    const AJAX_ACTION = 'growtype_wc';

    /**
     * Plugin handle.
     *
     * @var string
     */
    private $growtype_wc;

    /**
     * Plugin version.
     *
     * @var string
     */
    private $version;

    /**
     * Initialize and hook into WordPress.
     *
     * @param string $growtype_wc Plugin handle.
     * @param string $version Plugin version.
     */
    public function __construct($growtype_wc, $version)
    {
        $this->growtype_wc = $growtype_wc;
        $this->version = $version;

        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Localize data after scripts have been enqueued (and WC cart loaded)
        add_action('wp_enqueue_scripts', [$this, 'localize_script_data'], 20);

        add_action('woocommerce_cart_loaded_from_session', [$this, 'schedule_footer_injection'], 25);
    }

    /**
     * Enqueue public-facing styles.
     */
    public function enqueue_styles()
    {
        if (!is_admin()) {
            wp_enqueue_style($this->growtype_wc, GROWTYPE_WC_URL_PUBLIC . 'styles/growtype-wc.css', [], $this->version, 'all');
            wp_enqueue_style('growtype-wc-countdown', GROWTYPE_WC_URL_PUBLIC . 'libs/jquery-countdown/jquery.countdown.css', [], $this->version, 'all');
        }
    }

    /**
     * Enqueue public-facing scripts (no cart access here).
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->growtype_wc, plugin_dir_url(__FILE__) . 'scripts/growtype-wc.js', ['jquery'], $this->version, true);
        wp_enqueue_script('growtype-wc-countdown', plugin_dir_url(__FILE__) . 'libs/jquery-countdown/jquery.countdown.min.js', ['jquery'], $this->version, true);
        wp_register_script('growtype-wc-countdown-language', plugin_dir_url(__FILE__) . 'libs/jquery-countdown/jquery.countdown.language.js', ['jquery', 'growtype-wc-countdown'], $this->version, true);
    }

    /**
     * Localize script data, including cart items (only if cart is ready).
     */
    public function localize_script_data()
    {
        // Ensure script is registered before localizing
        $wp_scripts = wp_scripts();
        if (!isset($wp_scripts->registered[$this->growtype_wc])) {
            return;
        }

        // Ensure WooCommerce cart is available
        if (!function_exists('WC') || !WC() || !WC()->cart) {
            return;
        }

        $ajax_url = admin_url('admin-ajax.php');
        if (class_exists('QTX_Translator')) {
            $ajax_url = add_query_arg('lang', qtranxf_getLanguage(), $ajax_url);
        }

        $email = class_exists('Growtype_Analytics') ? growtype_analytics_get_user_email() : '';

        $cart_total = WC()->cart->get_cart_contents_total();

        wp_localize_script(
            $this->growtype_wc,
            'growtype_wc_ajax',
            [
                'url' => $ajax_url,
                'nonce' => wp_create_nonce('growtype_wc_ajax_nonce'), // SECURITY: Updated nonce name to match AJAX handlers
                'action' => self::AJAX_ACTION,
                'rest_url' => rest_url('wp/v2/product'),
                'shop_name' => sanitize_title_with_dashes(sanitize_title_with_dashes(get_bloginfo('name'))),
                'in_wishlist_text' => esc_html__('Already in wishlist', 'growtype-wc'),
                'remove_from_wishlist_text' => esc_html__('Remove from wishlist', 'growtype-wc'),
                'error_text' => esc_html__('Something went wrong, please contact our support', 'growtype-wc'),
                'no_wishlist_text' => esc_html__('No wishlist found', 'growtype-wc'),
                'fill_required_fields_text' => esc_html__('Please fill all required fields', 'growtype-wc'),
                'currency' => get_woocommerce_currency(),
                'wc_version' => defined('WC_VERSION') ? WC_VERSION : null,
                'cart_total' => $cart_total,
                'user_id' => get_current_user_id(),
                'email' => apply_filters('growtype_wc_get_user_email', $email),
            ]
        );
    }

    public function schedule_footer_injection()
    {
        add_action('wp_footer', [$this, 'inject_items_gtm'], 20);
    }

    public function inject_items_gtm()
    {
        $items = growtype_wc_get_cart_items_gtm();
        $json = wp_json_encode($items);
        ?>
        <script type="text/javascript">
            if (window.growtype_wc_ajax) {
                window.growtype_wc_ajax.items_gtm = <?php echo $json; ?>;
            }
        </script>
        <?php
    }
}
