<?php

/**
 * Fields
 */
include_once 'fields/billing.php';
include_once 'fields/default.php';
include_once 'fields/shipping.php';

/**
 * Scripts
 */
include_once 'scripts/index.php';

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
    foreach (WC()->cart->get_cart() as $values) {
        $product = $values['data'];
        echo growtype_wc_render_order_summary_row($product->get_id());

        $cross_sells = $product->get_cross_sell_ids();
        if (!empty($cross_sells)) {
            foreach ($cross_sells as $cross_sell) {
                echo growtype_wc_render_order_summary_row($cross_sell);
            }
        }
    }

    /**
     * Discount
     */
    $cart_totals = growtype_wc_get_cart_totals();
    if ($cart_totals['discount'] > 0) {
        echo '<tr class="cart-discount"><th><span class="e-label">' . __('Discount:', 'growtype-wc') . '</span> <span class="e-amount">-' . $cart_totals['discount_percentage'] . '%</span></th><td data-title="You Saved">-' . wc_price($cart_totals['discount'] + WC()->cart->get_discount_total()) . '</td></tr>';
    }
}

function growtype_wc_render_order_summary_row($product_id)
{
    $product = wc_get_product($product_id);
    $featured_image = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()));
    ?>
    <tr class="cart_item cart_item_detailed" data-type="cross_sell">
        <th>
            <?php if (isset($featured_image[0]) && !empty($featured_image[0])) { ?>
                <div class="b-img">
                    <img src="<?php echo $featured_image[0] ?>" class="img-fluid">
                </div>
            <?php } ?>
            <div class="b-details">
                <div class="e-title"><?php echo $product->get_title() ?></div>
                <div class="e-description"><?php echo $product->get_short_description() ?></div>
            </div>
        </th>
        <td>
            <?php if (wc_price($product->get_regular_price()) !== wc_price($product->get_price())) { ?>
                <div class="e-price-old"><?php echo wc_price($product->get_regular_price()) ?></div>
            <?php } ?>
            <div class="e-price"><?php echo wc_price($product->get_price()) ?></div>
        </td>
    </tr>
    <?php
}

add_action('wp_loaded', function () {
    /**
     * Payment methods position
     */
    if (growtype_wc_checkout_payment_methods_position() === 'after_shipping_details') {
        /**
         * Change order review position
         */
        remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);
        add_action('woocommerce_after_order_notes', 'woocommerce_checkout_payment', 20);

        add_action('woocommerce_review_order_before_payment', function () { ?>
            <h3><?php echo __('Select payment method', 'growtype-wc') ?></h3>
            <?php
        }, 1);
    }

    /**
     * Breadcrumbs
     */
    if (growtype_wc_checkout_breadcrumbs_active()) {
        add_action('growtype_wc_checkout_customer_details_intro', function () {
            if (growtype_wc_get_checkout_style() === 'steps') { ?>
                <div class="b-breadcrumb">
                    <span class="b-breadcrumb-text is-active" data-type="information"><?php echo __('Information', 'growtype-wc') ?></span>
                    <span class="b-breadcrumb-separator dashicons dashicons-arrow-right-alt2"></span>
                    <span class="b-breadcrumb-text" data-type="payment"><?php echo __('Payment', 'growtype-wc') ?></span>
                    <span class="b-breadcrumb-separator dashicons dashicons-arrow-right-alt2"></span>
                    <span class="b-breadcrumb-text" data-type="receipt"><?php echo __('Receipt', 'growtype-wc') ?></span>
                </div>
                <?php
            } else { ?>
                <div class="b-breadcrumb">
                    <span class="b-breadcrumb-text is-active" data-type="information"><?php echo __('Shipping Information & Payment', 'growtype-wc') ?></span>
                    <span class="b-breadcrumb-separator dashicons dashicons-arrow-right-alt2"></span>
                    <span class="b-breadcrumb-text" data-type="receipt"><?php echo __('Receipt', 'growtype-wc') ?></span>
                </div>
            <?php }
        });
    }

    /**
     * Set steps checkout style
     */
    if (growtype_wc_get_checkout_style() === 'steps') {
        add_action('growtype_wc_checkout_customer_details_before_close', function () { ?>
            <div class="b-actions">
                <a href="#" class="btn btn-secondary btn-next"><?php echo __('Continue', 'growtype-wc') ?></a>
            </div>
            <?php
        }, 100);

        add_action('woocommerce_before_checkout_billing_form', function () { ?>
            <span class="btn-edit"><?php echo __('Edit', 'growtype-wc') ?></span>
            <?php
        }, 1);
    }

    /**
     * Add extra html to cart totals
     */
    add_filter('woocommerce_cart_totals_order_total_html', function ($value) {
        $cart_totals = growtype_wc_get_cart_totals();

        if (isset($cart_totals['regular_price']) && !empty($cart_totals['regular_price'])) {
            $value .= '<span class="e-regular-old">' . wc_price($cart_totals['regular_price']) . '</span>';
        }

        return $value;
    });
});
