<?php

/**
 * Catelog product select filter
 */
add_action('wp_ajax_widget_meta', 'growtype_wc_widget_meta');
add_action('wp_ajax_nopriv_widget_meta', 'growtype_wc_widget_meta');
function growtype_wc_widget_meta()
{
    $values = isset($_REQUEST['values']) ? $_REQUEST['values'] : [];

    if (empty($values)) {
        return wp_send_json([]);
    }

    $filter_params = [
        'orderby' => isset($_POST['orderby']) ? $_POST['orderby'] : 'menu_order title',
        'categories_ids' => isset($_POST['categories_ids']) ? $_POST['categories_ids'] : [],
        'products_group' => isset($_POST['products_group']) ? $_POST['products_group'] : [],
        'min_price' => isset($_POST['min_price']) ? $_POST['min_price'] : [],
        'max_price' => isset($_POST['max_price']) ? $_POST['max_price'] : [],
        'base' => isset($_POST['base']) ? $_POST['base'] : '',
        'page_nr' => isset($_POST['page_nr']) ? $_POST['page_nr'] : '',
    ];

    $products = growtype_wc_get_filtered_products($filter_params);

    if ($products->have_posts()) {
        /**
         * Products
         */
        ob_start();

        if (Growtype_Wc_Product::catalog_default_preview_style() === 'table') {
            $params = [
                'watchlist_btn' => isset($_POST['products_group']) && ($_POST['products_group'] === 'watchlist' || $_POST['products_group'] === 'default'),
                'edit_btn' => isset($_POST['products_group']) && $_POST['products_group'] === 'user_uploaded',
            ];

            echo growtype_wc_include_view('woocommerce.components.table.product-table', [
                'products' => $products,
                'params' => $params
            ]);
        } else {
            while ($products->have_posts()) : $products->the_post();
                wc_get_template_part('content', 'product');
            endwhile;
        }

        wp_reset_postdata();

        $rendered_products = ob_get_clean();

        /**
         * Pagination
         */

        ob_start();

        $args = array (
            'total' => $products->max_num_pages,
            'current' => $filter_params['page_nr'],
            'base' => home_url() . '/auctions/' . '%_%?orderby=' . $_POST['orderby'],
            'format' => 'page/%#%',
        );

        echo wc_get_template('loop/pagination.php', $args);

        $pagination = ob_get_clean();

    } else {
        ob_start();

        do_action('woocommerce_no_products_found');

        $rendered_products = ob_get_clean();
    }

    $data = [
        'products' => $rendered_products,
        'pagination' => isset($pagination) ? $pagination : '',
    ];

    return wp_send_json($data);
}
