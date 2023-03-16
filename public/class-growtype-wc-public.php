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
class Growtype_Wc_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $growtype_wc    The ID of this plugin.
	 */
	private $growtype_wc;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $growtype_wc       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $growtype_wc, $version ) {
		$this->Growtype_Wc = $growtype_wc;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
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
		wp_enqueue_style( $this->Growtype_Wc, plugin_dir_url( __FILE__ ) . 'styles/growtype-wc.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

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

		wp_enqueue_script( $this->Growtype_Wc, plugin_dir_url( __FILE__ ) . 'scripts/growtype-wc.js', array( 'jquery' ), $this->version, false );

	}

}
