<?php

/**
 * @param $cart_item
 * @return false|string
 */
function growtype_wc_render_cart_single_item($cart_item)
{
    if (empty($cart_item)) {
        return '';
    }

    $product_id = isset($cart_item['variation_id']) && !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];

    $_product = wc_get_product($product_id);
    $product_price_html = WC()->cart->get_product_price($_product);
    $product_image = Growtype_Wc_Product::preview_image($product_id);
    $product_attributes = Growtype_Wc_Product::visible_attributes($product_id);
    $product_permalink = esc_url($_product->get_permalink($cart_item));

    ob_start();
    ?>
    <li class="shoppingcart-products-item" data-cart_item_key="<?php echo $cart_item['key'] ?>">
        <a href="#" class="e-remove"
           aria-label="<?php echo __('Remove this item', 'growtype-wc') ?>"
           data-product_id="<?php echo $cart_item['product_id'] ?>"
           data-variation_id="<?php echo $cart_item['variation_id'] ?>"
           data-product_sku="<?php echo $_product->get_sku() ?>"
        ></a>
        <a href="<?php echo esc_url($_product->get_permalink($cart_item)) ?>" class="product-image">
            <?php echo $product_image ?>
        </a>
        <div class="product-details">
            <a href="<?php echo $product_permalink ?>" class="product-name">
                <div class="product-name-title"><?php echo __($cart_item['data']->get_title()) ?></div>
                <?php if (isset($product_attributes) && !empty($product_attributes)) { ?>
                    <div class="product-name-summary"><?php echo wc_get_formatted_variation($product_attributes, true) ?></div>
                <?php } ?>
            </a>
            <div class="quantity">
                <span class="quantity-amount"><?php echo $cart_item['quantity'] ?></span>
                <span class="e-multiply"> x </span>
                <span class="quantity-price"><?php echo $product_price_html ?></span>
            </div>
            <div class="product-changeQuantity"
                 data-product_id="<?php echo $cart_item['product_id'] ?>"
                 data-variation_id="<?php echo $cart_item['variation_id'] ?>"
                 data-product_sku="<?php echo $_product->get_sku() ?>">
                <span class="arrow arrow-left">-</span>
                <span class="amount"><?php echo $cart_item['quantity'] ?></span>
                <span class="arrow arrow-right">+</span>
            </div>
        </div>
    </li>
    <?php

    return ob_get_clean();

}

/**
 * @return false|string
 * Get cart content
 */
function growtype_wc_render_cart_content()
{
    ob_start();

    ?>

    <?php if (!WC()->cart->is_empty()) { ?>

    <div class="b-shoppingcart-content">

        <ul class="shoppingcart-products">
            <?php foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                echo growtype_wc_render_cart_single_item($cart_item);
            } ?>
        </ul>

    </div>

    <div class="shoppingcart-total">
        <p class="text"><?php _e('Subtotal', 'growtype-wc'); ?></p>
        <div class="e-subtotal_price"><?php echo WC()->cart->get_cart_subtotal(); ?></div>
        <p class="text-extra">Your total cart amount</p>
        <div class="woocommerce-mini-cart__buttons buttons">
            <a href="<?php echo wc_get_checkout_url(); ?>" class="btn btn-primary"><?php echo __('Checkout', 'growtype-wc') ?></a>
            <?php if (!growtype_wc_skip_cart_page()) { ?>
                <a href="<?php echo wc_get_cart_url(); ?>" class="btn btn-secondary"><?php echo __('View cart', 'growtype-wc') ?></a>
            <?php } ?>
        </div>
    </div>

<?php } else { ?>
    <div class="col-12 text-center">
        <p class="e-message"><?php _e('No products in the cart.', 'growtype-wc'); ?></p>
        <a href="<?php echo get_permalink(wc_get_page_id('shop')); ?>" class="btn btn-primary"><?php _e('Go to shop', 'growtype-wc'); ?></a>
    </div>
<?php } ?>

    <?php
    $cart = ob_get_clean();
    return $cart;
}

/**
 * @return bool
 */
function growtype_wc_cart_icon_is_active()
{
    return class_exists('WooCommerce') && get_theme_mod('woocommerce_cart_icon', true) ? true : false;
}

/**
 * @param $product
 * @return bool
 */
function growtype_wc_product_is_in_cart($product)
{
    $product_in_cart = WC()->cart->find_product_in_cart(WC()->cart->generate_cart_id($product->get_id()));

    return !empty($product_in_cart) ? true : false;
}

/**
 * @return mixed
 * Skip cart page
 */
function growtype_wc_skip_cart_page()
{
    return get_theme_mod('woocommerce_skip_cart_page', false);
}

function growtype_wc_get_cart_items_gtm()
{
    $items = [];

    if (!empty(WC()) && !empty(WC()->cart)) {
        $items = WC()->cart->get_cart();

        if (empty($items)) {
            $default_product = apply_filters('growtype_wc_get_cart_items_gtm_default_product', null);

            if (!empty($default_product)) {
                return [
                    [
                        'item_id' => $default_product->get_id(),
                        'item_name' => $default_product->get_name(),
                        'affiliation' => "",
                        'coupon' => "",
                        'discount' => "",
                        'price' => $default_product->get_price(),
                        'promotion_id' => "",
                        'promotion_name' => "",
                        'quantity' => 1
                    ]
                ];
            }
        }
    }

    $data = [];
    foreach ($items as $item) {
        array_push($data, [
            'item_id' => $item['product_id'],
            'item_name' => $item['data']->get_name(),
            'affiliation' => "",
            'coupon' => "",
            'discount' => round((int)$item['data']->get_regular_price() - (int)$item['data']->get_price(), 2),
            'price' => $item['data']->get_price(),
            'promotion_id' => "",
            'promotion_name' => "",
            'quantity' => $item['quantity']
        ]);
    }

    return $data;
}

function growtype_wc_get_purchase_items_gtm($order_items)
{
    $data = [];
    foreach ($order_items as $item) {
        array_push($data, [
            'item_id' => $item['product_id'],
            'item_name' => $item->get_name(),
            'affiliation' => "",
            'coupon' => "",
            'price' => $item->get_data()['total'],
            'promotion_id' => "",
            'promotion_name' => "",
            'quantity' => $item['quantity']
        ]);
    }

    return $data;
}

function growtype_wc_get_cart_totals()
{
    $discount_total = 0;
    $discount_percentage = 0;
    $regular_price_total = 0;
    foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
        $product = $values['data'];

        if ($product->is_on_sale()) {
            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_sale_price();
            $discount = ((float)$regular_price - (float)$sale_price) * (int)$values['quantity'];
            $discount_total += $discount;
            $regular_price_total += $regular_price;
        }
    }

    /**
     * Discount
     */
    if ($discount_total > 0) {
        $discount_percentage = round(($discount_total * 100) / $regular_price_total, 0);
    }

    return [
        'regular_price' => $regular_price_total,
        'discount' => $discount_total,
        'discount_percentage' => $discount_percentage,
    ];
}
