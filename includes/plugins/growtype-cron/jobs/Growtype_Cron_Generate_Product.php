<?php

class Growtype_Cron_Generate_Product
{
    public function run($job_payload)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '100');
        set_time_limit(100);

        if ($this->manage_product_data_in_generating_cache($job_payload['data'], 'exists')) {
            error_log("Same product is already generating");
            throw new Exception('Same product is already generating. Details: ' . json_encode($job_payload['data']));
        }

        $this->manage_product_data_in_generating_cache($job_payload['data'], 'add');

        error_log("Started creating product: " . json_encode($job_payload['data']));

        $growtype_wc_product = new Growtype_Wc_Product();
        $growtype_wc_product->create($job_payload['data']);

        /**
         * Remove product data from generating cache
         */
        $this->manage_product_data_in_generating_cache($job_payload['data'], 'delete');
    }

    function manage_product_data_in_generating_cache($details, $status)
    {
        $growtype_wc_creating_products = get_transient('growtype_wc_creating_products');
        $growtype_wc_creating_products = !empty($growtype_wc_creating_products) ? json_decode($growtype_wc_creating_products) : [];
        $formatted_details = base64_encode(json_encode($details));
        $formatted_details = substr($formatted_details, 0, 500);

        switch ($status) {
            case 'exists':
                if (!empty($growtype_wc_creating_products) && in_array($formatted_details, $growtype_wc_creating_products)) {
                    return true;
                }

                return false;
            case 'add':
                if (!in_array($formatted_details, $growtype_wc_creating_products)) {
                    array_push($growtype_wc_creating_products, $formatted_details);
                }

                set_transient('growtype_wc_creating_products', json_encode($growtype_wc_creating_products), WEEK_IN_SECONDS);
                break;
            case 'delete':
                foreach ($growtype_wc_creating_products as $key => $product_data) {
                    if ($product_data == $formatted_details) {
                        unset($growtype_wc_creating_products[$key]);
                    }
                }

                set_transient('growtype_wc_creating_products', json_encode($growtype_wc_creating_products), WEEK_IN_SECONDS);
                break;
        }
    }
}
