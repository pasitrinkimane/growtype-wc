<?php

/**
 * Checkout input labels style floating
 */
add_filter('woocommerce_default_address_fields', 'growtype_wc_woocommerce_default_address_fields', 20, 1);
function growtype_wc_woocommerce_default_address_fields($fields)
{
    if (get_theme_mod('woocommerce_checkout_input_label_style') === 'floating') {
        $fields['first_name']['placeholder'] = '';
        $fields['last_name']['placeholder'] = '';
        $fields['country']['placeholder'] = '';
        $fields['address_1']['placeholder'] = '';
        $fields['city']['placeholder'] = '';
        $fields['state']['placeholder'] = '';
        $fields['postcode']['placeholder'] = '';
    }

    $fields['state']['label'] = __('State', 'growtype-wc');

    $fields['postcode']['label'] = __('Zip code', 'growtype-wc');

    /**
     * City
     */
    $state = get_theme_mod('woocommerce_checkout_billing_city', 'required');

    switch ($state) {
        case 'optional':
            $fields['city']['required'] = false;
            break;
        case 'required':
            $fields['city']['required'] = true;
            break;
        case 'hidden':
            unset($fields['city']);
    }

    /**
     * State
     */
    $state = get_theme_mod('woocommerce_checkout_billing_state', 'required');

    switch ($state) {
        case 'optional':
            $fields['state']['required'] = false;
            break;
        case 'required':
            $fields['state']['required'] = true;
            break;
        case 'hidden':
            unset($fields['state']);
    }

    /**
     * Email
     */
    $email = get_theme_mod('woocommerce_checkout_billing_email', 'required');

    switch ($email) {
        case 'optional':
            $fields['email']['required'] = false;
            break;
        case 'required':
            $fields['email']['required'] = true;
            break;
        case 'hidden':
            if (!$fields['email']['required']) {
                array_push($fields['email']['class'], 'd-none');
            }
            break;
    }

    /**
     * country
     */
    $country = get_theme_mod('woocommerce_checkout_billing_country', 'required');

    switch ($country) {
        case 'optional':
            $fields['country']['required'] = false;
            break;
        case 'required':
            $fields['country']['required'] = true;
            break;
        case 'hidden':
            unset($fields['country']);
    }

    /**
     * Address 1
     */
    $address_1 = get_theme_mod('woocommerce_checkout_billing_address_1', 'required');

    switch ($address_1) {
        case 'optional':
            $fields['address_1']['required'] = false;
            break;
        case 'required':
            $fields['address_1']['required'] = true;
            break;
        case 'hidden':
            unset($fields['address_1']);
    }

    /**
     * Postcode
     */
    $postcode = get_theme_mod('woocommerce_checkout_billing_postcode', 'required');

    switch ($postcode) {
        case 'optional':
            $fields['postcode']['required'] = false;
            break;
        case 'required':
            $fields['postcode']['required'] = true;
            break;
        case 'hidden':
            unset($fields['postcode']);
    }

    return $fields;
}

/**
 * Extend checkout fields
 */
add_filter('woocommerce_checkout_fields', 'growtype_wc_woocommerce_checkout_fields');
function growtype_wc_woocommerce_checkout_fields($fields)
{
    $order_notes = get_theme_mod('woocommerce_checkout_order_notes', 'optional');

    if (isset($fields['billing']['billing_city']['class'][0])) {
        $fields['billing']['billing_city']['class'][0] = 'form-row-col-4';
    }
    if (isset($fields['billing']['billing_state']['class'][0])) {
        $fields['billing']['billing_state']['class'][0] = 'form-row-col-4';
    }
    if (isset($fields['billing']['billing_postcode']['class'][0])) {
        $fields['billing']['billing_postcode']['class'][0] = 'form-row-col-4';
    }
    if (isset($fields['shipping']['shipping_city']['class'][0])) {
        $fields['shipping']['shipping_city']['class'][0] = 'form-row-col-4';
    }
    if (isset($fields['shipping']['shipping_state']['class'][0])) {
        $fields['shipping']['shipping_state']['class'][0] = 'form-row-col-4';
    }
    if (isset($fields['shipping']['shipping_postcode']['class'][0])) {
        $fields['shipping']['shipping_postcode']['class'][0] = 'form-row-col-4';
    }

    switch ($order_notes) {
        case 'required':
            $fields['order']['order_comments']['required'] = true;
            break;
        case 'hidden':
            unset($fields['order']['order_comments']);
    }

    if (get_theme_mod('woocommerce_checkout_input_label_style') === 'floating') {
        $fields['billing']['billing_state']['placeholder'] = '';
    }

    return $fields;
}
