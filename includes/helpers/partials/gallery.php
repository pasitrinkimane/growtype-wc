<?php

/**
 * Product gallery sizes
 */
if (!function_exists('growtype_wc_get_product_gallery_sizes')) {
    function growtype_wc_get_product_gallery_sizes()
    {
        return [
            'thumbnail' => [
                'width' => 100,
                'height' => 100,
                'crop' => 1
            ]
        ];
    }
}
