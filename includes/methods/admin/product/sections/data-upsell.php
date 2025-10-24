<?php

add_filter('product_type_options', function ($type_options) {
    $type_options['growtype_wc_upsell'] = array (
        'id' => 'growtype_wc_upsell',
        'wrapper_class' => '',
        'label' => __('Upsell', 'woocommerce'),
        'description' => __('Upsell.', 'woocommerce'),
        'default' => 'no',
    );

    return $type_options;
}, 10, 1);

/**
 * Update product meta
 */
add_action('woocommerce_update_product', function ($post_id, $product) {
    if (isset($_POST['post_type']) && $_POST['post_type'] === 'product' && isset($_POST['action']) && $_POST['action'] === 'editpost') {
        $is_active = isset($_POST['growtype_wc_upsell']) && $_POST['growtype_wc_upsell'] ? 'yes' : 'no';
        update_post_meta($post_id, Growtype_Wc_Upsell::META_KEY, $is_active);
    }
}, 0, 2);

/**
 * Save product meta
 */
add_action("save_post_product", function ($post_id, $product, $update) {
    if (isset($_POST['action']) && $_POST['action'] === 'editpost') {
        if (isset($_POST['growtype_wc_upsell_position'])) {
            update_post_meta($post_id, '_growtype_wc_upsell_position', esc_attr($_POST['growtype_wc_upsell_position']));
        }
    }
}, 10, 3);

/**
 * Product data tabs
 */
add_filter('woocommerce_product_data_tabs', function ($default_tabs) {
    global $post;

    $tabs = array (
        'growtype_wc_upsell_settings_tab' => array (
            'label' => esc_html__('Upsell Settings', 'growtype-wc'),
            'target' => 'growtype_wc_upsell_tab',
            'priority' => 60,
            'class' => array ('show_if_growtype_wc_upsell'),
        ),
    );

    $default_tabs = array_merge($default_tabs, $tabs);

    return $default_tabs;
}, 10, 1);

/**
 * Product data panels
 */
add_action('woocommerce_product_data_panels', function () {
    global $post;

    $positions = [
        1 => '1',
        2 => '2',
        3 => '3',
        4 => '4',
        5 => '5',
    ];

    ?>
    <div id="growtype_wc_upsell_tab" class="panel woocommerce_options_panel">
        <p class="form-field">
            <?php $growtype_wc_upsell_position = growtype_wc_get_upsell_position($post->ID); ?>
            <label for="growtype-wc-trial-period"><?php esc_html_e('Position', 'growtype-wc'); ?></label>
            <select name="growtype_wc_upsell_position" id="growtype_wc_upsell_position">
                <?php foreach ($positions as $key => $position) { ?>
                    <option value="<?php echo $key ?>" <?php echo selected((int)$key === (int)$growtype_wc_upsell_position) ?>><?php echo $position ?></option>
                <?php } ?>
            </select>
        </p>
        <?php do_action('growtype_wc_upsell_tab_before_close', $post->ID); ?>
    </div>

    <script>
        if (!jQuery('#growtype_wc_upsell').is(':checked')) {
            jQuery('.show_if_growtype_wc_upsell').hide();
        }

        jQuery("#growtype_wc_upsell").change(function () {
            if (this.checked) {
                jQuery('.show_if_growtype_wc_upsell').show();
            } else {
                jQuery('.show_if_growtype_wc_upsell').hide();
            }
        });
    </script>
    <?php
});

add_filter('parse_query', function ($query) {
    if (isset($_GET['product_type']) && $_GET['product_type'] === 'growtype_wc_upsell') {
        $query->set('meta_query', array (
            array (
                'key' => Growtype_Wc_Upsell::META_KEY,
                'value' => 'yes',
                'compare' => '='
            )
        ));
    }
}, 1);
