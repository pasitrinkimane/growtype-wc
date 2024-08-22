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
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Growtype_Wc
 * @subpackage Growtype_Wc/public
 * @author     Your Name <email@example.com>
 */
class Growtype_Wc_Public
{

    const AJAX_ACTION = 'growtype_wc';

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $growtype_wc The ID of this plugin.
     */
    private $growtype_wc;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $growtype_wc The name of the plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($growtype_wc, $version)
    {
        $this->growtype_wc = $growtype_wc;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->growtype_wc, GROWTYPE_WC_URL_PUBLIC . 'styles/growtype-wc.css', array (), $this->version, 'all');

        /**
         * libs
         */
        /**
         * Countdown
         */
        wp_enqueue_style('growtype-wc-countdown', GROWTYPE_WC_URL_PUBLIC . 'libs/jquery-countdown/jquery.countdown.css', array (), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->growtype_wc, plugin_dir_url(__FILE__) . 'scripts/growtype-wc.js', array ('jquery'), $this->version, true);

        /**
         * libs
         */
        /**
         * Countdown
         */
        wp_enqueue_script('growtype-wc-countdown', plugin_dir_url(__FILE__) . 'libs/jquery-countdown/jquery.countdown.min.js', array ('jquery'), $this->version, true);
        wp_register_script('growtype-wc-countdown-language', plugin_dir_url(__FILE__) . 'libs/jquery-countdown/jquery.countdown.language.js', array ('jquery', 'simple-auction-countdown'), $this->version, true);

        $ajax_url = admin_url('admin-ajax.php');

        if (class_exists('QTX_Translator')) {
            $ajax_url = admin_url('admin-ajax.php' . '?lang=' . qtranxf_getLanguage());
        }

        $email = class_exists('Growtype_Analytics') ? growtype_analytics_get_user_email() : '';

        $cart_total = 0;
        if (!empty(WC()) && !empty(WC()->cart->cart_contents_total)) {
            $cart_total = WC()->cart->cart_contents_total;
        }

        wp_localize_script($this->growtype_wc, 'growtype_wc_ajax', array (
            'url' => $ajax_url,
            'nonce' => wp_create_nonce('ajax-nonce'),
            'action' => self::AJAX_ACTION,
            'rest_url' => rest_url('wp/v2/product'),
            'shop_name' => sanitize_title_with_dashes(sanitize_title_with_dashes(get_bloginfo('name'))),
            'in_wishlist_text' => esc_html__("Already in wishlist", "growtype-wc"),
            'remove_from_wishlist_text' => esc_html__("Remove from wishlist", "growtype-wc"),
            'error_text' => esc_html__("Something went wrong, please contact our support", "growtype-wc"),
            'no_wishlist_text' => esc_html__("No wishlist found", "growtype-wc"),
            'fill_required_fields_text' => esc_html__("Please fill all required fields", "growtype-wc"),
            'currency' => get_woocommerce_currency(),
            'wc_version' => defined('WC_VERSION') ? WC_VERSION : null,
            'items_gtm' => growtype_wc_get_cart_items_gtm(),
            'cart_total' => $cart_total,
            'user_id' => get_current_user_id(),
            'email' => apply_filters('growtype_wc_get_user_email', $email),
        ));
    }
}
