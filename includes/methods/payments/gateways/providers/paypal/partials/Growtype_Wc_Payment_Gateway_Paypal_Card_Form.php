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
     */
    public static function render(array $args = []): void
    {
        $submit_label = isset($args['submit_label']) && !empty($args['submit_label']) ? $args['submit_label'] : self::get_default_submit_label();
        $show_errors  = $args['show_errors']  ?? true;
        ?>
        <div id="card-form" class="card_container">

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
