<?php
defined('ABSPATH') || exit;

do_action('woocommerce_before_account_subscriptions');

if (!empty($subscriptions)) { ?>
    <div class="board-box subs">
        <?php foreach ($subscriptions as $subscription) { ?>
            <div class="row subs-single">
                <?php include __DIR__ . '/partials/subscription-single-details.php' ?>
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
