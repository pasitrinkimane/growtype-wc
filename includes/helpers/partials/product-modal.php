<?php

if (!function_exists('growtype_wc_product_modal')) {
    /**
     * Render a flexible product payment modal.
     *
     * Minimum usage — everything auto-filled from the WC product:
     *   growtype_wc_product_modal(131);
     *
     * All fields are overridable:
     *   growtype_wc_product_modal(131, [
     *       'accent_color'  => '#fcb901',
     *       'badge_label'   => 'Limited Offer',
     *       'dismiss_text'  => 'Maybe later',
     *       'before_button' => '<p>Custom HTML before button</p>',
     *   ]);
     *
     * Body-only mode (embed inside an existing modal wrapper):
     *   growtype_wc_product_modal(0, ['standalone' => false]);
     *
     * @param array $args {
     *   -- Modal wrapper (standalone=true only) --
     *   @type bool   $standalone           Render full <div class="modal"> wrapper. Default true.
     *   @type string $modal_id             HTML id. Auto-derived from product slug when omitted.
     *   @type string $modal_class          Extra CSS classes on the modal div.
     *   @type array  $modal_data           key=>value data-* attributes.
     *   @type string $max_width            Dialog max-width. Default '550px'.
     *
     *   -- Styling --
     *   @type string $bg_color             Background colour. Default '#0a0a0b'.
     *   @type string $accent_color         Border / badge / price colour. Default '#00f2ff'.
     *   @type string $accent_color_rgb     RGB triplet for rgba() use. Default '0, 242, 255'.
     *   @type string $accent_text_color    Text on accent surfaces. Default '#fff'.
     *   @type string $border_width         Default '1px'.
     *   @type string $border_radius        Default '20px'.
     *   @type string $box_shadow           Auto-derived when empty.
     *
     *   -- Background image --
     *   @type string $character_image_url  Portrait URL. Default ''.
     *   @type float  $image_opacity        Default 0.2.
     *
     *   -- Badge --
     *   @type string $badge_label          Auto-filled from _promo_label meta.
     *   @type string $badge_bg             Defaults to $accent_color.
     *   @type string $badge_text_color     Defaults to $accent_text_color.
     *
     *   -- Content --
     *   @type string $title                Auto-filled from product title.
     *   @type string $subtitle             Auto-filled from short description.
     *   @type string $features_html        Auto-filled from description.
     *   @type string $features_box_style   Extra inline style on the features box.
     *
     *   -- Price --
     *   @type string $regular_price        Auto-filled.
     *   @type string $sale_price           Auto-filled.
     *   @type string $price_color          Defaults to $accent_color.
     *   @type string $price_label          Auto-filled from _price_details meta.
     *   @type string $discount_badge       Auto-calculated (e.g. "33% OFF").
     *   @type string $regular_price_prefix Default 'Value: $'.
     *
     *   -- CTA --
     *   @type int    $product_id           WC product ID — first positional argument.
     *   @type string $button_text          Auto-filled from _add_to_cart_button_custom_text meta.
     *   @type array  $payment_methods      Default ['applePay', 'googlePay'].
     *   @type string $return_url           Post-payment redirect URL.
     *
     *   -- Footer --
     *   @type string $dismiss_text         Auto-filled from _modal_dismiss_text meta.
     *   @type string $dismiss_href         Default '#' (dismisses modal).
     *   @type string $footer_html          Arbitrary HTML below dismiss link.
     *
     *   -- Slots --
     *   @type string $before_badge         HTML above the badge.
     *   @type string $before_features      HTML between subtitle and features box.
     *   @type string $before_button        HTML between price and CTA button.
     *   @type string $after_button         HTML after CTA button.
     *
     *   -- Extras --
     *   @type bool   $show_payment_icons   Show Visa / Mastercard / PayPal trust icons after CTA. Default false.
     * }
     */
    function growtype_wc_product_modal(int $product_id = 0, array $params = []): string
    {
        // ── Base defaults ──────────────────────────────────────────────────────
        $standalone           = (bool) ($params['standalone']           ?? true);
        $modal_class          = (string) ($params['modal_class']        ?? '');
        $modal_data           = (array) ($params['modal_data']          ?? []);
        $max_width            = (string) ($params['max_width']          ?? '550px');

        $bg_color             = (string) ($params['bg_color']           ?? '#0e0e0e');
        $accent_color         = (string) ($params['accent_color']       ?? '#fcb901');
        $accent_color_rgb     = (string) ($params['accent_color_rgb']   ?? '252, 185, 1');
        $accent_text_color    = (string) ($params['accent_text_color']  ?? '#000');
        $border_width         = (string) ($params['border_width']       ?? '1px');
        $border_radius        = (string) ($params['border_radius']      ?? '20px');
        $box_shadow           = (string) ($params['box_shadow']         ?? "0 0 30px rgba({$accent_color_rgb}, 0.2)");

        $character_image_url  = (string) ($params['character_image_url'] ?? '');
        $image_opacity        = (float) ($params['image_opacity']       ?? 0.2);

        $payment_methods      = (array) ($params['payment_methods']     ?? ['applePay', 'googlePay']);
        $return_url           = $params['return_url']                   ?? null;

        $features_box_style   = (string) ($params['features_box_style'] ?? '');
        $dismiss_href         = (string) ($params['dismiss_href']       ?? '#');
        $regular_price_prefix = (string) ($params['regular_price_prefix'] ?? 'Value: $');
        $price_color          = (string) ($params['price_color']        ?? $accent_color);

        $before_badge         = (string) ($params['before_badge']       ?? '');
        $before_features      = (string) ($params['before_features']    ?? '');
        $before_button        = (string) ($params['before_button']      ?? '');
        $after_button         = (string) ($params['after_button']       ?? '');
        $footer_html          = (string) ($params['footer_html']        ?? '');
        $show_payment_icons   = (bool) ($params['show_payment_icons']   ?? true);

        // ── Smart defaults from WC product ────────────────────────────────────
        $wc_product = ($product_id > 0 && function_exists('wc_get_product'))
            ? wc_get_product($product_id)
            : null;

        if ($wc_product) {
            $modal_id       = $params['modal_id']       ?? ('gwcProductModal-' . sanitize_html_class($wc_product->get_slug() ?: $product_id));
            $title          = $params['title']          ?? $wc_product->get_title();
            $subtitle       = $params['subtitle']       ?? $wc_product->get_short_description();
            $features_html  = $params['features_html']  ?? $wc_product->get_description();
            $regular_price  = $params['regular_price']  ?? $wc_product->get_regular_price();
            $sale_price     = $params['sale_price']     ?? $wc_product->get_sale_price();

            // Auto-calculate discount badge
            if (!isset($params['discount_badge']) && !empty($regular_price) && !empty($sale_price)
                && (float) $sale_price < (float) $regular_price
            ) {
                $pct            = round((1 - (float) $sale_price / (float) $regular_price) * 100);
                $discount_badge = $pct . '% OFF';
            } else {
                $discount_badge = (string) ($params['discount_badge'] ?? '');
            }

            $badge_label  = $params['badge_label']  ?? (get_post_meta($product_id, '_promo_label',                   true) ?: '');
            $price_label  = $params['price_label']  ?? (get_post_meta($product_id, '_price_details',                 true) ?: '');
            $button_text  = $params['button_text']  ?? (get_post_meta($product_id, '_add_to_cart_button_custom_text', true) ?: __('Get Started', 'growtype-wc'));
            $dismiss_text = $params['dismiss_text'] ?? (get_post_meta($product_id, '_modal_dismiss_text',            true) ?: '');
        } else {
            $modal_id       = (string) ($params['modal_id']       ?? 'gwcProductModal');
            $title          = (string) ($params['title']          ?? '');
            $subtitle       = (string) ($params['subtitle']       ?? '');
            $features_html  = (string) ($params['features_html']  ?? '');
            $regular_price  = (string) ($params['regular_price']  ?? '');
            $sale_price     = (string) ($params['sale_price']     ?? '');
            $discount_badge = (string) ($params['discount_badge'] ?? '');
            $badge_label    = (string) ($params['badge_label']    ?? '');
            $price_label    = (string) ($params['price_label']    ?? '');
            $button_text    = (string) ($params['button_text']    ?? __('Get Started', 'growtype-wc'));
            $dismiss_text   = (string) ($params['dismiss_text']   ?? '');
        }

        $badge_bg         = (string) ($params['badge_bg']         ?? $accent_color);
        $badge_text_color = (string) ($params['badge_text_color'] ?? $accent_text_color);

        // ── Build data-* attribute string ─────────────────────────────────────
        $modal_data_str = '';
        foreach ($modal_data as $key => $val) {
            $modal_data_str .= ' ' . esc_attr($key) . '="' . esc_attr($val) . '"';
        }

        // ── Render ────────────────────────────────────────────────────────────
        ob_start();
        if ($standalone) : ?>
<div class="modal modal-half-half fade <?php echo esc_attr($modal_class); ?>"
     id="<?php echo esc_attr($modal_id); ?>"
     tabindex="-1"
     aria-hidden="true"
     aria-labelledby="<?php echo esc_attr($modal_id); ?>Label"
     <?php echo $modal_data_str; ?>>
    <div class="modal-dialog modal-dialog-centered" style="max-width:<?php echo esc_attr($max_width); ?>;">
        <div class="modal-content"
             style="background:<?php echo esc_attr($bg_color); ?>; border:<?php echo esc_attr($border_width); ?> solid <?php echo esc_attr($accent_color); ?>; border-radius:<?php echo esc_attr($border_radius); ?>; overflow:hidden; color:#fff; position:relative;">
<?php endif; ?>

            <?php if (!empty($character_image_url)) : ?>
            <div class="gwc-modal__bg-image"
                 style="display:block!important; position:absolute; inset:0; opacity:<?php echo esc_attr($image_opacity); ?>; background-image:url('<?php echo esc_url($character_image_url); ?>'); background-size:cover; background-position:center; z-index:0; pointer-events:none;">
                <div style="background:linear-gradient(0deg, <?php echo esc_attr($bg_color); ?> 0%, transparent 100%); height:100%; width:100%;"></div>
            </div>
            <?php endif; ?>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"
                        style="position:absolute; right:20px; top:20px; z-index:10;"></button>

            <div class="modal-body text-center p-4 p-md-5" style="position:relative; z-index:1;">

                <?php echo $before_badge; ?>

                <?php if (!empty($badge_label)) : ?>
                <div class="gwc-modal__badge mb-3"
                     style="display:inline-block; background:<?php echo esc_attr($badge_bg); ?>; color:<?php echo esc_attr($badge_text_color); ?>; padding:5px 18px; border-radius:50px; font-weight:800; font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; box-shadow:0 0 18px rgba(<?php echo esc_attr($accent_color_rgb); ?>, 0.4);">
                    <?php echo $badge_label; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($title)) : ?>
                <h3 class="gwc-modal__title" style="font-weight:900; font-size:1.8rem; margin-bottom:8px; color:#fff;">
                    <?php echo $title; ?>
                </h3>
                <?php endif; ?>

                <?php if (!empty($subtitle)) : ?>
                <div class="gwc-modal__subtitle mb-4" style="opacity:0.85; font-size:1rem;">
                    <?php echo $subtitle; ?>
                </div>
                <?php endif; ?>

                <?php echo $before_features; ?>

                <?php if (!empty($features_html)) : ?>
                <div class="gwc-modal__features mb-4"
                     style="background:rgba(<?php echo esc_attr($accent_color_rgb); ?>, 0.05); border:1px dashed rgba(<?php echo esc_attr($accent_color_rgb); ?>, 0.3); padding:20px 25px; border-radius:14px; text-align:left; <?php echo esc_attr($features_box_style); ?>">
                    <?php echo $features_html; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($regular_price) || !empty($sale_price)) : ?>
                <div class="gwc-modal__price mb-4">
                    <?php if (!empty($regular_price)) : ?>
                    <div style="font-size:0.9rem; opacity:0.45; text-decoration:line-through;">
                        <?php echo esc_html($regular_price_prefix) . esc_html($regular_price); ?>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-top:4px;">
                    <?php if (!empty($sale_price)) : ?>
                        <span style="font-size:3rem; font-weight:950; color:<?php echo esc_attr($price_color); ?>; line-height:1;">
                        $<?php echo esc_html($sale_price); ?>
                    </span>
                    <?php elseif (!empty($regular_price)) : ?>
                        <span style="font-size:3rem; font-weight:950; color:#fff; line-height:1;">
                        $<?php echo esc_html($regular_price); ?>
                    </span>
                    <?php endif; ?>
                        <?php if (!empty($discount_badge)) : ?>
                        <span style="background:#ff0055; color:#fff; padding:2px 8px; border-radius:4px; font-size:0.8rem; font-weight:800;">
                            <?php echo esc_html($discount_badge); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($price_label)) : ?>
                    <p style="font-size:0.82rem; opacity:0.6; margin-top:6px;"><?php echo esc_html($price_label); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php echo $before_button; ?>

                <?php if ($product_id > 0 && class_exists('Growtype_Wc_Child_Payment')) : ?>
                <div class="gwc-modal__cta mb-3">
                    <?= Growtype_Wc_Child_Payment::render_checkout_button(
                        $button_text,
                        $product_id,
                        $payment_methods,
                        $return_url
                    ); ?>
                </div>
                <?php endif; ?>

                <?php echo $after_button; ?>

                <?php if ($show_payment_icons) : ?>
                <div class="gwc-modal__payment-icons" style="opacity:0.5; filter:grayscale(1); margin:8px 0;">
                    <img src="<?php echo esc_url(plugins_url('growtype-wc/public/icons/payment-methods/visa-white.svg')); ?>" height="20" class="me-2" alt="Visa">
                    <img src="<?php echo esc_url(plugins_url('growtype-wc/public/icons/payment-methods/mastercard-white.svg')); ?>" height="20" alt="Mastercard">
                    <img src="<?php echo esc_url(plugins_url('growtype-wc/public/icons/payment-methods/paypal.svg')); ?>" height="20" class="ms-2" alt="PayPal">
                </div>
                <?php endif; ?>

                <?php if (!empty($dismiss_text)) : ?>
                <div class="gwc-modal__dismiss" style="margin-top:16px;">
                    <a href="<?php echo esc_url($dismiss_href); ?>"
                       <?php echo $dismiss_href === '#' ? 'data-bs-dismiss="modal"' : ''; ?>
                       style="font-size:0.82rem; color:#fff; text-decoration:none; opacity:0.45; border-bottom:1px solid rgba(255,255,255,0.2);">
                        <?php echo $dismiss_text; ?>
                    </a>
                </div>
                <?php endif; ?>

                <?php echo $footer_html; ?>

            </div><!-- /.modal-body -->

<?php if ($standalone) : ?>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
<?php endif;
        return (string) ob_get_clean();
    }
}
