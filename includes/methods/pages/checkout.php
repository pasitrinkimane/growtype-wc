<?php

/**
 * Scripts
 */
add_action('wp_enqueue_scripts', 'growtype_wc_checkout_scripts');
function growtype_wc_checkout_scripts()
{
    if (class_exists('woocommerce') && is_checkout()) {
        wp_enqueue_script('wc-custom-checkout', GROWTYPE_WC_URL_PUBLIC . '/scripts/wc-checkout.js', [], GROWTYPE_WC_VERSION, true);
    }
}

/**
 * Remove cart notice
 */
add_action('wp_loaded', 'growtype_wc_cart_notices');
function growtype_wc_cart_notices()
{
    if (function_exists('wc_cart_notices')) {
        remove_action('woocommerce_before_checkout_form', array (wc_cart_notices(), 'add_cart_notice'));
    }
}

/**
 *
 */
add_filter('wp_loaded', 'growtype_wc_create_account_default_checked');
function growtype_wc_create_account_default_checked()
{
    if (get_theme_mod('woocommerce_checkout_create_account_checked')) {
        add_filter('woocommerce_create_account_default_checked', '__return_true');
    }
}

/**
 * Change checkout button text  "Place Order" to custom text in checkout page
 * @param $button_text
 * @return $string
 */
add_filter('woocommerce_order_button_text', 'growtype_wc_order_button_text');
function growtype_wc_order_button_text($button_text)
{
    $woocommerce_checkout_billing_section_title = !empty(get_theme_mod('woocommerce_checkout_place_order_button_title')) ? get_theme_mod('woocommerce_checkout_place_order_button_title') : __('Place order', 'growtype-wc');
    return $woocommerce_checkout_billing_section_title;
}

/**
 *
 */
add_filter('woocommerce_cart_item_name', 'growtype_wc_cart_item_name');
function growtype_wc_cart_item_name($text)
{
    return __($text);
}

/**
 * Extend checkout fields
 */
add_filter('woocommerce_checkout_fields', 'growtype_wc_checkout_fields_extend');
function growtype_wc_checkout_fields_extend($fields)
{
    $order_notes = get_theme_mod('woocommerce_checkout_order_notes', 'optional');

    switch ($order_notes) {
        case 'required':
            $fields['order']['order_comments']['required'] = true;
            break;
        case 'hidden':
            unset($fields['order']['order_comments']);
    }

    return $fields;
}

/**
 * Locales data update
 */
add_filter('woocommerce_get_script_data', 'growtype_wc_get_script_data', 10, 2);
function growtype_wc_get_script_data($data, $handle)
{
    switch ($handle) :
        case 'wc-address-i18n':
//            $country = WC()->customer->get_shipping_country();
            $locale_data = json_decode($data['locale'], true);
            $locale_data['LT']['state']['required'] = false;
            $data['locale'] = json_encode($locale_data);
            break;
    endswitch;

    return $data;
}

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

/**
 * Shipping fields
 */
add_filter('woocommerce_shipping_fields', 'growtype_wc_shipping_fields');
function growtype_wc_shipping_fields($fields)
{
    return $fields;
}

/**
 * Set default country
 */
add_filter('default_checkout_billing_country', 'growtype_default_checkout_billing_country');
function growtype_default_checkout_billing_country($country)
{
    if (!empty(get_user_meta(get_current_user_id(), 'country', true))) {
        return get_user_meta(get_current_user_id(), 'country', true);
    }

    return $country;
}

/**
 * Set steps checkout style
 */
if (get_theme_mod('woocommerce_checkout_style_select') === 'steps') {
    /**
     * Change order review position
     */
    remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);
    add_action('woocommerce_after_order_notes', 'woocommerce_checkout_payment', 20);

    add_action('growtype_wc_checkout_customer_details_intro', function () { ?>
        <div class="b-breadcrumb">
            <span class="b-breadcrumb-text is-active" data-type="information">Information</span>
            <span class="b-breadcrumb-separator dashicons dashicons-arrow-right-alt2"></span>
            <span class="b-breadcrumb-text" data-type="payment">Payment</span>
            <span class="b-breadcrumb-separator dashicons dashicons-arrow-right-alt2"></span>
            <span class="b-breadcrumb-text" data-type="receipt">Receipt</span>
        </div>
        <?php
    });

    add_action('growtype_wc_checkout_customer_details_before_close', function () { ?>
        <div class="b-actions">
            <a href="#" class="btn btn-secondary btn-next"><?php echo __('Continue', 'growtype-wc') ?></a>
        </div>
        <?php
    }, 100);

    add_action('woocommerce_review_order_before_payment', function () { ?>
        <h3><?php echo __('Select payment method', 'growtype-wc') ?></h3>
        <?php
    }, 1);

    add_action('woocommerce_before_checkout_billing_form', function () { ?>
        <span class="btn-edit"><?php echo __('Edit', 'growtype-wc') ?></span>
        <?php
    }, 1);
}

/**
 * checkout input labels style floating
 */
if (get_theme_mod('woocommerce_checkout_input_label_style') === 'floating') {
    add_filter('woocommerce_default_address_fields', 'override_default_address_checkout_fields', 20, 1);
    function override_default_address_checkout_fields($address_fields)
    {
        $address_fields['first_name']['placeholder'] = '';
        $address_fields['last_name']['placeholder'] = '';
        $address_fields['country']['placeholder'] = '';
        $address_fields['address_1']['placeholder'] = '';
        $address_fields['city']['placeholder'] = '';
        $address_fields['state']['placeholder'] = '';
        $address_fields['postcode']['placeholder'] = '';
        return $address_fields;
    }

    add_filter('woocommerce_checkout_fields', 'override_billing_checkout_fields', 20, 1);
    function override_billing_checkout_fields($fields)
    {
        $fields['billing']['billing_state']['placeholder'] = '';
        return $fields;
    }
}

add_filter('woocommerce_terms_is_checked_default', 'growtype_wc_woocommerce_terms_is_checked_by_default');
function growtype_wc_woocommerce_terms_is_checked_by_default()
{
    if (get_theme_mod('woocommerce_checkout_terms_is_checked_by_default')) {
        return true;
    }

    return false;
}

/**
 * Add custom field summary
 */
add_action('woocommerce_review_order_after_cart_contents', 'growtype_wc_woocommerce_review_order_after_cart_contents');
function growtype_wc_woocommerce_review_order_after_cart_contents()
{
    $discount_total = 0;
    $regular_price_total = 0;
    foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
        $product = $values['data'];

        if ($product->is_on_sale()) {
            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_sale_price();
            $discount = ((float)$regular_price - (float)$sale_price) * (int)$values['quantity'];
            $discount_total += $discount;
            $regular_price_total += $regular_price;
        }

        $featured_image = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()));

        echo '<tr class="cart_item cart_item_detailed" data-type="product"><th>' . (isset($featured_image[0]) && !empty($featured_image[0]) ? '<div class="b-img"><img src="' . $featured_image[0] . '" class="img-fluid"></div>' : '') . '<div class="b-details"><div class="e-title">' . $product->get_title() . '</div><div class="e-description">' . $product->get_short_description() . '</div></div></th><td><div class="e-price-old">' . wc_price($product->get_regular_price()) . '</div><div class="e-price">' . wc_price($product->get_price()) . '</div></td></tr>';

        $cross_sells = $product->get_cross_sell_ids();
        if (!empty($cross_sells)) {
            foreach ($cross_sells as $cross_sell) {
                $product = wc_get_product($cross_sell);

                $featured_image = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()));

                echo '<tr class="cart_item cart_item_detailed" data-type="cross_sell"><th>' . (isset($featured_image[0]) && !empty($featured_image[0]) ? '<div class="b-img"><img src="' . $featured_image[0] . '" class="img-fluid"></div>' : '') . '<div class="b-details"><div class="e-title">' . $product->get_title() . '</div><div class="e-description">' . $product->get_short_description() . '</div></div></th><td><div class="e-price-old">' . wc_price($product->get_regular_price()) . '</div><div class="e-price">' . wc_price($product->get_price()) . '</div></td></tr>';
            }
        }
    }

    /**
     * Discount
     */
    if ($discount_total > 0) {
        $discount_percentage = round(($discount_total * 100) / $regular_price_total, 0);
        echo '<tr class="cart-discount"><th><span class="e-label">' . __('Discount:', 'growtype-wc') . '</span> <span class="e-amount">-' . $discount_percentage . '%</span></th><td data-title="You Saved">-' . wc_price($discount_total + WC()->cart->get_discount_total()) . '</td></tr>';
    }
}

/**
 * Add custom field summary
 */
add_action('wp_enqueue_scripts', 'growtype_wc_checkout_enqueue_scripts');
function growtype_wc_checkout_enqueue_scripts()
{
    $css = '';
    if (!get_theme_mod('woocommerce_checkout_order_review_table_show_subtotal', true)) {
        $css .= '
        .woocommerce-checkout-review-order-table .cart-subtotal {
            display:none!important;
        }';
    }

    if (!get_theme_mod('woocommerce_checkout_order_review_table_show_head', true)) {
        $css .= '
        .woocommerce-checkout-review-order-table thead {
            display:none;
        }
        .woocommerce-checkout-review-order-table .cart_item_detailed th {
           border-top:none!important;
        }
        .woocommerce-checkout-review-order-table .cart_item_detailed td {
           border-top:none!important;
        }
        ';
    }

    if (get_theme_mod('woocommerce_checkout_order_review_cart_item_style') === 'detailed') {
        $css .= '
        .woocommerce-checkout-review-order-table .cart_item:not(.cart_item_detailed) {
            display:none!important;
        }
        .woocommerce-checkout-review-order-table .cart_item_detailed {
                display: table-row;
        }';
    } else {
        $css .= '
        .woocommerce-checkout-review-order-table .cart_item_detailed {
            display:none;
        }';
    }

    if (!empty($css)) {
        wp_register_style('dma-inline-style', false);
        wp_enqueue_style('dma-inline-style');
        wp_add_inline_style('dma-inline-style', $css);
    }
}
