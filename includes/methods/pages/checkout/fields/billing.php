<?php

/**
 * Billing fields
 */
add_filter('woocommerce_billing_fields', 'growtype_wc_billing_fields');
function growtype_wc_billing_fields($fields)
{
    /**
     * City
     */
    $state = get_theme_mod('woocommerce_checkout_billing_city', 'required');

    switch ($state) {
        case 'optional':
            $fields['billing_city']['required'] = false;
            break;
        case 'required':
            $fields['billing_city']['required'] = true;
            break;
        case 'hidden':
            unset($fields['billing_city']);
    }

    /**
     * State
     */
    $state = get_theme_mod('woocommerce_checkout_billing_state', 'required');

    switch ($state) {
        case 'optional':
            $fields['billing_state']['required'] = false;
            break;
        case 'required':
            $fields['billing_state']['required'] = true;
            break;
        case 'hidden':
            unset($fields['billing_state']);
    }

    /**
     * Email
     */
    $email = get_theme_mod('woocommerce_checkout_billing_email', 'required');

    switch ($email) {
        case 'optional':
            $fields['billing_email']['required'] = false;
            break;
        case 'required':
            $fields['billing_email']['required'] = true;
            break;
        case 'hidden':
            if (!$fields['billing_email']['required']) {
                array_push($fields['billing_email']['class'], 'd-none');
            }
            break;
    }

    /**
     * country
     */
    $country = get_theme_mod('woocommerce_checkout_billing_country', 'required');

    switch ($country) {
        case 'optional':
            $fields['billing_country']['required'] = false;
            break;
        case 'required':
            $fields['billing_country']['required'] = true;
            break;
        case 'hidden':
            unset($fields['billing_country']);
    }

    /**
     * Address 1
     */
    $address_1 = get_theme_mod('woocommerce_checkout_billing_address_1', 'required');

    switch ($address_1) {
        case 'optional':
            $fields['billing_address_1']['required'] = false;
            break;
        case 'required':
            $fields['billing_address_1']['required'] = true;
            break;
        case 'hidden':
            unset($fields['billing_address_1']);
    }

    /**
     * Postcode
     */
    $postcode = get_theme_mod('woocommerce_checkout_billing_postcode', 'required');

    switch ($postcode) {
        case 'optional':
            $fields['billing_postcode']['required'] = false;
            break;
        case 'required':
            $fields['billing_postcode']['required'] = true;
            break;
        case 'hidden':
            unset($fields['billing_postcode']);
    }

    /**
     * Set default billing values
     */
    if (!empty(get_current_user_id())) {
        if (!empty(get_user_meta(get_current_user_id(), 'first_name', true))) {
            $fields['billing_first_name']['default'] = get_user_meta(get_current_user_id(), 'first_name', true);
        }
        if (!empty(get_user_meta(get_current_user_id(), 'last_name', true))) {
            $fields['billing_last_name']['default'] = get_user_meta(get_current_user_id(), 'last_name', true);
        }
        if (!empty(get_user_meta(get_current_user_id(), 'address_1', true))) {
            $fields['billing_address_1']['default'] = get_user_meta(get_current_user_id(), 'address_1', true);
        }
        if (!empty(get_user_meta(get_current_user_id(), 'city', true))) {
            $fields['billing_city']['default'] = get_user_meta(get_current_user_id(), 'city', true);
        }
        if (!empty(get_user_meta(get_current_user_id(), 'postcode', true))) {
            $fields['billing_postcode']['default'] = get_user_meta(get_current_user_id(), 'postcode', true);
        }
    }

    /**
     * Billing fields
     */
    if (!get_theme_mod('woocommerce_checkout_billing_fields', true)) {
        unset($fields['billing_company']);
        unset($fields['billing_city']);
        unset($fields['billing_postcode']);
        unset($fields['billing_country']);
        unset($fields['billing_state']);
        unset($fields['billing_address_1']);
        unset($fields['billing_address_2']);
        unset($fields['billing_first_name']);
        unset($fields['billing_last_name']);
    }

    return $fields;
}
