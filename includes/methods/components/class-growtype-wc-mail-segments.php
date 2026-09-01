<?php

class Growtype_Wc_Mail_Segments
{
    private $get_data_callback;

    private $render_cell_callback;

    public function __construct()
    {
        add_filter('growtype_mail_ajax_table_config_segment_members', [$this, 'extend_table'], 20);
    }

    public function extend_table(array $config): array
    {
        $this->get_data_callback = $config['get_data'] ?? null;
        $this->render_cell_callback = $config['render_cell'] ?? null;

        $columns = [];
        foreach ($config['columns'] ?? [] as $key => $column) {
            $columns[$key] = $column;

            if ($key === 'sent') {
                $columns['wc_total_spent'] = [
                    'label' => 'Total Spent',
                    'style' => 'width: 110px;',
                ];
            }
        }

        $config['columns'] = $columns;
        $config['get_data'] = [$this, 'get_data'];
        $config['render_cell'] = [$this, 'render_cell'];

        return $config;
    }

    public function get_data($page, $per_page): array
    {
        if (!is_callable($this->get_data_callback)) {
            return [[], 0];
        }

        $result = call_user_func($this->get_data_callback, $page, $per_page);
        if (!is_array($result) || empty($result[0]) || !is_array($result[0])) {
            return $result;
        }

        $user_ids = array_values(array_unique(array_filter(array_map(static function ($item) {
            return (int) ($item['user_id'] ?? 0);
        }, $result[0]))));

        $totals = $this->get_total_spent_by_user($user_ids);

        foreach ($result[0] as &$item) {
            $user_id = (int) ($item['user_id'] ?? 0);
            $item['_wc_total_spent'] = $totals[$user_id] ?? 0;
        }
        unset($item);

        return $result;
    }

    private function get_total_spent_by_user(array $user_ids): array
    {
        if (empty($user_ids) || !function_exists('wc_get_is_paid_statuses')) {
            return [];
        }

        global $wpdb;

        $statuses = array_map(static function ($status) {
            return 'wc-' . $status;
        }, wc_get_is_paid_statuses());

        if (empty($statuses)) {
            return [];
        }

        $user_placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $query_args = array_merge($user_ids, $statuses);

        if (
            class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
            && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
        ) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT customer_id AS user_id, SUM(total_amount) AS total_spent
                 FROM {$wpdb->prefix}wc_orders
                 WHERE customer_id IN ({$user_placeholders})
                   AND status IN ({$status_placeholders})
                 GROUP BY customer_id",
                ...$query_args
            ), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT customer.meta_value AS user_id, SUM(order_total.meta_value) AS total_spent
                 FROM {$wpdb->posts} orders
                 INNER JOIN {$wpdb->postmeta} customer
                    ON customer.post_id = orders.ID
                   AND customer.meta_key = '_customer_user'
                 INNER JOIN {$wpdb->postmeta} order_total
                    ON order_total.post_id = orders.ID
                   AND order_total.meta_key = '_order_total'
                 WHERE orders.post_type = 'shop_order'
                   AND customer.meta_value IN ({$user_placeholders})
                   AND orders.post_status IN ({$status_placeholders})
                 GROUP BY customer.meta_value",
                ...$query_args
            ), ARRAY_A);
        }

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['user_id']] = (float) $row['total_spent'];
        }

        return $totals;
    }

    public function render_cell($item, $key, $context): string
    {
        if ($key === 'wc_total_spent') {
            if (empty($item['user_id'])) {
                return '&mdash;';
            }

            $total = (float) ($item['_wc_total_spent'] ?? 0);

            return function_exists('wc_price')
                ? wc_price($total)
                : number_format($total, 2);
        }

        if (!is_callable($this->render_cell_callback)) {
            return '';
        }

        return (string) call_user_func($this->render_cell_callback, $item, $key, $context);
    }
}
