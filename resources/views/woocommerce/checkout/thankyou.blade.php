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

<div class="woocommerce-order <?php echo get_theme_mod('woocommerce_thankyou_page_style') === 'centered' ? 'thankyou-centered' : '' ?> order-details-<?php echo !get_theme_mod('woocommerce_thankyou_page_order_details_disabled') ? 'enabled' : 'disabled' ?> customer-details-<?php echo !get_theme_mod('woocommerce_thankyou_page_customer_details_disabled') ? 'enabled' : 'disabled' ?> download-details-<?php echo !get_theme_mod('woocommerce_thankyou_page_download_details_disabled') ? 'enabled' : 'disabled' ?>>">

    <?php
    if ($order) :

        do_action('woocommerce_before_thankyou', $order->get_id());
        ?>

        <?php if ($order->has_status('failed')) : ?>

    <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.',
            'growtype-wc'); ?></p>

    <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
        <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="button pay"><?php esc_html_e('Pay',
                'growtype-wc'); ?></a>
            <?php if (is_user_logged_in()) : ?>
        <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="button pay"><?php esc_html_e('My account',
                'growtype-wc'); ?></a>
        <?php endif; ?>
    </p>

    <?php else : ?>

    <div class="b-intro-content">
            <?php echo apply_filters('the_content', growtype_wc_thankyou_page_intro_content($order)); ?>
    </div>

        <?php if (!growtype_wc_order_overview_disabled()) { ?>
    <ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

        <li class="woocommerce-order-overview__order order">
                <?php esc_html_e('Order number:', 'growtype-wc'); ?>
            <strong><?php echo $order->get_order_number(); ?></strong>
        </li>

        <li class="woocommerce-order-overview__date date">
                <?php esc_html_e('Date:', 'growtype-wc'); ?>
            <strong><?php echo wc_format_datetime($order->get_date_created()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
        </li>

            <?php if (is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email()) : ?>
        <li class="woocommerce-order-overview__email email">
                <?php esc_html_e('Email:', 'growtype-wc'); ?>
            <strong><?php echo $order->get_billing_email(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
        </li>
        <?php endif; ?>

        <li class="woocommerce-order-overview__total total">
                <?php esc_html_e('Total:', 'growtype-wc'); ?>
            <strong><?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
        </li>

            <?php if ($order->get_payment_method_title()) : ?>
        <li class="woocommerce-order-overview__payment-method method">
                <?php esc_html_e('Payment method:', 'growtype-wc'); ?>
            <strong><?php echo wp_kses_post($order->get_payment_method_title()); ?></strong>
        </li>
        <?php endif; ?>

    </ul>
    <?php } ?>

    <?php endif; ?>

        <?php do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id()); ?>
        <?php do_action('woocommerce_thankyou', $order->get_id()); ?>

    <?php else : ?>
    <div class="b-intro-content">
            <?php echo apply_filters('the_content', growtype_wc_thankyou_page_intro_content($order)); ?>
    </div>
    <?php endif; ?>

</div>
