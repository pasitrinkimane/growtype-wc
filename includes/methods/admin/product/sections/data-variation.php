<?php

function growtype_wc_get_custom_meta_fields($variation_id)
{
    $meta_fields = get_post_meta($variation_id);

    $custom_meta_fields = [];
    foreach ($meta_fields as $key => $field) {
        if (str_contains($key, 'custom_meta_')) {
            $custom_meta_fields[$key] = $field;
        }
    }

    return $custom_meta_fields;
}

add_action('woocommerce_product_after_variable_attributes', 'growtype_wc_variation_custom_settings_fields', 10, 3);
function growtype_wc_variation_custom_settings_fields($loop, $variation_data, $variation)
{
    $custom_meta_fields = growtype_wc_get_custom_meta_fields($variation->ID);

    foreach ($custom_meta_fields as $key => $custom_meta_field) {
        woocommerce_wp_textarea_input(
            array (
                'id' => $key . "_{$loop}",
                'name' => $key,
                'value' => get_post_meta($variation->ID, $key, true),
                'label' => $key,
                'desc_tip' => true,
                'description' => __('Custom field.', 'woocommerce'),
                'wrapper_class' => 'form-row form-row-full',
            )
        );
    }
}

add_action('woocommerce_save_product_variation', 'growtype_wc_save_variation_custom_settings_fields', 10, 2);
function growtype_wc_save_variation_custom_settings_fields($variation_id, $loop)
{
    $custom_meta_fields = growtype_wc_get_custom_meta_fields($variation_id);

    foreach ($custom_meta_fields as $key => $custom_meta_field) {
        $post_field = $_POST[$key];

        if (!empty($post_field)) {
            update_post_meta($variation_id, $key, esc_attr($post_field));
        }
    }
}

add_filter('woocommerce_available_variation', 'growtype_wc_load_variation_custom_settings_fields');
function growtype_wc_load_variation_custom_settings_fields($variation)
{
    $custom_meta_fields = growtype_wc_get_custom_meta_fields($variation['variation_id']);

    foreach ($custom_meta_fields as $key => $custom_meta_field) {
        $variation[$key] = get_post_meta($variation['variation_id'], $key, true);
    }

    return $variation;
}
