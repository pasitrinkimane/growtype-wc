<?php
defined('ABSPATH') || exit;

do_action('woocommerce_before_account_subscriptions');

if (!empty($subscription)) { ?>
    <?php
    $subscription_order_id = (int) get_post_meta($subscription->ID, '_order_id', true);
    $subscription_order = $subscription_order_id > 0 ? wc_get_order($subscription_order_id) : false;
    $paypal_subscription_id = $subscription_order instanceof WC_Order
        && $subscription_order->get_payment_method() === Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID
        ? trim((string) $subscription_order->get_meta('paypal_subscription_id'))
        : '';
    $is_terminal_paypal_subscription = $paypal_subscription_id !== ''
        && $subscription->sub_status === Growtype_Wc_Subscription::STATUS_CANCELLED;
    ?>
    <div class="card board-box subs">
        <div class="row subs-single">
            <?php include __DIR__ . '/partials/subscription-single-details.php' ?>
            <div class="subs-single-actions col-md-4 mt-4 mt-md-0">
                <div class="b-actions d-flex justify-content-end">
                    <?php if (!empty($stripe_billing_portal_url)) { ?>
                        <a href="<?php echo esc_url($stripe_billing_portal_url) ?>" class="btn btn-secondary" data-growtype-quiz-modal-open="subscription_cancellation"><?php echo __('Cancel', 'growtype-wc') ?></a>
                    <?php } elseif (!$is_terminal_paypal_subscription) { ?>
                        <form class="col-12 col-md-6 d-flex justify-content-end" action="<?php get_permalink() ?>" method="post">
                            <?php wp_nonce_field('growtype_wc_change_subscription_status_' . (int) $subscription->ID, '_growtype_wc_subscription_status_nonce'); ?>
                            <input type="hidden" name="subscription_id" value="<?php echo $subscription->ID ?>">
                            <input type="hidden" name="change_subscription_status" value="<?php echo $subscription->sub_status !== Growtype_Wc_Subscription::STATUS_ACTIVE ? Growtype_Wc_Subscription::STATUS_ACTIVE : Growtype_Wc_Subscription::STATUS_CANCELLED ?>">
                            <button type="submit" class="btn <?php echo $subscription->sub_status !== Growtype_Wc_Subscription::STATUS_ACTIVE ? 'btn-primary' : 'btn-secondary' ?>"<?php echo $subscription->sub_status === Growtype_Wc_Subscription::STATUS_ACTIVE ? ' data-growtype-quiz-modal-open="subscription_cancellation"' : '' ?>><?php echo $subscription->sub_status !== Growtype_Wc_Subscription::STATUS_ACTIVE ? __('Resume', 'growtype-wc') : __('Cancel', 'growtype-wc') ?></button>
                        </form>
                    <?php } else { ?>
                        <span class="text-muted"><?php echo esc_html__('Cancelled', 'growtype-wc'); ?></span>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
<?php } ?>
