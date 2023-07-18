<?php

/**
 * Remove product loop cta button
 */
add_action('wp_loaded', 'growtype_wc_shop_loop_remove_cta');
function growtype_wc_shop_loop_remove_cta()
{
    if (!Growtype_Wc_Product::product_preview_cta_enabled()) {
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart');
    }
}

/**
 * Render single product main content
 */
add_action('growtype_wc_single_product_main_content', 'growtype_wc_single_product_main_content_render');
function growtype_wc_single_product_main_content_render()
{
    $content = growtype_wc_include_view('partials.content-single-product');

    $content = apply_filters('growtype_wc_single_product_main_content_render', $content);

    echo $content;
}

/**
 * Related products amount
 */
add_filter('woocommerce_output_related_products_args', 'growtype_wc_output_related_products_args', 20);
function growtype_wc_output_related_products_args($args)
{
    $products_amount = 4;
    if (!empty(get_theme_mod('woocommerce_product_page_related_products_amount'))) {
        $products_amount = get_theme_mod('woocommerce_product_page_related_products_amount');
    }

    $args['posts_per_page'] = $products_amount;
    $args['columns'] = $products_amount;
    return $args;
}

/**
 * Wishlist button
 */
add_filter('woocommerce_after_add_to_cart_button', 'growtype_wc_after_add_to_cart_button', 5);
function growtype_wc_after_add_to_cart_button()
{
    $current_user = wp_get_current_user();
    $current_user_wishlist_ids = get_user_meta($current_user->ID, 'wishlist_ids', true);
    $current_user_wishlist_ids = explode(',', $current_user_wishlist_ids);
    $productInWishlist = in_array(get_the_ID(), $current_user_wishlist_ids);
    ?>
    <?php
    if (growtype_wc_wishlist_page_icon()) { ?>
        <div class="wishlist-toggle <?php echo $productInWishlist ? 'is-active' : '' ?>" data-product="<?php echo get_the_ID() ?>" title="<?php echo esc_attr__(" Add to Wishlist", "text-domain") ?>">
            <span class="e-text"><?php echo esc_attr__("Add to Wishlist", "text-domain") ?></span>
        </div>
    <?php } ?>
    <?php
}

/**
 * Remove the breadcrumbs
 */
add_action('wp_loaded', 'growtype_wc_remove_breadcrumbs');
function growtype_wc_remove_breadcrumbs()
{
    if (get_theme_mod('woocommerce_product_page_breadcrumb_disabled')) {
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0);
    }
}

/**
 * Breadcrumb home url
 */
add_filter('woocommerce_breadcrumb_home_url', 'growtype_wc_breadcrumb_home_url');
function growtype_wc_breadcrumb_home_url()
{
    return get_permalink(wc_get_page_id('shop'));
}

/**
 * Setup breadcrumb
 */
add_filter('woocommerce_breadcrumb_defaults', 'growtype_wc_breadcrumb_defaults');
function growtype_wc_breadcrumb_defaults($defaults)
{
    $shop_page = get_post(wc_get_page_id('shop'));

    return array (
        'delimiter' => ' &#47; ',
        'wrap_before' => '<div class="woocommerce-breadcrumb ' . (!is_product() ? 'd-none' : '') . '" itemprop="breadcrumb"><div class="woocommerce-breadcrumb-inner container">',
        'wrap_after' => '</div></div>',
        'before' => '',
        'after' => '',
        'home' => $shop_page->post_title,
    );
}

/**
 * Alter breadcrumb
 */
add_filter('woocommerce_get_breadcrumb', 'growtype_wc_get_breadcrumb', 10, 2);
function growtype_wc_get_breadcrumb($crumbs, $breadcrumb)
{
    if (is_product()) {
        array_shift($crumbs);
        array_pop($crumbs);
    }

    return $crumbs;
}

/**
 * Add info below add to form button
 */
add_action('woocommerce_single_product_summary', 'growtype_wc_after_add_to_cart_form', 100);
function growtype_wc_after_add_to_cart_form()
{
    echo growtype_wc_include_view('woocommerce.components.product-single-payment-details');
}

/**
 * Add sign after quantity input
 */
add_action('woocommerce_before_quantity_input_field', 'growtype_wc_before_quantity_input_field');
function growtype_wc_before_quantity_input_field()
{
    $single_item_available = growtype_wc_single_item_available(get_the_ID());

    if (!$single_item_available) {
        echo '<div class="btn btn-down">-</div>';
    }
}

/**
 * Add sign after quantity input
 */
add_action('woocommerce_after_quantity_input_field', 'growtype_wc_after_quantity_input_field');
function growtype_wc_after_quantity_input_field()
{
    $single_item_available = growtype_wc_single_item_available(get_the_ID());

    if (!$single_item_available) {
        echo '<div class="btn btn-up">+</div>';
    }
}

/**
 * Disable single product page
 */
add_filter('woocommerce_register_post_type_product', 'growtype_wc_register_post_type_product', 12, 1);
function growtype_wc_register_post_type_product($args)
{
    if (get_theme_mod('woocommerce_product_page_access_disabled')) {
        $args["publicly_queryable"] = false;
        $args["public"] = false;
    }

    return $args;
}

/**
 * Disable single product page
 */
add_filter('woocommerce_get_item_data', 'growtype_wc_woocommerce_get_item_data', 12, 2);
function growtype_wc_woocommerce_get_item_data($item_data, $cart_item)
{
    if (isset($cart_item['variation_id'])) {
        $visible_attributes = Growtype_Wc_Product::visible_attributes($cart_item['variation_id']);

        $available_items = [];
        foreach ($visible_attributes as $key => $visible_attribute) {
            $term = get_term_by('slug', $visible_attribute, $key);

            foreach ($item_data as $item) {
                if ($item['value'] === $term->name) {
                    array_push($available_items, $item);
                }
            }
        }

        $item_data = $available_items;
    }

    return $item_data;
}

/**
 * Size guide button
 */
add_action('woocommerce_before_single_variation', 'growtype_wc_size_guide_cta', 10);
function growtype_wc_size_guide_cta()
{
    $size_guide = get_theme_mod('woocommerce_product_page_size_guide_details');

    if (!empty($size_guide)) {
        echo '<button class="btn btn-secondary btn-sizeguide" type="button" data-bs-toggle="modal" data-bs-target="#staticBackdrop">Size guide</button>';

        echo '<!-- Modal -->
<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="staticBackdropLabel">Size guide</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        ' . $size_guide . '
      </div>
    </div>
  </div>
</div>';
    }
}
