<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Wc
 * @subpackage Growtype_Wc/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Growtype_Wc
 * @subpackage Growtype_Wc/admin
 * @author     Your Name <email@example.com>
 */
class Growtype_Wc_Admin
{

    const GROWTYPE_WC_SETTINGS_DEFAULT_TAB = 'general';

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
     * @param string $growtype_wc The name of this plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($growtype_wc, $version)
    {
        $this->growtype_wc = $growtype_wc;
        $this->version = $version;

        $this->load_methods();
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->growtype_wc, plugin_dir_url(__FILE__) . 'css/growtype-wc-admin.css', array (), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->growtype_wc, plugin_dir_url(__FILE__) . 'js/growtype-wc-admin.js', array ('jquery'), $this->version, false);
    }

    function load_methods()
    {
        /**
         * Plugin settings
         */
        require GROWTYPE_WC_PATH . '/admin/pages/growtype-wc-admin-pages.php';
        new Growtype_Wc_Admin_Pages();
    }
}
