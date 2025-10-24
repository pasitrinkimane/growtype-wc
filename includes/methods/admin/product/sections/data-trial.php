<?php

add_filter('product_type_options', function ($type_options) {
    $type_options['growtype_wc_trial'] = array (
        'id' => 'growtype_wc_trial',
        'wrapper_class' => '',
        'label' => __('Trial', 'woocommerce'),
        'description' => __('Trial.', 'woocommerce'),
        'default' => 'no',
    );

    return $type_options;
}, 10, 1);


/**
 * Update product meta
 */
add_action('woocommerce_update_product', function ($post_id, $product) {
    if (isset($_POST['post_type']) && $_POST['post_type'] === 'product' && isset($_POST['action']) && $_POST['action'] === 'editpost') {
        $is_trial = isset($_POST['growtype_wc_trial']) && $_POST['growtype_wc_trial'] ? 'yes' : 'no';
        update_post_meta($post_id, Growtype_Wc_Trial::META_KEY, $is_trial);
    }
}, 0, 2);

/**
 * Save product meta
 */
add_action("save_post_product", function ($post_id, $product, $update) {
    if (isset($_POST['action']) && $_POST['action'] === 'editpost') {
        if (isset($_POST['growtype_wc_trial_price'])) {
            update_post_meta($post_id, '_growtype_wc_trial_price', esc_attr($_POST['growtype_wc_trial_price']));
        }

        if (isset($_POST['growtype_wc_trial_period'])) {
            update_post_meta($post_id, '_growtype_wc_trial_period', esc_attr($_POST['growtype_wc_trial_period']));
        }

        if (isset($_POST['growtype_wc_trial_duration'])) {
            update_post_meta($post_id, '_growtype_wc_trial_duration', esc_attr($_POST['growtype_wc_trial_duration']));
        }
    }
}, 10, 3);

/**
 * Product data tabs
 */
add_filter('woocommerce_product_data_tabs', function ($default_tabs) {
    global $post;

    $tabs = array (
        'growtype_wc_trial_settings_tab' => array (
            'label' => esc_html__('Trial settings', 'growtype-wc'),
            'target' => 'growtype_wc_trial_tab',
            'priority' => 60,
            'class' => array ('show_if_growtype_wc_trial'),
        ),
    );

    $default_tabs = array_merge($default_tabs, $tabs);

    return $default_tabs;
}, 10, 1);

/**
 * Product data panels
 */
add_action('woocommerce_product_data_panels', function () {
    global $woocommerce, $post;

    $periods = [
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
        'year' => 'Year',
    ];

    ?>
    <div id="growtype_wc_trial_tab" class="panel woocommerce_options_panel">
        <p class="form-field">
            <?php $growtype_wc_trial_price = get_post_meta($post->ID, '_growtype_wc_trial_price', true); ?>
            <label for="growtype-wc-trial-price"><?php esc_html_e('Price', 'growtype-wc'); ?></label>
            <input type="text" name="growtype_wc_trial_price" id="growtype-wc-trial-price" value="<?php echo $growtype_wc_trial_price; ?>"/>
        </p>
        <p class="form-field">
            <?php $growtype_wc_trial_period = growtype_wc_get_trial_period($post->ID); ?>
            <label for="growtype-wc-trial-period"><?php esc_html_e('Period', 'growtype-wc'); ?></label>
            <select name="growtype_wc_trial_period" id="growtype_wc_trial_period">
                <?php foreach ($periods as $key => $period) { ?>
                    <option value="<?php echo $key ?>" <?php echo selected($key === $growtype_wc_trial_period) ?>><?php echo $period ?></option>
                <?php } ?>
            </select>
        </p>
        <p class="form-field">
            <?php $growtype_wc_trial_duration = growtype_wc_get_trial_duration($post->ID); ?>
            <label for="growtype-wc-trial-duration"><?php esc_html_e('Duration', 'growtype-wc'); ?></label>
            <input type="text" name="growtype_wc_trial_duration" id="growtype-wc-trial-duration" value="<?php echo $growtype_wc_trial_duration; ?>"/>
        </p>
        <?php do_action('growtype_wc_trial_tab_before_close', $post->ID); ?>
    </div>

    <script>
        if (!jQuery('#growtype_wc_trial').is(':checked')) {
            jQuery('.show_if_growtype_wc_trial').hide();
        }

        jQuery("#growtype_wc_trial").change(function () {
            if (this.checked) {
                jQuery('.show_if_growtype_wc_trial').show();
            } else {
                jQuery('.show_if_growtype_wc_trial').hide();
            }
        });
    </script>
    <?php
});


add_filter('woocommerce_product_filters', function ($output) {
    $insert_after_words = 'Virtual</option>';
    $position = strpos($output, $insert_after_words);
    $output = substr_replace($output, '<option value="growtype_wc_trial" >' . (is_rtl() ? '&larr;' : '&rarr;') . ' Trial</option>', $position + strlen($insert_after_words), 0);

    return $output;
}, 10, 1);


add_filter('parse_query', function ($query) {
    if (isset($_GET['product_type']) && $_GET['product_type'] === 'growtype_wc_trial') {
        $query->set('meta_query', array (
            array (
                'key' => Growtype_Wc_Trial::META_KEY,
                'value' => 'yes',
                'compare' => '='
            )
        ));
    }
}, 1);
