<div class="woocommerce-order-details-intro">
    <?php echo apply_filters('the_content', growtype_wc_thankyou_page_intro_content($order->get_id())); ?>
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
