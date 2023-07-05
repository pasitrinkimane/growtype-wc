<div class="table">
    <?php
    $params = apply_filters('growtype_wc_product_table_params', (isset($params) ? $params : []));

    echo growtype_wc_include_view('woocommerce.components.table.product-table-head'); ?>

    <div class="table-body">
        <?php
        if (!isset($products)) {
            global $wp_query;
            $products = $wp_query;
        }

        while ($products->have_posts()) : $products->the_post();
            /**
             * Hook: woocommerce_shop_loop.
             */
            do_action('woocommerce_shop_loop');

            echo growtype_wc_include_view('woocommerce.components.table.product-table-row', [
                'product' => wc_get_product(),
                'params' => apply_filters('growtype_wc_product_table_row_params', $params, get_the_ID()),
            ]);
        endwhile; ?>

    </div>
</div>
