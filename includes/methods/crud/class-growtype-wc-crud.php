<?php

class Growtype_Wc_Crud
{
    public function __construct()
    {

    }

    public function generate_products()
    {
        $products_details = [
            [
                'post_title' => 'Test - ' . uniqid(),
                'post_content' => 'Post_content - ' . uniqid(),
                'sku' => uniqid(),
                'image_id' => 968,
                'gallery_image_ids' => [961, 960],
                'default_attributes' => [
                    'pa_orientation' => 'vertical',
                    'pa_size' => '12x18',
                    'pa_frame' => 'no-frame',
                ],
                'taxonomies' => [
                    'pa_orientation' => [
                        'values' => '',
                        'is_radio_select' => '0',
                        'is_visible' => '0'
                    ],
                    'pa_frame' => [
                        'values' => '',
                        'is_radio_select' => '1',
                        'is_label_hidden' => '0',
                    ],
                    'pa_size' => [
                        'values' => ''
                    ]
                ],
                'variations' => [
                    [
                        'regular_price' => '20',
                        'price' => '10',
                        'sale_price' => '10',
                        'stock_qty' => '10',
                        'stock_status' => 'instock',
                        'variation_description' => 'Description' . uniqid(),
                        'sku' => uniqid(),
                        'image_id' => 968,
                        'custom_variables' => [
                            'attribute_pa_orientation' => 'vertical',
                            'attribute_pa_frame' => 'black',
                            'attribute_pa_size' => '12x18',
                        ]
                    ],
                    [
                        'regular_price' => '20',
                        'price' => '10',
                        'sale_price' => '10',
                        'stock_qty' => '10',
                        'stock_status' => 'instock',
                        'variation_description' => 'Description' . uniqid(),
                        'attribute_pa_size' => uniqid(),
                        'sku' => uniqid(),
                        'image_id' => 968,
                        'custom_variables' => [
                            'attribute_pa_orientation' => 'vertical',
                            'attribute_pa_frame' => 'no-frame',
                            'attribute_pa_size' => '18x24',
                        ]
                    ],
                ]
            ]
        ];

        $growtype_wc_product = new Growtype_Wc_Product();

        foreach ($products_details as $details) {
            $growtype_wc_product->create($details);
        }
    }
}
