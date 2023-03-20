<?php if (Growtype_Wc_Product::display_shop_catalog_sidebar() && is_dynamic_sidebar('sidebar-shop')) { ?>
    <aside id="sidebar-shop" class="sidebar sidebar-shop widget-area">
        <?php dynamic_sidebar('sidebar-shop'); ?>
    </aside>
<?php } ?>
