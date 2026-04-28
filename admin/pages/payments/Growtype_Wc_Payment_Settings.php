<?php

/**
 * Growtype_Wc_Payment_Settings
 *
 * Renders Growtype payment settings inside WooCommerce → Settings → Payments
 * (tab=checkout). Because that tab now renders its main view via React, the
 * classic section sidebar is not shown; instead we output our fields directly
 * into the classic <form> / <table> that WooCommerce still renders below the
 * React block whenever no gateway-specific section is active.
 *
 * The settings are visible on the main Payments page and are saved via the
 * standard WooCommerce settings save flow.
 */
class Growtype_Wc_Payment_Settings
{
    /** WordPress option key for the primary payment method setting. */
    const OPTION_PRIMARY_METHOD = 'growtype_wc_primary_payment_method';
    
    /** Setting to redirect to PayPal immediately after adding to cart. */
    const OPTION_REDIRECT_PAYPAL = 'growtype_wc_paypal_redirect_after_add_to_cart';

    public function __construct()
    {
        // Output fields on the main Checkout/Payments tab (no section active)
        add_action('woocommerce_settings_checkout', [$this, 'output']);

        // Save fields when the form is submitted on that page
        add_action('woocommerce_settings_save_checkout', [$this, 'save']);
    }

    // -------------------------------------------------------------------------
    // Output & Save
    // -------------------------------------------------------------------------

    /**
     * Output fields on the main Payments tab (no gateway section active).
     * Gateway-specific sections (e.g. ?section=growtype_wc_paypal) are handled
     * by each gateway class and should be left alone.
     */
    public function output(): void
    {
        $section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';

        // Only render on the top-level Payments page, not inside gateway sections
        if ($section !== '') {
            return;
        }

        WC_Admin_Settings::output_fields($this->get_fields());
    }

    public function save(): void
    {
        $section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';

        if ($section !== '') {
            return;
        }

        WC_Admin_Settings::save_fields($this->get_fields());
    }

    // -------------------------------------------------------------------------
    // Field definitions
    // -------------------------------------------------------------------------

    private function get_fields(): array
    {
        return [
            [
                'title' => __('Growtype Wc - Payment Settings', 'growtype-wc'),
                'type'  => 'title',
                'id'    => 'growtype_payments_section_start',
            ],
            [
                'title'    => __('Primary Payment Method', 'growtype-wc'),
                'desc'     => __('Gateway used for express checkout buttons and PayPal fallback redirects. "Auto" picks Stripe when enabled, otherwise PayPal.', 'growtype-wc'),
                'id'       => self::OPTION_PRIMARY_METHOD,
                'type'     => 'select',
                'default'  => 'auto',
                'options'  => [
                    'auto'       => __('Auto', 'growtype-wc'),
                    'gwc-paypal' => __('PayPal', 'growtype-wc'),
                    'gwc-stripe' => __('Stripe', 'growtype-wc'),
                ],
                'desc_tip' => true,
            ],
            [
                'title'   => __('Direct PayPal Redirect', 'growtype-wc'),
                'desc'    => __('Automatically redirect to PayPal checkout when a product is added to cart with ?payment_method=gwc-paypal.', 'growtype-wc'),
                'id'      => self::OPTION_REDIRECT_PAYPAL,
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'growtype_payments_section_end',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Static helper — used by Growtype_Wc_Child_Payment::get_primary_payment_method_id()
    // -------------------------------------------------------------------------

    /**
     * Resolve the active primary payment method ID (gwc-paypal or gwc-stripe).
     * Falls back to auto-detection when the option is set to 'auto'.
     */
    public static function get_primary_method_id(): string
    {
        $setting = get_option(self::OPTION_PRIMARY_METHOD, 'auto');

        if ($setting !== 'auto') {
            return $setting;
        }

        // Auto: prefer Stripe when enabled, otherwise PayPal
        $stripe_enabled = function_exists('growtype_wc_payment_method_is_enabled')
            && growtype_wc_payment_method_is_enabled(Growtype_Wc_Payment_Gateway_Stripe::PROVIDER_ID);

        return $stripe_enabled ? 'gwc-stripe' : 'gwc-paypal';
    }
}
