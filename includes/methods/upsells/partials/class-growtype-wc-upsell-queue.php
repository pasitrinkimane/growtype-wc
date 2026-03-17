<?php

class Growtype_Wc_Upsell_Queue
{
    public static function init()
    {
        if (apply_filters('growtype_wc_upsell_modal_enabled', true)) {
            add_action('woocommerce_payment_complete', [self::class, 'queue_upsells_after_purchase'], 30, 2);
        }
    }

    public static function queue_upsells_after_purchase($order_id, $transaction_id = '')
    {
        $order = wc_get_order($order_id);

        if (!$order || !$order->is_paid()) {
            return;
        }

        $is_queue_allowed = apply_filters('growtype_wc_upsell_queue_allowed', true, $order);

        if (!$is_queue_allowed) {
            return;
        }

        self::queue_for_order($order);
    }
    public static function queue_for_order($order): array
    {
        if (!$order || !is_a($order, 'WC_Order') || !$order->is_paid()) {
            return [];
        }

        $user_id = (int)$order->get_user_id();

        if ($user_id < 1) {
            return [];
        }

        // A new successful purchase starts a fresh upsell cycle.
        delete_user_meta($user_id, Growtype_Wc_Upsell::DISMISSED_META_KEY);

        $queue_ids = self::build_eligible_product_ids($user_id);

        if (empty($queue_ids)) {
            delete_user_meta($user_id, Growtype_Wc_Upsell::QUEUE_META_KEY);
            return [];
        }

        update_user_meta($user_id, Growtype_Wc_Upsell::QUEUE_META_KEY, $queue_ids);

        return $queue_ids;
    }

    public static function get_validated_queue($user_id): array
    {
        static $cache = [];

        $user_id = (int)$user_id;

        if ($user_id < 1) {
            return [];
        }

        if (isset($cache[$user_id])) {
            return $cache[$user_id];
        }

        $stored_queue = get_user_meta($user_id, Growtype_Wc_Upsell::QUEUE_META_KEY, true);

        if (!is_array($stored_queue) || empty($stored_queue)) {
            return [];
        }

        $eligible_ids = self::build_eligible_product_ids($user_id);

        if (empty($eligible_ids)) {
            delete_user_meta($user_id, Growtype_Wc_Upsell::QUEUE_META_KEY);
            return [];
        }

        $eligible_lookup = array_fill_keys($eligible_ids, true);
        $validated_queue = [];

        foreach ($stored_queue as $product_id) {
            $product_id = (int)$product_id;

            if ($product_id > 0 && isset($eligible_lookup[$product_id])) {
                $validated_queue[] = $product_id;
            }
        }

        if (empty($validated_queue)) {
            delete_user_meta($user_id, Growtype_Wc_Upsell::QUEUE_META_KEY);
            return [];
        }

        $validated_queue = array_values(array_unique($validated_queue));

        if ($validated_queue !== array_values(array_map('intval', $stored_queue))) {
            update_user_meta($user_id, Growtype_Wc_Upsell::QUEUE_META_KEY, $validated_queue);
        }

        $cache[$user_id] = apply_filters('growtype_wc_upsell_queue', $validated_queue, $user_id);

        return $cache[$user_id];
    }

    public static function get_product_ids($user_id): array
    {
        return self::get_validated_queue($user_id);
    }

    public static function dismiss_product($user_id, $product_id): array
    {
        $user_id = (int)$user_id;
        $product_id = (int)$product_id;

        if ($user_id < 1 || $product_id < 1) {
            return [];
        }

        $dismissed_ids = self::get_dismissed_product_ids($user_id);

        if (!in_array($product_id, $dismissed_ids, true)) {
            $dismissed_ids[] = $product_id;
            update_user_meta(
                $user_id,
                Growtype_Wc_Upsell::DISMISSED_META_KEY,
                array_values(array_unique(array_map('intval', $dismissed_ids)))
            );
        }

        $queue_ids = array_values(array_filter(self::get_validated_queue($user_id), function ($queued_product_id) use ($product_id) {
            return (int)$queued_product_id !== (int)$product_id;
        }));

        if (empty($queue_ids)) {
            delete_user_meta($user_id, Growtype_Wc_Upsell::QUEUE_META_KEY);
        } else {
            update_user_meta($user_id, Growtype_Wc_Upsell::QUEUE_META_KEY, $queue_ids);
        }

        return $queue_ids;
    }

    public static function user_has_purchased_product($user_id, $product_id): bool
    {
        $purchased_lookup = array_fill_keys(self::get_purchased_product_ids($user_id), true);

        return isset($purchased_lookup[(int)$product_id]);
    }

    public static function get_dismissed_product_ids($user_id): array
    {
        $dismissed_ids = get_user_meta((int)$user_id, Growtype_Wc_Upsell::DISMISSED_META_KEY, true);

        if (!is_array($dismissed_ids) || empty($dismissed_ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $dismissed_ids))));
    }

    private static function build_eligible_product_ids($user_id): array
    {
        $user_id = (int)$user_id;

        if ($user_id < 1) {
            return [];
        }

        $dismissed_lookup = array_fill_keys(self::get_dismissed_product_ids($user_id), true);
        $purchased_lookup = array_fill_keys(self::get_purchased_product_ids($user_id), true);
        $queue_ids = [];

        foreach (Growtype_Wc_Upsell_Catalog::get_products() as $product) {
            $product_id = (int)$product->get_id();

            if ($product_id < 1) {
                continue;
            }

            if (isset($dismissed_lookup[$product_id]) || isset($purchased_lookup[$product_id])) {
                continue;
            }

            $queue_ids[] = $product_id;
        }

        return array_values(array_unique($queue_ids));
    }

    private static function get_purchased_product_ids($user_id): array
    {
        static $cache = [];

        $user_id = (int)$user_id;

        if ($user_id < 1) {
            return [];
        }

        if (isset($cache[$user_id])) {
            return $cache[$user_id];
        }

        $orders = wc_get_orders([
            'customer' => $user_id,
            'limit' => -1,
            'status' => wc_get_is_paid_statuses(),
            'return' => 'ids',
        ]);

        if (empty($orders)) {
            $cache[$user_id] = [];
            return $cache[$user_id];
        }

        $product_ids = [];

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);

            if (!$order) {
                continue;
            }

            foreach ($order->get_items() as $item) {
                $product_id = (int)$item->get_product_id();

                if ($product_id > 0) {
                    $product_ids[] = $product_id;
                }
            }
        }

        $cache[$user_id] = array_values(array_unique($product_ids));

        return $cache[$user_id];
    }

    public static function has_order_purchased_product($order_id, $product_id)
    {
        $orders_to_check = [$order_id];

        $child_orders = wc_get_orders([
            'limit' => -1,
            'meta_key' => 'parent_order_id',
            'meta_value' => $order_id,
            'return' => 'ids',
            'status' => wc_get_is_paid_statuses(),
        ]);

        if ($child_orders) {
            $orders_to_check = array_merge($orders_to_check, $child_orders);
        }

        foreach ($orders_to_check as $oid) {
            $order = wc_get_order($oid);

            if (!$order) {
                continue;
            }

            foreach ($order->get_items() as $item) {
                if ((int)$item->get_product_id() === (int)$product_id) {
                    return true;
                }
            }
        }

        return false;
    }
}
