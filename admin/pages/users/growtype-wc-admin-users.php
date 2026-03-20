<?php

class Growtype_Wc_Admin_Users
{
    public function __construct()
    {
        add_action('restrict_manage_users', [$this, 'add_order_status_filter'], 10, 1);
        add_action('pre_user_query', [$this, 'user_query_where']);
    }

    public function add_order_status_filter($which)
    {
        // Usually restrict_manage_users in users.php only passes 'top' and 'bottom'
        if ($which !== 'top') {
            return;
        }

        if (!function_exists('wc_get_order_statuses')) {
            return;
        }

        $statuses = wc_get_order_statuses();
        $selected = isset($_GET['gt_wc_order_status']) ? sanitize_text_field(wp_unslash($_GET['gt_wc_order_status'])) : '';
        ?>
        <div style="margin-left:15px;float:right;">
            <select name="gt_wc_order_status" style="float:none; margin-right: 5px;">
                <option value=""><?php _e('Filter by Order Status', 'growtype-wc'); ?></option>
                <option value="any_paid" <?php selected($selected, 'any_paid'); ?>><?php _e('Any Paid', 'growtype-wc'); ?></option>
                <?php foreach ($statuses as $status => $label) : ?>
                    <option value="<?php echo esc_attr($status); ?>" <?php selected($selected, $status); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php submit_button(__('Filter', 'growtype-wc'), 'secondary', 'gt_wc_filter_users_submit', false, ['id' => 'gt-wc-filter-users']); ?>
        </div>
        <?php
    }

    public function user_query_where($query)
    {
        global $pagenow, $wpdb;

        if (is_admin() && $pagenow === 'users.php' && !empty($_GET['gt_wc_order_status'])) {
            $status = sanitize_text_field(wp_unslash($_GET['gt_wc_order_status']));
            $paid_statuses = array ('wc-processing', 'wc-completed');

            if ($status === 'any_paid') {
                $status_sql = "IN ('" . implode("', '", $paid_statuses) . "')";
            } else {
                // Keep the wc- prefix if passed, else fallback
                $status_sql = "= '" . esc_sql($status) . "'";
            }

            $query->query_where .= " AND {$wpdb->users}.ID IN (
                SELECT pm.meta_value FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '_customer_user'
                AND p.post_type = 'shop_order'
                AND p.post_status {$status_sql}
            )";
        }
    }
}
