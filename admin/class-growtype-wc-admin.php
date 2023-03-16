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
        $this->Growtype_Wc = $growtype_wc;
        $this->version = $version;

//        add_action('admin_menu', array ($this, 'admin_menu'));
//        add_action('admin_init', array ($this, 'growtype_wc_options_setting'));
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Growtype_Wc_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Growtype_Wc_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style($this->Growtype_Wc, plugin_dir_url(__FILE__) . 'css/growtype-wc-admin.css', array (), $this->version, 'all');

    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Growtype_Wc_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Growtype_Wc_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script($this->Growtype_Wc, plugin_dir_url(__FILE__) . 'js/growtype-wc-admin.js', array ('jquery'), $this->version, false);

    }

    /**
     * Register the options page with the Wordpress menu.
     */
    function admin_menu()
    {
        add_options_page(
            __('Growtype - Wc', 'growtype'),
            __('Growtype - Wc', 'growtype'),
            'manage_options',
            'growtype-wc-options',
            array ($this, 'growtype_wc_options_content'),
            1
        );
    }

    function growtype_wc_options_content()
    {
        echo '<div class="wrap">
	<h1>Growtype - Wc plugin options</h1>
	<form method="post" action="options.php">';

        settings_fields('growtype_wc_options_settings'); // settings group name
        do_settings_sections('growtype-wc-options'); // just a page slug
        submit_button();

        echo '</form></div>';
    }

    function growtype_wc_options_setting()
    {

    }
}
