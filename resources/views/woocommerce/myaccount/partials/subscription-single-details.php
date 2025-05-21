<div class="subs-single-details col-md-8">
    <p class="e-title"><?php echo __('Details', 'growtype-wc') ?></p>
    <p><?php echo sprintf('%s: <span class="e-status" data-status="%s">%s</span>', __('Status', 'growtype-wc'), $subscription->sub_status, $subscription->sub_status === 'active' ? __('Active', 'growtype-wc') : __('Inactive', 'growtype-wc')) ?></p>
    <p><?php echo sprintf('%s: %s', __('Title', 'growtype-wc'), get_the_title($subscription->ID)) ?></p>
    <p><?php echo sprintf('%s: %s', __('Price', 'growtype-wc'), $subscription->sub_price) ?></p>
    <p><?php echo sprintf('%s: %s', __('Initial charge', 'growtype-wc'), $subscription->sub_payment_date) ?></p>
    <?php if ($subscription->sub_status === Growtype_Wc_Subscription::STATUS_ACTIVE) { ?>
        <p><?php echo sprintf('%s: %s', __('Next charge', 'growtype-wc'), $subscription->sub_next_charge) ?></p>
    <?php } ?>
</div>
