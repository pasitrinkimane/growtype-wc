<?php

/**
 * Fired during plugin activation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Wc
 * @subpackage Growtype_Wc/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Growtype_Wc
 * @subpackage Growtype_Wc/includes
 * @author     Your Name <email@example.com>
 */
class Growtype_Wc_Activator
{

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        if (!class_exists('woocommerce')) {
            exit('Please install WooCommerce before activating Growtype WooCommerce plugin');
        }
    }

}
