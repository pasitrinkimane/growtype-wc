<?php

class Growtype_Wc_Upsell_Catalog
{
    public static function init()
    {
    }

    public static function get_products(): array
    {
        static $products = null;

        if ($products !== null) {
            return $products;
        }

        $queried_products = wc_get_products([
            'limit' => -1,
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_key' => '_growtype_wc_upsell_position',
            'meta_query' => [
                [
                    'key' => '_growtype_wc_upsell',
                    'value' => 'yes',
                    'compare' => '=',
                ],
            ],
        ]);

        $products = array_values(array_filter($queried_products, function ($product) {
            $value = get_post_meta($product->get_id(), Growtype_Wc_Upsell::META_KEY, true);

            return is_string($value) && trim(strtolower($value)) === 'yes';
        }));

        return $products;
    }

    public static function get_product_by_slug(string $slug)
    {
        if (empty($slug)) {
            return null;
        }

        foreach (self::get_products() as $product) {
            if ($product->get_slug() === $slug) {
                return $product;
            }
        }

        return null;
    }

    public static function get_slugs(): array
    {
        return array_map(function ($product) {
            return $product->get_slug();
        }, self::get_products());
    }

    public static function get_upsell_definitions(): array
    {
        return array_map(function ($product) {
            return [
                'slug' => $product->get_slug(),
            ];
        }, self::get_products());
    }

    public static function get($slug = null)
    {
        $upsells = apply_filters('growtype_wc_get_upsells', self::get_upsell_definitions());

        if (empty($upsells)) {
            return $slug ? null : [];
        }

        if ($slug) {
            foreach ($upsells as $upsell) {
                if (($upsell['slug'] ?? '') === $slug) {
                    return $upsell;
                }
            }

            return null;
        }

        return $upsells;
    }
}
