<?php

require_once('printful/index.php');

/**
 * Update shipping cost based on address¬
 */
add_filter('woocommerce_package_rates', 'update_shipping_cost_based_on_address', 10, 2);
function update_shipping_cost_based_on_address($rates, $package)
{
    if (!empty($rates)) {
        $rates = growtype_wc_update_shipping_details($rates);
    }

    return $rates;
}

function growtype_wc_update_shipping_details($rates)
{
    if (!empty(WC()) && !empty(WC()->cart)) {
        $printful_methods = get_option('woocommerce_plugins_printful_enabled') && function_exists('growtype_wc_printful_available_methods') ? growtype_wc_printful_available_methods() : null;

        foreach ($rates as $rate_key => $rate) {
            if (!empty($printful_methods) && isset($printful_methods[$rate->method_id])) {
                if (!empty($printful_methods[$rate->method_id])) {
                    $method = $printful_methods[$rate->method_id];

                    $rate->label = $method['name'];
                    $rate->cost = $method['rate'];
                } else {
                    unset($rates[$rate_key]);
                }
            }
        }
    }

    return $rates;
}

function growtype_wc_get_customer_submitted_address_single_detail($type)
{
    return growtype_wc_get_customer_submitted_address_details()[$type];
}

function growtype_wc_get_customer_submitted_address_details()
{
    $calc_shipping_country = 'US';

    $customer = WC()->session->get('customer');

    if (isset($customer['shipping_country'])) {
        $calc_shipping_country = $customer['shipping_country'];
    } elseif (isset($customer['country'])) {
        $calc_shipping_country = $customer['country'];
    }

    if (isset($_POST['calc_shipping_country']) || isset($_POST['s_country'])) {
        $calc_shipping_country = isset($_POST['calc_shipping_country']) ? $_POST['calc_shipping_country'] : $_POST['s_country'];
    }

    $calc_shipping_state = $calc_shipping_country === 'US' ? 'CA' : '';

    if (isset($_POST['calc_shipping_state']) || isset($_POST['s_state'])) {
        $calc_shipping_state = isset($_POST['calc_shipping_state']) ? $_POST['calc_shipping_state'] : $_POST['s_state'];
    }


    $calc_shipping_city = '';

    if (isset($_POST['calc_shipping_city']) || isset($_POST['s_city'])) {
        $calc_shipping_city = isset($_POST['calc_shipping_city']) ? $_POST['calc_shipping_city'] : $_POST['s_city'];
    }


    $calc_shipping_postcode = '';

    if (isset($_POST['calc_shipping_postcode']) || isset($_POST['s_postcode'])) {
        $calc_shipping_postcode = isset($_POST['calc_shipping_postcode']) ? $_POST['calc_shipping_postcode'] : $_POST['s_postcode'];
    }

    return [
        'country_code' => $calc_shipping_country,
        'state_code' => $calc_shipping_state,
        'city' => $calc_shipping_city,
        'zip' => $calc_shipping_postcode
    ];
}
