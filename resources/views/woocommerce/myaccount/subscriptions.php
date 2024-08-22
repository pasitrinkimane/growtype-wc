<?php
defined('ABSPATH') || exit;

do_action('woocommerce_before_account_subscriptions');

if (!empty($subscriptions)) { ?>
    <div class="board-box subs">
        <?php foreach ($subscriptions as $subscription) { ?>
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
                        <form class="col-12 col-md-6" action="<?php get_permalink() ?>" method="post" style="display: none;">
                            <input type="hidden" name="change_subscription" value="true">
                            <button type="submit" class="btn btn-primary"><?php echo __('Change', 'growtype-wc') ?></button>
                        </form>

                        <a href="<?php echo Growtype_Wc_Subscription::manage_url($subscription->ID) ?>" class="btn btn-primary">
                            <?php echo __('Manage Subscription', 'growtype-wc') ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
<?php } else {
    echo has_action('growtype_wc_woocommerce_account_no_subscriptions_found') ? do_action('growtype_wc_woocommerce_account_no_subscriptions_found') : growtype_wc_include_view('partials.content.404', ['subtitle' => __('You have no subscriptions.', 'growtype-wc')]);
}
