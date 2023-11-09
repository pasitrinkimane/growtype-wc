<?php

add_action('growtype_header_before_close', 'growtype_header_before_close_wc_extend');
function growtype_header_before_close_wc_extend()
{
    if (get_theme_mod('woocommerce_cart_enabled', true)) {
        echo growtype_wc_include_view('components.cart');
    }
}

add_action('growtype_side_nav', 'growtype_wc_side_nav');
function growtype_wc_side_nav()
{
    if (growtype_wc_wishlist_page_icon()) { ?>
        <li class="e-wishlist">
            <a href="<?php echo get_permalink(get_page_by_path('wishlist')) ?>">
                <i class="icon-wishlist"></i>
            </a>
        </li>
    <?php }

    if (growtype_wc_cart_icon_is_active()) {
        echo growtype_wc_get_cart_icon();
    }
}

function growtype_wc_get_cart_icon()
{
    ob_start();
    ?>
    <a href="#" class="e-cart">
        <i class="icon-cart"></i>
    </a>
    <?php
    $html = ob_get_clean();

    return apply_filters('growtype_wc_get_cart_icon', $html);
}

add_action('wp_head', 'growtype_wc_wp_head');
function growtype_wc_wp_head()
{
    ?>
    <?php if (get_theme_mod('sidebar_shop_position') === 'right') { ?>
    <style>
        #sidebar-shop {
            float: right;
            border-width: 0px 0px 0px 1px;
        }
    </style>
<?php } ?>
    <?php
}

/**
 * Add classes to body
 */
add_filter('body_class', 'growtype_wc_extend_body_classes', 100);
function growtype_wc_extend_body_classes($classes)
{
    if (in_array('page-wishlist-data', $classes)) {
        $classes[] = 'woocommerce';
    }

    if (growtype_wc_is_account_page()) {
        $url_slug = Growtype_Page::get_url_slug();
        $classes[] = 'page-' . $url_slug;
    }

    if (get_option('woocommerce_cart_redirect_after_add') !== 'yes') {
        $classes[] = 'ajaxcart-enabled';
    }

    if (get_theme_mod('woocommerce_cart_enabled', true)) {
        $classes[] = 'cart-enabled';
    }

    $classes[] = Growtype_Wc_Product::sidebar() ? 'has-sidebar-product' : null;

    $classes[] = Growtype_Wc_Product::display_shop_catalog_sidebar() ? 'has-sidebar-catalog' : null;

    if (is_single()) {
        $classes[] = get_theme_mod('woocommerce_product_page_gallery_type', 'woocommerce-product-gallery-type-2');

        $single_item_available = growtype_wc_product_is_sold_individually(get_the_ID());

        if ($single_item_available) {
            $classes[] = 'single-item-available';
        }
    }

    return $classes;
}
