<?php

add_filter('growtype_cron_load_jobs', 'growtype_wc_growtype_cron_load_jobs');
function growtype_wc_growtype_cron_load_jobs($jobs)
{
    $jobs = array_merge($jobs, [
        'growtype-wc-generate-product' => [
            'classname' => 'Growtype_Cron_Generate_Product',
            'path' => GROWTYPE_WC_PATH . '/includes/plugins/growtype-cron/jobs/Growtype_Cron_Generate_Product.php',
        ],
        'growtype-wc-update-product' => [
            'classname' => 'Growtype_Cron_Update_Product',
            'path' => GROWTYPE_WC_PATH . '/includes/plugins/growtype-cron/jobs/Growtype_Cron_Update_Product.php',
        ],
    ]);

    return $jobs;
}
