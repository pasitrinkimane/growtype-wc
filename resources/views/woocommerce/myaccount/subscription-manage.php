<?php
defined('ABSPATH') || exit;

do_action('woocommerce_before_account_subscriptions');

if (!empty($subscription)) { ?>
    <div class="board-box subs">
        <div class="row subs-single">
            <div class="subs-single-details col-md-8">
                <p class="e-title"><?php echo __('Details', 'growtype-wc') ?></p>
                <p><?php echo sprintf('%s: %s', __('Title', 'growtype-wc'), get_the_title($subscription->ID)) ?></p>
                <p><?php echo sprintf('%s: %s', __('Price', 'growtype-wc'), $subscription->sub_price) ?></p>
                <p><?php echo sprintf('%s: <span class="e-status" data-status="%s">%s</span>', __('Status', 'growtype-wc'), $subscription->sub_status, strtoupper($subscription->sub_status)) ?></p>
                <?php if ($subscription->sub_status === Growtype_Wc_Subscription::STATUS_ACTIVE) { ?>
                    <p><?php echo sprintf('%s: %s', __('Next charge', 'growtype-wc'), $subscription->sub_next_charge) ?></p>
                <?php } ?>
            </div>
            <div class="subs-single-actions col-md-4 mt-4 mt-md-0">
                <div class="b-actions">
                    <form class="col-12 col-md-6" action="<?php get_permalink() ?>" method="post">
                        <input type="hidden" name="subscription_id" value="<?php echo $subscription->ID ?>">
                        <input type="hidden" name="change_subscription_status" value="<?php echo $subscription->sub_status !== Growtype_Wc_Subscription::STATUS_ACTIVE ? Growtype_Wc_Subscription::STATUS_ACTIVE : Growtype_Wc_Subscription::STATUS_CANCELLED ?>">
                        <button type="submit" class="btn <?php echo $subscription->sub_status !== Growtype_Wc_Subscription::STATUS_ACTIVE ? 'btn-primary' : 'btn-secondary' ?>"><?php echo $subscription->sub_status !== Growtype_Wc_Subscription::STATUS_ACTIVE ? __('Resume', 'growtype-wc') : __('Cancel', 'growtype-wc') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php } ?>
