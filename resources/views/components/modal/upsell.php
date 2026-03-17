<?php
$modal_id = $modal_id ?? Growtype_Wc_Upsell::MODAL_ID;
$ajax_action = $ajax_action ?? Growtype_Wc_Upsell::DISMISS_AJAX_ACTION;
$ajax_get_item_action = $ajax_get_item_action ?? Growtype_Wc_Upsell::GET_ITEM_AJAX_ACTION;
$ajax_nonce = $ajax_nonce ?? '';
$upsell_ids = $upsell_ids ?? [];
$auto_show = isset($auto_show) ? (bool)$auto_show : true;
?>
<?php if (!empty($upsell_ids)): ?>
<div class="modal fade gwc-upsell-modal" id="<?php echo esc_attr($modal_id); ?>" tabindex="-1"
    aria-labelledby="<?php echo esc_attr($modal_id); ?>Label" aria-hidden="true" data-gwc-upsell-modal="true"
    data-auto-show="<?php echo $auto_show ? 'true' : 'false'; ?>"
    data-auto-show-delay="<?php echo isset($auto_show_delay) ? (int)$auto_show_delay : 0; ?>"
    data-ajax-action="<?php echo esc_attr($ajax_action); ?>"
    data-ajax-get-item-action="<?php echo esc_attr($ajax_get_item_action); ?>"
    data-ajax-nonce="<?php echo esc_attr($ajax_nonce); ?>"
    data-upsell-ids="<?php echo esc_attr(wp_json_encode($upsell_ids)); ?>">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <!-- Loading overlay -->
            <div class="gwc-upsell-loader">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">
                        <?php esc_html_e('Loading...', 'growtype-wc'); ?>
                    </span>
                </div>
            </div>
            <!-- Dynamic background -->
            <div class="gwc-upsell-featured"></div>

            <div class="modal-body text-center">
                <!-- Close button -->
                <button type="button" class="btn-close btn-close-white gwc-upsell-close" aria-label="Close"
                    data-bs-dismiss="modal"></button>

                <!-- Badges Row -->
                <div class="gwc-upsell-badges">
                    <div class="gwc-upsell-promo"></div>
                    <div class="gwc-upsell-discount"></div>
                </div>

                <!-- Title & Hook -->
                <div class="gwc-upsell-short-description"></div>

                <!-- Offer Description Box -->
                <div class="gwc-upsell-offer-box">
                    <div class="gwc-upsell-description"></div>
                </div>

                <!-- Price Section -->
                <div class="gwc-upsell-price">
                    <div class="gwc-upsell-price-html"></div>
                    <div class="gwc-upsell-extra-details"></div>
                </div>

                <!-- Stripe / Apple Pay / Google Pay Element -->
                <div class="gwc-upsell-payment"></div>

                <!-- Secondary Actions -->
                <div class="d-flex justify-content-center">
                    <button type="button" class="btn gwc-upsell-skip" data-bs-dismiss="modal" <?php echo count($upsell_ids) === 1 ? 'style="display: none;"' : ''; ?>>
                        <?php esc_html_e('Skip This Offer', 'growtype-wc'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
endif; ?>
