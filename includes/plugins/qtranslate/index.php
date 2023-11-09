<?php

add_filter('growtype_wp_ajax_qtranslate_fields_theme_mods', function ($theme_mods) {
    $wc_mods = [
        'woocommerce_product_page_payment_details',
        'woocommerce_checkout_billing_section_title',
        'woocommerce_checkout_additional_section_title',
        'woocommerce_checkout_account_section_title',
        'woocommerce_checkout_place_order_button_title',
        'woocommerce_checkout_intro_text',
        'woocommerce_thankyou_page_intro_title',
        'woocommerce_thankyou_page_intro_content',
        'woocommerce_thankyou_page_intro_content_access_platform',
        'woocommerce_product_page_sidebar_content',
        'woocommerce_product_preview_cta_label',
        'woocommerce_product_page_shop_loop_item_price_starts_from_text',
    ];

    $theme_mods = array_merge($theme_mods, $wc_mods);

    return $theme_mods;
});

add_filter('manage_edit-growtype_wc_subs_columns', 'growtype_wc_growtype_qtranslate_columns');
function growtype_wc_growtype_qtranslate_columns($columns)
{
    unset($columns['language']);

    return $columns;
}
