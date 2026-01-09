<?php

class Growtype_Wc_Upsell
{
    const META_KEY = '_growtype_wc_upsell';

    public function __construct()
    {
        add_filter('woocommerce_get_return_url', function ($return_url, $order) {
            $upsells = self::get();

            if (!empty($upsells)) {
                $query_data['upsell'] = $upsells[0]['slug'];

                $return_url = add_query_arg($query_data, $return_url);
            }

            return $return_url;
        }, 0, 2);

        add_action('woocommerce_thankyou_intro', [$this, 'show'], 0, 20);

        add_filter('growtype_wc_single_product_main_content_render', [$this, 'product_main_content_render'], 0, 20);
    }

    public function product_main_content_render($render)
    {
        global $product;

        if (!empty($product)) {
            $is_upsell = get_post_meta($product->get_id(), self::META_KEY, true);

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

    public static function show($order_id)
    {
        $current_slug = isset($_GET['upsell']) ? sanitize_text_field($_GET['upsell']) : '';

        if (!$current_slug) {
            return;
        }

        $current_url = esc_url_raw(home_url(add_query_arg(null, null)));

        $upsells = self::get();

        $slugs = wp_list_pluck($upsells, 'slug');
        $pos = array_search($current_slug, $slugs, true);
        if ($pos === false) {
            return;
        }

        $user_id = get_current_user_id();

        // advance until we find one the user hasn't bought
        while (isset($upsells[$pos])) {
            $slug = $upsells[$pos]['slug'];
            $post = get_page_by_path($slug, OBJECT, 'product');
            if ($post && $post->ID) {
                $product = wc_get_product($post->ID);

                $product_id = $product->get_id();
                $has_purchased = growtype_wc_user_has_purchased_product($product_id, $user_id);
                
                if (!$has_purchased) {
                    $has_purchased = self::has_order_purchased_product($order_id, $product_id);
                }

                if ($product && !$has_purchased) {
                    // found one they haven’t bought
                    break;
                }
            }
            // skip to next
            $pos++;
        }

        $next_url = remove_query_arg('upsell');

        if (!isset($upsells[$pos]) || empty($product)) {
            wp_redirect($next_url);
            exit();
        }

        if ($current_slug !== $upsells[$pos]['slug']) {
            $next_url = add_query_arg('upsell', $upsells[$pos]['slug'], $next_url);
            wp_redirect($next_url);
            exit();
        }

        $link_text = __('Skip', 'growtype-wc');
        if (isset($upsells[$pos + 1])) {
            $next_slug = $upsells[$pos + 1]['slug'];
            $next_url = add_query_arg('upsell', $next_slug, $next_url);
            $link_text = __('Skip for now', 'growtype-wc');
        }

        // remove default thank-you table
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

    public static function render_content($product, $params)
    {
        $short_desc = apply_filters('growtype_the_content', $product->get_short_description());
        $description = $product->get_description();
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

            <?php if ($description) : ?>
                <div class="upsell-desc"><?= wp_kses_post($description) ?></div>
            <?php endif; ?>

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
        <?php

        return ob_get_clean();
    }

    public static function get($slug = null)
    {
        $args = [
            'limit' => -1,
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_key' => '_growtype_wc_upsell_position',
            'meta_query' => [
                [
                    'key' => '_growtype_wc_upsell',
                    'value' => 'yes',
                    'compare' => '=',
                ],
            ],
        ];

        // Optional: let the DB pre-filter by slug
        if ($slug) {
            $args['slug'] = $slug;
        }

        $upsell_products = wc_get_products($args);

        // Defensive filter to avoid stray "no"/missing values
        $upsell_products = array_values(array_filter($upsell_products, function ($p) {
            $val = get_post_meta($p->get_id(), '_growtype_wc_upsell', true);
            return is_string($val) && trim(strtolower($val)) === 'yes';
        }));

        $upsells_available = array_map(function ($product) {
            return ['slug' => $product->get_slug()];
        }, $upsell_products);

        $upsells = apply_filters('growtype_wc_get_upsells', $upsells_available);

        if (empty($upsells)) {
            // return [] for list calls, null for single—up to you
            return $slug ? null : [];
        }

        if ($slug) {
            $matches = array_values(array_filter($upsells, function ($u) use ($slug) {
                return $u['slug'] === $slug;
            }));
            return $matches[0] ?? null; // safe access
        }

        return $upsells;
    }

    public static function has_order_purchased_product($order_id, $product_id)
    {
        $orders_to_check = [$order_id];

        // Get children (upsell orders)
        $child_orders = wc_get_orders([
            'limit' => -1,
            'meta_key' => 'parent_order_id',
            'meta_value' => $order_id,
            'return' => 'ids',
            'status' => wc_get_is_paid_statuses(),
        ]);

        if ($child_orders) {
            $orders_to_check = array_merge($orders_to_check, $child_orders);
        }

        foreach ($orders_to_check as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;
            foreach ($order->get_items() as $item) {
                if ((int)$item->get_product_id() === (int)$product_id) {
                    return true;
                }
            }
        }
        return false;
    }
}
