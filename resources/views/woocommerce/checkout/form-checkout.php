<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_before_checkout_form', $checkout);

if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) {
    echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message',
        __('You must be logged in to checkout.', 'growtype-wc')));
    return;
}

?>

<?php if (!empty(get_theme_mod('woocommerce_checkout_intro_text'))) { ?>
    <div class="woocommerce-checkout-intro pb-3 pt-2">
        <?php echo get_theme_mod('woocommerce_checkout_intro_text') ?>
    </div>
<?php } ?>

<form name="checkout" method="post" class="checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">

    <?php if ($checkout->get_checkout_fields()) : ?>

        <?php do_action('woocommerce_checkout_before_customer_details'); ?>

        <div class="col2-set" id="customer_details"
             data-type="customer_details"
             data-billing="<?php echo get_theme_mod('woocommerce_checkout_billing_fields', true) ? 'true' : 'false' ?>"
             data-shipping="<?php echo !wc_ship_to_billing_address_only() ? 'true' : 'false' ?>"
        >
            <?php do_action('growtype_wc_checkout_customer_details_intro'); ?>

            <div class="col-1">
                <?php do_action('woocommerce_checkout_billing'); ?>
            </div>
            <div class="col-2">
                <?php do_action('woocommerce_checkout_shipping'); ?>
            </div>

            <?php do_action('growtype_wc_checkout_customer_details_before_close'); ?>
        </div>

        <?php do_action('woocommerce_checkout_after_customer_details'); ?>

    <?php endif; ?>

    <div class="col2-set"
         data-type="order_summary"
    >
        <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>

        <?php if (get_theme_mod('woocommerce_checkout_order_review_heading', true)) { ?>
            <h3 id="order_review_heading" class="is-open"><?php esc_html_e('Order summary', 'growtype-wc'); ?></h3>
        <?php } ?>

        <?php do_action('woocommerce_checkout_before_order_review'); ?>

        <div id="order_review" class="woocommerce-checkout-review-order is-accordion">
            <?php do_action('woocommerce_checkout_order_review'); ?>
        </div>

        <?php do_action('woocommerce_checkout_after_order_review'); ?>
    </div>

</form>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>
