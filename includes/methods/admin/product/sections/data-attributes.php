<?php

/**
 * Check status
 */
function growtype_wc_get_attribute($attribute_name, $key)
{
    global $post;

    $post_id = isset($_POST['post_id']) ? $_POST['post_id'] : null;

    if (!empty($post)) {
        $post_id = $post->ID;
    }

    $val = !empty($post_id) ? get_post_meta($post_id, "attribute_" . $attribute_name . "_" . $key, true) : null;

    return !empty($val) ? $val : false;
}

/**
 * Get html
 */
add_action('woocommerce_after_product_attribute_settings', 'growtype_wc_after_product_attribute_settings', 10, 1);
function growtype_wc_after_product_attribute_settings($attribute, $i = 0)
{
    $radio_value = growtype_wc_get_attribute($attribute->get_name(), 'radio');

    ?>
    <tr>
        <td>
            <div class="enable_variation">
                <label>
                    <input type="hidden" name="attribute_<?php echo $attribute->get_name() ?>_radio[<?php echo esc_attr($i); ?>]" value="0"/>
                    <input type="checkbox" class="checkbox" <?php echo checked($radio_value); ?> name="attribute_<?php echo $attribute->get_name() ?>_radio[<?php echo esc_attr($i); ?>]" value="1"/>
                    <?php esc_html_e('Is - radio select', 'growtype-wc'); ?>
                </label>
            </div>
        </td>
    </tr>

    <?php
    $label_value = growtype_wc_get_attribute($attribute->get_name(), 'label');
    ?>
    <tr>
        <td>
            <div class="enable_variation">
                <label>
                    <input type="hidden" name="attribute_<?php echo $attribute->get_name() ?>_label[<?php echo esc_attr($i); ?>]" value="0"/>
                    <input type="checkbox" class="checkbox" <?php echo checked($label_value); ?> name="attribute_<?php echo $attribute->get_name() ?>_label[<?php echo esc_attr($i); ?>]" value="1"/>
                    <?php esc_html_e('Label hidden', 'growtype-wc'); ?>
                </label>
            </div>
        </td>
    </tr>
    <?php
}

/**
 * Save value
 */
add_action('wp_ajax_woocommerce_save_attributes', 'growtype_wp_ajax_woocommerce_save_attributes', 0);
function growtype_wp_ajax_woocommerce_save_attributes()
{
    check_ajax_referer('save-attributes', 'security');
    parse_str($_POST['data'], $data);
    $post_id = absint($_POST['post_id']);

    foreach ($data['attribute_names'] as $attribute_name) {
        if (array_key_exists("attribute_" . $attribute_name . "_radio", $data) && is_array($data["attribute_" . $attribute_name . "_radio"])) {
            foreach ($data["attribute_" . $attribute_name . "_radio"] as $i => $val) {
                update_post_meta($post_id, "attribute_" . $attribute_name . "_radio", wc_string_to_bool($val));
                WC()->session->set("attribute_" . $attribute_name . "_radio", wc_string_to_bool($val));
            }
        }
        if (array_key_exists("attribute_" . $attribute_name . "_label", $data) && is_array($data["attribute_" . $attribute_name . "_label"])) {
            foreach ($data["attribute_" . $attribute_name . "_label"] as $i => $val) {
                update_post_meta($post_id, "attribute_" . $attribute_name . "_label", wc_string_to_bool($val));
                WC()->session->set("attribute_" . $attribute_name . "_label", wc_string_to_bool($val));
            }
        }
    }
}
