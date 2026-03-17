<?php

class Growtype_Wc_Upsell_Crud
{
    public static function init()
    {
        if (apply_filters('growtype_wc_traditional_upsells_enabled', false)) {
            add_action('woocommerce_thankyou_intro', [self::class, 'show'], 0, 20);
        }

        add_filter('growtype_wc_single_product_main_content_render', [self::class, 'product_main_content_render'], 0, 20);
    }

    public static function show($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order || !$order->is_paid()) {
            return;
        }

        $explicit_return_url = Growtype_Wc_Upsell_Return_Url::get_from_order($order);

        if (!empty($explicit_return_url)) {
            wp_safe_redirect($explicit_return_url);
            exit();
        }

        $current_slug = isset($_GET['upsell']) ? sanitize_text_field(wp_unslash($_GET['upsell'])) : '';

        if (empty($current_slug)) {
            return;
        }

        $current_url = esc_url_raw(home_url(add_query_arg(null, null)));
        $upsell_products = Growtype_Wc_Upsell_Catalog::get_products();
        $slugs = array_map(function ($product) {
            return $product->get_slug();
        }, $upsell_products);
        $pos = array_search($current_slug, $slugs, true);

        if ($pos === false) {
            return;
        }

        $user_id = get_current_user_id();
        $product = null;

        while (isset($upsell_products[$pos])) {
            $candidate_product = $upsell_products[$pos];
            $product_id = (int)$candidate_product->get_id();
            $has_purchased = Growtype_Wc_Upsell_Queue::user_has_purchased_product($user_id, $product_id);

            if (!$has_purchased) {
                $has_purchased = Growtype_Wc_Upsell_Queue::has_order_purchased_product($order_id, $product_id);
            }

            if (!$has_purchased) {
                $product = $candidate_product;
                break;
            }

            $pos++;
        }

        $next_url = remove_query_arg('upsell');

        if (!isset($upsell_products[$pos]) || empty($product)) {
            wp_redirect($next_url);
            exit();
        }

        if ($current_slug !== $upsell_products[$pos]->get_slug()) {
            $next_url = add_query_arg('upsell', $upsell_products[$pos]->get_slug(), $next_url);
            wp_redirect($next_url);
            exit();
        }

        $link_text = __('Skip', 'growtype-wc');

        if (isset($upsell_products[$pos + 1])) {
            $next_slug = $upsell_products[$pos + 1]->get_slug();
            $next_url = add_query_arg('upsell', $next_slug, $next_url);
            $link_text = __('Skip for now', 'growtype-wc');
        }

        remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
        remove_action('woocommerce_thankyou_intro', 'woocommerce_thankyou_intro_callback', 10);

        add_filter('body_class', function ($classes) {
            $classes[] = 'page-upsell';
            return $classes;
        });

        $payment_intent_url = Growtype_Wc_Payment::intent_url($current_url, $order_id, $product->get_id());

        echo self::render_content($product, [
            'next_url' => $next_url,
            'link_text' => $link_text,
            'payment_intent_url' => $payment_intent_url,
        ]);
    }

    public static function product_main_content_render($render)
    {
        global $product;

        if (!empty($product)) {
            $is_upsell = get_post_meta($product->get_id(), Growtype_Wc_Upsell::META_KEY, true);

            if ($is_upsell === 'yes') {
                add_filter('body_class', function ($classes) {
                    $classes[] = 'page-upsell';
                    return $classes;
                });

                return '<div class="container" style="padding-bottom: 150px;">' . self::render_content($product, [
                        'next_url' => '',
                        'link_text' => '',
                        'payment_intent_url' => '',
                    ]) . '</div>';
            }
        }

        return $render;
    }

    public static function render_content($product, $params)
    {
        $short_desc = apply_filters('growtype_the_content', $product->get_short_description());
        $description = apply_filters('growtype_the_content', $product->get_description());
        $price_html = $product->get_price_html();
        $price = $product->get_price();

        $next_url = $params['next_url'] ?? '';
        $link_text = $params['link_text'] ?? '';
        $payment_intent_url = $params['payment_intent_url'] ?? '';

        ob_start();
        ?>
        <div class="gwc-upsell">
            <?php if ($short_desc) : ?>
                <div class="upsell-intro"><?= $short_desc ?></div>
            <?php endif; ?>

            <div class="gwc-upsell-paymentform">
                <div class="gwc-upsell-price">
                    <?= Growtype_Wc_Product::get_discount_percentage_label_formatted($product->get_id()) ?>
                    <?= Growtype_Wc_Product::get_promo_label_formatted($product->get_id()) ?>
                    <div class="title"><?= esc_html($product->get_title()) ?></div>
                    <div class="price-wrapper"><?= $price_html ?></div>
                    <div class="extra-details">
                        <?= Growtype_Wc_Product::get_extra_details_formatted($product->get_id()) ?>
                    </div>
                </div>

                <div class="b-actions">
                    <?php if (!empty($next_url)) { ?>
                        <a href="<?= esc_url($next_url) ?>" class="btn btn-secondary"><?= esc_html($link_text) ?></a>
                    <?php } ?>

                    <a href="<?= esc_url($payment_intent_url) ?>" class="btn btn-primary">
                        <?= esc_html__('🔓 Unlock Now', 'growtype-wc') ?> – <?= wc_price($price) ?>
                    </a>
                </div>
            </div>

            <?php if ($description) : ?>
                <div class="upsell-desc"><?= $description ?></div>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }
}