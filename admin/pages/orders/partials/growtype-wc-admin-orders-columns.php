<?php

class Growtype_Wc_Admin_Orders_Columns
{
    public function __construct()
    {
        /**
         * Add columns to WooCommerce Orders list (supports HPOS)
         */
        add_filter('woocommerce_shop_order_list_table_columns', [$this, 'add_orders_columns'], 20);

        /**
         * Render the custom column values
         */
        add_action('woocommerce_shop_order_list_table_custom_column', [$this, 'render_orders_column_values'], 10, 2);
    }

    public function add_orders_columns($columns)
    {
        $new = [];

        foreach ($columns as $key => $label) {
            $new[$key] = $label;

            if ($key === 'order_number') {
                $new['order_email'] = __('Email', 'growtype-wc');
            }

            if ($key === 'actions') {
                $new['order_coupons'] = __('Coupons Used', 'growtype-wc');
            }
        }

        if (!isset($new['order_email'])) {
            $new['order_email'] = __('Email', 'growtype-wc');
        }

        if (!isset($new['order_coupons'])) {
            $new['order_coupons'] = __('Coupons Used', 'growtype-wc');
        }

        return $new;
    }

    public function render_orders_column_values($column, $order)
    {
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        if ($column === 'order_email') {
            $email = $order->get_billing_email();
            if ($email) {
                echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
            } else {
                echo '<span style="color:#999;">—</span>';
            }
        }

        if ($column === 'order_coupons') {
            $codes = $order->get_coupon_codes();
            if (!empty($codes)) {
                echo '<span style="color:#0073aa;">' . esc_html(implode(', ', $codes)) . '</span>';
            } else {
                echo '<span style="color:#999;">—</span>';
            }
        }
    }
}
