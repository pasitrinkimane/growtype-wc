<?php

class Growtype_Wc_Activator
{
    protected static $plugin_file;

    // Initialize hooks
    public static function init($plugin_file)
    {
        self::$plugin_file = $plugin_file;

        // Register activation hook
        register_activation_hook($plugin_file, [__CLASS__, 'activate']);

        // Admin notice
        add_filter('wp_admin_notice_markup', [__CLASS__, 'custom_admin_notice_markup'], 10, 3);

        // Safe deactivation
        add_action('admin_init', [__CLASS__, 'maybe_deactivate']);
    }

    // Activation callback
    public static function activate()
    {
        if (!class_exists('WooCommerce')) {
            set_transient('growtype_wc_activation_error', true, 10);
        }
    }

    // Show admin notice
    public static function custom_admin_notice_markup($markup, $message, $args)
    {
        if (get_transient('growtype_wc_activation_error')) {
            $markup = '<div id="message" class="notice is-dismissible error"><p><strong>Growtype WooCommerce Plugin:</strong> Please install and activate WooCommerce first.</p></div>';
            delete_transient('growtype_wc_activation_error');
        }

        return $markup;
    }

    // Deactivate plugin if WooCommerce is missing
    public static function maybe_deactivate()
    {
        if (get_transient('growtype_wc_activation_error')) {
            deactivate_plugins(self::$plugin_file);
        }
    }
}
