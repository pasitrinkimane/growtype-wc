<?php

/**
 * Standalone PayPal Hosted Fields card form renderer.
 *
 * Usage — anywhere in PHP templates or partials:
 *   Growtype_Wc_Payment_Gateway_Paypal_Card_Form::render();
 *   Growtype_Wc_Payment_Gateway_Paypal_Card_Form::render_loader();
 *
 * The class is context-agnostic: it only outputs the form markup.
 * Wrapping it in a modal, a sidebar, an inline section, etc. is the
 * responsibility of the caller.
 */
class Growtype_Wc_Payment_Gateway_Paypal_Card_Form
{
    /**
     * Render the card form markup.
     *
     * @param array $args Optional overrides:
     *   - 'submit_label' (string)  Button label.
     *   - 'show_errors'  (bool)    Whether to include the error container (default true).
     *   - 'show_dev_helper' (bool) Whether to show the test-card helper (default false).
     */
    public static function render(array $args = []): void
    {
        $submit_label = isset($args['submit_label']) && !empty($args['submit_label']) ? $args['submit_label'] : self::get_default_submit_label();
        $show_errors  = $args['show_errors']  ?? true;
        $show_dev_helper = (bool) ($args['show_dev_helper'] ?? false);
        ?>
        <div id="card-form" class="card_container">
            <?php if ($show_dev_helper) : ?>
            <div
                class="gwc-paypal-dev-helper"
                data-test-cardholder-name="Test Buyer"
                data-test-card-expiry="01/30"
                data-test-card-cvv="1234"
                style="margin-bottom:12px;padding:10px 12px;background:#fff4d6;border:1px solid #f1d48b;border-radius:8px;color:#6a5200;font-size:13px;"
            >
                <strong><?php _e('Development helper:', 'growtype-child'); ?></strong>
                <?php _e('PayPal test values', 'growtype-child'); ?>:
                <div style="margin-top:8px;display:grid;gap:8px;">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                        <strong style="min-width:120px;"><?php _e('Cardholder name', 'growtype-child'); ?></strong>
                        <span class="gwc-paypal-dev-helper__name">Test Buyer</span>
                        <button type="button" class="gwc-paypal-dev-copy-value" data-copy-value="Test Buyer" data-field-label="Cardholder name" style="padding:4px 8px;border:1px solid #d6b862;border-radius:6px;background:#fff;color:#6a5200;font-size:12px;cursor:pointer;">
                            <?php _e('Copy', 'growtype-child'); ?>
                        </button>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                        <strong style="min-width:120px;"><?php _e('Card Number', 'growtype-child'); ?></strong>
                        <select class="gwc-paypal-dev-card-select" style="min-width:260px;padding:6px 8px;border:1px solid #d6b862;border-radius:6px;background:#fff;color:#6a5200;font-size:12px;">
                            <option value="371449635398431" data-brand="American Express" selected>371449635398431 - American Express</option>
                            <option value="376680816376961" data-brand="American Express">376680816376961 - American Express</option>
                            <option value="36461510000039" data-brand="Diners Club">36461510000039 - Diners Club</option>
                            <option value="36461510000013" data-brand="Diners Club">36461510000013 - Diners Club</option>
                            <option value="6304000000000000" data-brand="Maestro">6304000000000000 - Maestro</option>
                            <option value="5063516945005047" data-brand="Maestro">5063516945005047 - Maestro</option>
                            <option value="2223000048400011" data-brand="Mastercard">2223000048400011 - Mastercard</option>
                            <option value="4005519200000004" data-brand="Visa">4005519200000004 - Visa</option>
                            <option value="4012000033330026" data-brand="Visa">4012000033330026 - Visa</option>
                            <option value="4012000077777777" data-brand="Visa">4012000077777777 - Visa</option>
                            <option value="4012888888881881" data-brand="Visa">4012888888881881 - Visa</option>
                            <option value="4217651111111119" data-brand="Visa">4217651111111119 - Visa</option>
                            <option value="4500600000000061" data-brand="Visa">4500600000000061 - Visa</option>
                            <option value="4772129056533503" data-brand="Visa">4772129056533503 - Visa</option>
                            <option value="4915805038587737" data-brand="Visa">4915805038587737 - Visa</option>
                            <option value="6200680000000004" data-brand="CUP">6200680000000004 - CUP</option>
                            <option value="6200680000000038" data-brand="CUP">6200680000000038 - CUP</option>
                            <option value="3636500000000260" data-brand="JCB">3636500000000260 - JCB</option>
                            <option value="3636500000000989" data-brand="JCB">3636500000000989 - JCB</option>
                        </select>
                        <button type="button" class="gwc-paypal-dev-copy-value gwc-paypal-dev-copy-card-number" data-copy-value="371449635398431" data-field-label="Card number" style="padding:4px 8px;border:1px solid #d6b862;border-radius:6px;background:#fff;color:#6a5200;font-size:12px;cursor:pointer;">
                            <?php _e('Copy', 'growtype-child'); ?>
                        </button>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                        <strong style="min-width:120px;"><?php _e('Expiry (MM/YY)', 'growtype-child'); ?></strong>
                        <span class="gwc-paypal-dev-helper__expiry">01/30</span>
                        <button type="button" class="gwc-paypal-dev-copy-value" data-copy-value="01/30" data-field-label="Expiry" style="padding:4px 8px;border:1px solid #d6b862;border-radius:6px;background:#fff;color:#6a5200;font-size:12px;cursor:pointer;">
                            <?php _e('Copy', 'growtype-child'); ?>
                        </button>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                        <strong style="min-width:120px;"><?php _e('CVV', 'growtype-child'); ?></strong>
                        <span class="gwc-paypal-dev-helper__cvv">1234</span>
                        <button type="button" class="gwc-paypal-dev-copy-value" data-copy-value="1234" data-field-label="CVV" style="padding:4px 8px;border:1px solid #d6b862;border-radius:6px;background:#fff;color:#6a5200;font-size:12px;cursor:pointer;">
                            <?php _e('Copy', 'growtype-child'); ?>
                        </button>
                    </div>
                </div>
                <span class="gwc-paypal-dev-copy-status" style="margin-top:8px;display:none;"></span>
            </div>
            <?php endif; ?>

            <div class="gwc-hf-group">
                <label class="gwc-hf-label">
                    <?php _e('Cardholder name', 'growtype-child'); ?>
                    <span style="color:#e05c5c">*</span>
                </label>
                <div id="card-name-field-container" class="gwc-hf-frame"></div>
            </div>

            <div class="gwc-hf-group">
                <label class="gwc-hf-label">
                    <?php _e('Card Number', 'growtype-child'); ?>
                    <span style="color:#e05c5c">*</span>
                </label>
                <div id="card-number-field-container" class="gwc-hf-frame"></div>
            </div>

            <div class="gwc-hf-row">
                <div class="gwc-hf-group">
                    <label class="gwc-hf-label">
                        <?php _e('Expiry (MM/YY)', 'growtype-child'); ?>
                        <span style="color:#e05c5c">*</span>
                    </label>
                    <div id="card-expiry-field-container" class="gwc-hf-frame"></div>
                </div>
                <div class="gwc-hf-group">
                    <label class="gwc-hf-label">
                        <?php _e('CVV', 'growtype-child'); ?>
                        <span style="color:#e05c5c">*</span>
                    </label>
                    <div id="card-cvv-field-container" class="gwc-hf-frame"></div>
                </div>
            </div>

            <?php if ($show_errors) : ?>
            <div id="gwc-hf-errors" style="display:none;margin-bottom:5px;padding:10px 14px;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.3);border-radius:6px;color:#e05c5c;font-size:13px"></div>
            <?php endif; ?>

            <button id="card-field-submit-button" class="gwc-hf-submit">
                <span class="gwc-hf-submit-lock" style="position:relative;top:-2px;margin-right:6px;">
                    <svg class="gwc-hf-lock-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </span>
                <span class="gwc-hf-submit-text"><?php echo esc_html($submit_label); ?></span>
            </button>
        </div>
        <?php
    }

    /**
     * Get the default submit button label.
     *
     * @return string
     */
    public static function get_default_submit_label(): string
    {
        return __('Complete Secure Payment', 'growtype-child');
    }
}
