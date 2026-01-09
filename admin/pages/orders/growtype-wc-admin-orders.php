<?php

class Growtype_Wc_Admin_Orders
{
    public function __construct()
    {
        /**
         * Add "Coupons Used" column to WooCommerce Orders list (supports HPOS)
         */
        add_filter('woocommerce_shop_order_list_table_columns', function ($columns) {
            $new = [];

            foreach ($columns as $key => $label) {
                $new[$key] = $label;

                if ($key === 'actions') {
                    $new['order_coupons'] = __('Coupons Used', 'growtype-wc');
                }
            }

            if (!isset($new['order_coupons'])) {
                $new['order_coupons'] = __('Coupons Used', 'growtype-wc');
            }

            return $new;
        }, 20);

        /**
         * Render the custom column value
         */
        add_action('woocommerce_shop_order_list_table_custom_column', function ($column, $order) {
            if ($column !== 'order_coupons') {
                return;
            }

            if (!is_a($order, 'WC_Order')) {
                echo '—';
                return;
            }

            $codes = $order->get_coupon_codes();

            if (!empty($codes)) {
                echo '<span style="color:#0073aa;">' . esc_html(implode(', ', $codes)) . '</span>';
            } else {
                echo '<span style="color:#999;">—</span>';
            }
        }, 10, 2);
    }
}
