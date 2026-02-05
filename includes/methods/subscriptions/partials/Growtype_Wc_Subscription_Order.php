<?php

class Growtype_Wc_Subscription_Order extends WC_Order
{
    const META_FIELDS = [
        'subscription' => [
            [
                'type' => 'select',
                'key' => '_status',
                'name' => 'status',
                'label' => 'Status:',
                'options' => [],
            ],
            [
                'key' => '_period',
                'name' => 'period',
                'label' => 'Period:'
            ],
            [
                'key' => '_duration',
                'name' => 'duration',
                'label' => 'Duration:'
            ],
            [
                'key' => '_price',
                'name' => 'price',
                'label' => 'Price:'
            ],
            [
                'key' => '_start_date',
                'name' => 'start_date',
                'label' => 'Start date:'
            ],
            [
                'key' => '_end_date',
                'name' => 'end_date',
                'label' => 'End date:'
            ]
        ],
        'user' => [
            [
                'key' => '_user_id',
                'name' => 'user_id',
                'label' => 'User id:',
            ]
        ],
        'order' => [
            [
                'key' => '_order_id',
                'name' => 'order_id',
                'label' => 'Order id:',
            ]
        ]
    ];

    const SUBSCRIPTION_DATA_KEYS = array (
        '_billing_period' => 'billing_period',
        '_billing_interval' => 'billing_interval',
        '_suspension_count' => 'suspension_count',
        '_cancelled_email_sent' => 'cancelled_email_sent',
        '_requires_manual_renewal' => 'requires_manual_renewal',
        '_trial_period' => 'trial_period',

        '_schedule_trial_end' => 'schedule_trial_end',
        '_schedule_next_payment' => 'schedule_next_payment',
        '_schedule_cancelled' => 'schedule_cancelled',
        '_schedule_end' => 'schedule_end',
        '_schedule_payment_retry' => 'schedule_payment_retry',
        '_schedule_start' => 'schedule_start',

        '_subscription_switch_data' => 'switch_data',

        '_billing_price' => 'billing_price',
        '_product_id' => 'product_id',
        '_title' => 'title',
    );

    public function __construct()
    {
        add_action('add_meta_boxes_growtype_wc_subs', array ($this, 'growtype_wc_add_meta_boxes_growtype_wc_subs'));
        add_action('save_post_growtype_wc_subs', array ($this, 'growtype_wc_save_post_growtype_wc_subs'));
        add_filter('manage_growtype_wc_subs_posts_columns', array ($this, 'manage_columns'));
        add_action('manage_growtype_wc_subs_posts_custom_column', array ($this, 'fill_columns'), 10, 2);

        if (is_admin()) {
            add_action('load-post.php', array ($this, 'init_metabox'));
        }

        $this->set_meta_keys_to_props();
    }

    public function __set($key, $value)
    {
        $this->$key = $value;
    }

    public function set_title($value)
    {
        $this->set_prop('title', $value);
    }

    public function set_billing_period($value)
    {
        $this->set_prop('billing_period', $value);
    }

    public function set_billing_interval($value)
    {
        $this->set_prop('billing_interval', (string)absint($value));
    }

    public function set_start_date($value)
    {
        $this->set_prop('schedule_start', $value);
    }

    public function set_billing_price($value)
    {
        $this->set_prop('billing_price', $value);
    }

    public function set_product_id($value)
    {
        $this->set_prop('product_id', $value);
    }

    public static function get_meta_fields()
    {
        $meta_fields = self::META_FIELDS;
        $meta_fields['subscription'][0]['options'] = growtype_wc_get_subscription_statuses();

        return $meta_fields;
    }

    public function init_metabox()
    {
        add_action('add_meta_boxes', array ($this, 'add_metabox'));
        add_action('save_post', array ($this, 'save_metabox'), 10, 2);
    }

    public function add_metabox()
    {
        add_meta_box(
            'actions-meta-box',
            __('Actions', 'growtype-wc'),
            array ($this, 'render_metabox'),
            'growtype_wc_subs',
            'side',
            'default'
        );
    }

    public function render_metabox($post)
    {
        wp_nonce_field('custom_nonce_action', 'custom_nonce');
        ?>
        <button class="button button-primary button-large" name="trigger_action" value="subscription_charge" type="submit">Charge subscription</button>
        <?php
    }

    public function save_metabox($post_id, $post)
    {
        $nonce_name = isset($_POST['custom_nonce']) ? $_POST['custom_nonce'] : '';
        $nonce_action = 'custom_nonce_action';

        if (!wp_verify_nonce($nonce_name, $nonce_action)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (wp_is_post_autosave($post_id)) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        $trigger_action = isset($_POST['trigger_action']) ? $_POST['trigger_action'] : '';

        if (!empty($trigger_action)) {
            $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : '';

            if (!empty($order_id)) {
                $renewal_order = wc_get_order($order_id);
                do_action('woocommerce_scheduled_subscription_payment_' . $renewal_order->get_payment_method(), $renewal_order->get_total(), $renewal_order);
            }
        }
    }

    public function get_data_key($key)
    {
        return $this->data[$key];
    }

    public function set_meta_keys_to_props()
    {
        foreach (self::SUBSCRIPTION_DATA_KEYS as $meta_key => $prop) {
            $this->data[$prop] = get_post_meta($this->id, $meta_key, true);
        }
    }

    public function set_date_created($date = null)
    {
        if (!is_null($date)) {
            $datetime_string = growtype_wc_sub_get_datetime_utc_string(growtype_wc_sub_get_datetime_from($date));

            $this->set_prop('date_created', $datetime_string);
        }
    }

    function manage_columns($columns)
    {
        foreach (self::get_meta_fields() as $meta_fields) {
            foreach ($meta_fields as $meta_field) {
                $columns[$meta_field['key']] = $meta_field['label'];
            }
        }

        return $columns;
    }

    function fill_columns($column, $post_id)
    {
        foreach (self::get_meta_fields() as $meta_fields) {
            foreach ($meta_fields as $meta_field) {
                $columns[$meta_field['key']] = $meta_field['label'];

                if ($meta_field['key'] === $column) {
                    $value = get_post_meta($post_id, $meta_field['key'], true);

                    if ($column === '_status') {
                        echo '<span style="background: ' . ($value === 'active' ? 'green' : 'red') . ';padding: 5px;color: white;border-radius: 5px;text-transform: uppercase;">' . $value . '</span>';
                    } elseif ($column === '_user_id') {
                        if (!empty(get_user_by('id', $value))) {
                            echo $value . ' - (' . get_user_by('id', $value)->user_email . ')';
                        } else {
                            echo $value;
                        }
                    } else {
                        echo $value;
                    }
                }
            }
        }
    }

    function growtype_wc_save_post_growtype_wc_subs()
    {
        if (empty($_POST)) {
            return;
        }

        global $post;

        $meta_fields_groups = self::get_meta_fields();

        foreach ($meta_fields_groups as $meta_fields_group) {
            foreach ($meta_fields_group as $meta_fields) {
                if (isset($_POST[$meta_fields['name']])) {
                    update_post_meta($post->ID, $meta_fields['key'], $_POST[$meta_fields['name']]);
                }
            }
        }

        $user_id = get_post_meta($post->ID, '_user_id', true);
        if ($user_id) {
            delete_transient('growtype_wc_user_has_active_sub_' . $user_id);
        }
    }

    function growtype_wc_add_meta_boxes_growtype_wc_subs()
    {
        $meta_fields = self::get_meta_fields();

        add_meta_box('subscription', __('Subscription', 'growtype-wc'), array ($this, 'growtype_wc_subs_metabox_html'), 'growtype_wc_subs', 'normal', 'high', $meta_fields['subscription']);
        add_meta_box('user', __('User', 'growtype-wc'), array ($this, 'growtype_wc_subs_metabox_html'), 'growtype_wc_subs', 'normal', 'high', $meta_fields['user']);
        add_meta_box('order', __('Order', 'growtype-wc'), array ($this, 'growtype_wc_subs_metabox_html'), 'growtype_wc_subs', 'normal', 'high', $meta_fields['order']);
    }

    function growtype_wc_subs_metabox_html($post, $params)
    {
        global $post;

        $args = $params['args'];

        foreach ($args as $arg) {
            $current_value = get_post_meta($post->ID, $arg['key'], true);
            ?>
            <div style="margin-bottom: 10px;">
                <label><?php echo $arg['label'] ?></label>
                <?php if (isset($arg['type']) && $arg['type'] === 'select') { ?>
                    <select name="<?php echo $arg['name'] ?>">
                        <?php
                        if (isset($arg['options'])) {
                            foreach ($arg['options'] as $key => $label) { ?>
                                <option value="<?php echo $key ?>" <?php echo selected($key, $current_value) ?>><?php echo $label ?></option>
                            <?php }
                        }
                        ?>
                    </select>
                <?php } else { ?>
                    <input type="text" class="regular-text" name="<?php echo $arg['name'] ?>" value="<?php echo $current_value; ?>">
                <?php } ?>
            </div>
            <?php if ($arg['name'] === 'user_id') { ?>
                <a href="<?php echo get_edit_user_link($current_value) ?>" target="_blank">Profile link</a>
            <?php } ?>
            <?php if ($arg['name'] === 'order_id') { ?>
                <a href="<?php echo get_edit_post_link($current_value) ?>" target="_blank">Order link</a>
            <?php } ?>
        <?php } ?>

        <?php
    }
}
