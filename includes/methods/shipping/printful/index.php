<?php

use Printful\Exceptions\PrintfulApiException;
use Printful\Exceptions\PrintfulException;
use Printful\PrintfulApiClient;

add_action('woocommerce_shipping_init', 'growtype_wc_printful_shipping_init');
function growtype_wc_printful_shipping_init()
{
    if (!class_exists('WC_Printful_Shipping_Standard_Method')) {
        require_once('partials/WC_Printful_Shipping_Standard_Method.php');
    }

    if (!class_exists('WC_Printful_Shipping_Standard_Carbon_Offset_Method')) {
        require_once('partials/WC_Printful_Shipping_Standard_Carbon_Offset_Method.php');
    }
}

add_filter('woocommerce_shipping_methods', 'growtype_wc_printful_shipping_method');
function growtype_wc_printful_shipping_method($methods)
{
    $methods['printful_shipping_standard'] = 'WC_Printful_Shipping_Standard_Method';
    $methods['printful_shipping_standard_carbon_offset'] = 'WC_Printful_Shipping_Standard_Carbon_Offset_Method';

    return $methods;
}

//add_action('woocommerce_checkout_update_order_review', 'action_woocommerce_checkout_update_order_review');
function action_woocommerce_checkout_update_order_review($posted_data)
{
//    d(WC()->cart->get_shipping_packages());
//    growtype_wc_update_shipping_details();
}

//    add_action('woocommerce_calculated_shipping', 'wp_kama_woocommerce_calculated_shipping_action');
function wp_kama_woocommerce_calculated_shipping_action()
{
    WC()->cart->calculate_shipping();
}

/**
 * @return void
 * Calculate additional fees
 */
//    add_action('woocommerce_cart_calculate_fees', 'woo_add_cart_fee');
function woo_add_cart_fee()
{
    global $woocommerce;

    $woocommerce->cart->add_fee(__('Shipping price:', 'growtype-wc'), 15);
}

function growtype_wc_printful_available_methods()
{
    $secret = get_option('woocommerce_plugins_printful_token');

    $pf = PrintfulApiClient::createOauthClient($secret);

    $methods = [
        'STANDARD' => 'printful_shipping_standard',
        'STANDARD_CARBON_OFFSET' => 'printful_shipping_standard_carbon_offset'
    ];

    $available_methods = [];

    $recipient['country_code'] = growtype_wc_get_customer_submitted_address_single_detail('country_code');
    $recipient['state_code'] = growtype_wc_get_customer_submitted_address_single_detail('state_code');

    if (!empty($calc_shipping_city)) {
        $recipient['city'] = growtype_wc_get_customer_submitted_address_single_detail('city');
    }

    if (!empty($calc_shipping_postcode)) {
        $recipient['zip'] = growtype_wc_get_customer_submitted_address_single_detail('zip');
    }

    $products = [];
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $variation_id = $cart_item['variation_id'];
        $products[] = [
            'variant_id' => (int)get_post_meta($variation_id, 'custom_meta_variant_id', true),
            'quantity' => $cart_item['quantity']
        ];
    }

    try {
        $pf_rates = $pf->post('shipping/rates', [
            'recipient' => $recipient,
            'items' => $products,
        ]);

        foreach ($methods as $method_id => $method) {
            $existing_rate = [];
            foreach ($pf_rates as $pf_rate) {
                if ($pf_rate['id'] === $method_id) {
                    $existing_rate = $pf_rate;
                }
            }

            $available_methods[$method] = $existing_rate;
        }
    } catch (PrintfulApiException $e) {
        error_log('Printful API Exception: ' . $e->getCode() . ' ' . $e->getMessage());
    } catch (PrintfulException $e) { //API call failed
        error_log($pf->getLastResponseRaw());
    }

    return $available_methods;
}
