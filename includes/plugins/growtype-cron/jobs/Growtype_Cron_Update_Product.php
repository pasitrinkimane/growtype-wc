<?php

class Growtype_Cron_Update_Product
{
    public function run($job_payload)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '100');
        set_time_limit(100);

        return apply_filters('growtype_wc_update_products_data', $job_payload['data']);
    }
}
