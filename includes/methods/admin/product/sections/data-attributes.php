<?php

function growtype_wc_get_custom_attributes()
{
    return [
        [
            'key' => 'is_radio_select',
            'label' => __('Radio select', 'growtype-wc')
        ],
        [
            'key' => 'is_label_hidden',
            'label' => __('Label hidden', 'growtype-wc')
        ]
    ];
}

/**
 * Check status
 */
function growtype_wc_get_attribute($attribute_type, $attribute_key)
{
    global $post;

    $post_id = isset($_POST['post_id']) ? $_POST['post_id'] : null;

    if (!empty($post)) {
        $post_id = $post->ID;
    }

    $attribute_name = growtype_wc_format_attribute_key($attribute_type, $attribute_key);

    return get_post_meta($post_id, $attribute_name, true);
}

/**
 * Get html
 */
add_action('woocommerce_after_product_attribute_settings', 'growtype_wc_after_product_attribute_settings', 10, 1);
function growtype_wc_after_product_attribute_settings($attribute, $i = 0)
{
    $attribute_type = $attribute->get_name();
    $custom_attributes = growtype_wc_get_custom_attributes();

    foreach ($custom_attributes as $custom_attribute) {
        $attribute_name = 'attribute_' . $attribute_type . '_' . $custom_attribute['key'] . '[' . esc_attr($i) . ']';
        ?>
        <tr>
            <td>
                <div class="enable_variation">
                    <label>
                        <input type="hidden" name="<?php echo $attribute_name; ?>" value="0"/>
                        <input type="checkbox" class="checkbox" <?php echo checked(growtype_wc_get_attribute($attribute_type, $custom_attribute['key'])); ?> name="<?php echo $attribute_name; ?>" value="1"/>
                        <?php echo $custom_attribute['label']; ?>
                    </label>
                </div>
            </td>
        </tr>
        <?php
    }
}

function growtype_wc_format_attribute_key($attribute_type, $attribute_key)
{
    return 'attribute_' . $attribute_type . '_' . $attribute_key;
}

/**
 * Save value
 */
add_action('wp_ajax_woocommerce_save_attributes', 'growtype_wp_ajax_woocommerce_save_attributes', 9);
function growtype_wp_ajax_woocommerce_save_attributes()
{
    check_ajax_referer('save-attributes', 'security');
    parse_str($_POST['data'], $data);
    $post_id = absint($_POST['post_id']);

    $custom_attributes = growtype_wc_get_custom_attributes();

    foreach ($data['attribute_names'] as $attribute_type) {
        foreach ($custom_attributes as $custom_attribute) {
            $attribute_name = growtype_wc_format_attribute_key($attribute_type, $custom_attribute['key']);

            foreach ($data[$attribute_name] as $i => $val) {
                update_post_meta($post_id, $attribute_name, $val);
                WC()->session->set($attribute_name, wc_string_to_bool($val));
            }
        }
    }
}
