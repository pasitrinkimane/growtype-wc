<?php

add_filter('product_type_options', 'growtype_wc_product_type_options', 10, 1);
function growtype_wc_product_type_options($type_options)
{
    $type_options['growtype_wc_subscription'] = array (
        'id' => 'growtype_wc_subscription',
        'wrapper_class' => '',
        'label' => __('Subscription', 'woocommerce'),
        'description' => __('Subscription.', 'woocommerce'),
        'default' => 'no',
    );

    return $type_options;
}

/**
 * Update product meta
 */
add_action('woocommerce_update_product', function ($post_id, $product) {
    if (isset($_POST['post_type']) && $_POST['post_type'] === 'product') {
        $is_subscription = isset($_POST['growtype_wc_subscription']) && $_POST['growtype_wc_subscription'] ? 'yes' : 'no';
        update_post_meta($post_id, Growtype_Wc_Subscription::META_KEY, $is_subscription);
    }
}, 0, 2);

/**
 * Save product meta
 */
add_action("save_post_product", function ($post_id, $product, $update) {
    if (isset($_POST['growtype_wc_subscription_price'])) {
        update_post_meta($post_id, '_growtype_wc_subscription_price', esc_attr($_POST['growtype_wc_subscription_price']));
    }

    if (isset($_POST['growtype_wc_subscription_period'])) {
        update_post_meta($post_id, '_growtype_wc_subscription_period', esc_attr($_POST['growtype_wc_subscription_period']));
    }

    if (isset($_POST['growtype_wc_subscription_duration'])) {
        update_post_meta($post_id, '_growtype_wc_subscription_duration', esc_attr($_POST['growtype_wc_subscription_duration']));
    }

    update_post_meta($post_id, '_growtype_wc_subscription_preview_as_monthly', isset($_POST['growtype_wc_subscription_preview_as_monthly']) ? esc_attr($_POST['growtype_wc_subscription_preview_as_monthly']) : 0);
}, 10, 3);

/**
 * Product data tabs
 */
add_filter('woocommerce_product_data_tabs', 'growtype_wc_woocommerce_product_data_tabs', 10, 1);
function growtype_wc_woocommerce_product_data_tabs($default_tabs)
{
    global $post;

    $tabs = array (
        'wk_custom_tab' => array (
            'label' => esc_html__('Subscription settings', 'growtype-wc'),
            'target' => 'growtype_wc_subscription_tab',
            'priority' => 60,
            'class' => array ('show_if_growtype_wc_subscription'),
        ),
    );

    $default_tabs = array_merge($default_tabs, $tabs);

    return $default_tabs;
}

/**
 * Product data panels
 */
add_action('woocommerce_product_data_panels', 'growtype_wc_woocommerce_product_data_panels');
function growtype_wc_woocommerce_product_data_panels()
{
    global $woocommerce, $post;

    $periods = [
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
        'year' => 'Year',
    ];

    ?>
    <div id="growtype_wc_subscription_tab" class="panel woocommerce_options_panel">
        <p class="form-field">
            <?php $growtype_wc_subscription_price = get_post_meta($post->ID, '_growtype_wc_subscription_price', true); ?>
            <label for="growtype-wc-subscription-price"><?php esc_html_e('Price', 'growtype-wc'); ?></label>
            <input type="text" name="growtype_wc_subscription_price" id="growtype-wc-subscription-price" value="<?php echo $growtype_wc_subscription_price; ?>"/>
        </p>
        <p class="form-field">
            <?php $growtype_wc_subscription_period = growtype_wc_get_subcription_period($post->ID); ?>
            <label for="growtype-wc-subscription-period"><?php esc_html_e('Period', 'growtype-wc'); ?></label>
            <select name="growtype_wc_subscription_period" id="growtype_wc_subscription_period">
                <?php foreach ($periods as $key => $period) { ?>
                    <option value="<?php echo $key ?>" <?php echo selected($key === $growtype_wc_subscription_period) ?>><?php echo $period ?></option>
                <?php } ?>
            </select>
        </p>
        <p class="form-field">
            <?php $growtype_wc_subscription_duration = growtype_wc_get_subcription_duration($post->ID); ?>
            <label for="growtype-wc-subscription-duration"><?php esc_html_e('Duration', 'growtype-wc'); ?></label>
            <input type="text" name="growtype_wc_subscription_duration" id="growtype-wc-subscription-duration" value="<?php echo $growtype_wc_subscription_duration; ?>"/>
        </p>
        <p class="form-field">
            <?php $growtype_wc_subscription_preview_as_monthly = get_post_meta($post->ID, '_growtype_wc_subscription_preview_as_monthly', true); ?>
            <label for="growtype-wc-subscription-preview-as-monthly"><?php esc_html_e('Preview with monthly price', 'growtype-wc'); ?></label>
            <input type="checkbox" name="growtype_wc_subscription_preview_as_monthly" value="1" <?php echo checked(1, $growtype_wc_subscription_preview_as_monthly, false) ?> id="growtype-wc-subscription-preview-as-monthly"/>
        </p>
        <?php do_action('growtype_wc_subscription_tab_before_close', $post->ID); ?>
    </div>

    <script>
        if (!jQuery('#growtype_wc_subscription').is(':checked')) {
            jQuery('.show_if_growtype_wc_subscription').hide();
        }

        jQuery("#growtype_wc_subscription").change(function () {
            if (this.checked) {
                jQuery('.show_if_growtype_wc_subscription').show();
            } else {
                jQuery('.show_if_growtype_wc_subscription').hide();
            }
        });
    </script>
    <?php
}

add_filter('woocommerce_product_filters', 'growtype_wc_woocommerce_product_filters', 10, 1);
function growtype_wc_woocommerce_product_filters($output)
{
    $insert_after_words = 'Virtual</option>';
    $position = strpos($output, $insert_after_words);
    $output = substr_replace($output, '<option value="growtype_wc_subscription" >' . (is_rtl() ? '&larr;' : '&rarr;') . ' Subscription</option>', $position + strlen($insert_after_words), 0);

    return $output;
}

add_filter('parse_query', function ($query) {
    if (isset($_GET['product_type']) && $_GET['product_type'] === 'growtype_wc_subscription') {
        $query->set('meta_query', array (
            array (
                'key' => Growtype_Wc_Subscription::META_KEY,
                'value' => 'yes',
                'compare' => '='
            )
        ));
    }
}, 1);
