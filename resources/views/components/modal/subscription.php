<div class="modal fade" id="growtypeWcSubscriptionModal" tabindex="-1" aria-labelledby="growtypeWcSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="growtypeWcSubscriptionModalLabel"><?php echo __('Upgrade to Unlock', 'growtype-wc') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4 class="mb-4"><?php echo __('Elevate your chat experience with our subscription!', 'growtype-wc') ?></h4>
                <b><?php echo __('Enjoy benefits like:', 'growtype-wc') ?></b>
                <?php echo Growtype_Wc_Subscription_Benefits_Shortcode::benefits() ?>
            </div>
            <div class="modal-footer">
                <a href="<?php echo get_permalink(get_page_by_path('plans')) ?>" class="btn btn-primary"><?php echo __('Upgrade to Premium', 'growtype-wc') ?></a>
            </div>
        </div>
    </div>
</div>
