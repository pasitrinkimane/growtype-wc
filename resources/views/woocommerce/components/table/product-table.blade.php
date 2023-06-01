<div class="table">

    <?php
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
        ]);
    endwhile; ?>

    </div>
</div>
