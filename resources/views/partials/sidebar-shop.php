<?php if (Growtype_Wc_Product::display_shop_catalog_sidebar() && is_dynamic_sidebar('sidebar-shop')) { ?>
    <aside id="sidebar-shop" class="sidebar sidebar-shop widget-area">
        <div class="widget-header">
            <h3 class="e-title"><?php echo __('Filter', 'growtype-wc') ?></h3>
            <a href="<?= get_permalink(wc_get_page_id('shop')) ?>" class="btn btn-secondary btn-clear-all"><?php echo __('Clear all', 'growtype-wc') ?></a>
        </div>
        <?php dynamic_sidebar('sidebar-shop'); ?>
    </aside>
<?php } ?>
