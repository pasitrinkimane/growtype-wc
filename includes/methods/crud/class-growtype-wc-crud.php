<?php

class Growtype_Wc_Crud
{
    public function generate_products()
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '100');
        set_time_limit(100);

        $products_data = apply_filters('growtype_wc_generate_products_data', []);

        $growtype_wc_product = new Growtype_Wc_Product();

        foreach ($products_data as $data) {
            $growtype_wc_product->create($data);
        }
    }
}
