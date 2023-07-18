<?php

class Growtype_Wc_Crud
{
    public function generate_products()
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '100');
        set_time_limit(100);

        error_log('Generate products initiated - ' . date('Y-m-d H:i:s'), 0);

        $products_data = apply_filters('growtype_wc_generate_products_data', []);

        $growtype_wc_product = new Growtype_Wc_Product();

        $delay = 3;
        foreach ($products_data as $data) {
            if (class_exists('Growtype_Cron_Jobs')) {
                delete_transient('growtype_wc_creating_products');

                Growtype_Cron_Jobs::create('growtype-wc-generate-product', json_encode([
                    'data' => $data
                ]), $delay);
            } else {
                $growtype_wc_product->create($data);
            }

            $delay += 3;
        }
    }

    public function update_products($ids = [])
    {
        if (class_exists('Growtype_Cron_Jobs')) {
            Growtype_Cron_Jobs::create('growtype-wc-update-product', json_encode([
                'data' => $ids
            ]));
        } else {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '100');
            set_time_limit(100);

            apply_filters('growtype_wc_update_products_data', $ids);
        }
    }
}
