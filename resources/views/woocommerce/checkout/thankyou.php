<?php
/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.7.0
 */

defined('ABSPATH') || exit;
?>

<div class="woocommerce-order <?php echo get_theme_mod('woocommerce_thankyou_page_style') === 'centered' ? 'thankyou-centered' : '' ?> order-details-<?php echo get_theme_mod('woocommerce_thankyou_page_order_details', true) ? 'enabled' : 'disabled' ?> customer-details-<?php echo get_theme_mod('woocommerce_thankyou_page_customer_details', true) ? 'enabled' : 'disabled' ?> download-details-<?php echo get_theme_mod('woocommerce_thankyou_page_download_details', true) ? 'enabled' : 'disabled' ?>">
    <?php if ($order) { ?>
        <?php do_action('woocommerce_before_thankyou', $order->get_id()); ?>

        <?php if ($order->has_status('failed')) { ?>

            <?php echo growtype_wc_include_view('woocommerce.checkout.thankyou-failed', [
                'order' => $order
            ]) ?>

        <?php } else { ?>

            <?php echo growtype_wc_include_view('woocommerce.checkout.thankyou-success', [
                'order' => $order
            ]) ?>

        <?php } ?>

        <?php do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id()); ?>
        <?php do_action('woocommerce_thankyou', $order->get_id()); ?>
    <?php } else { ?>
        <?php echo growtype_wc_include_view('woocommerce.checkout.thankyou-no-order', [
            'order' => $order
        ]) ?>
    <?php } ?>
</div>
