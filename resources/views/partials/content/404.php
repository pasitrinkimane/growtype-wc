<div class="general-404">
    <div class="img-wrapper">
        <img src="<?php echo growtype_get_parent_theme_public_path() . '/images/404/content.png' ?>" alt="" class="img-fluid">
    </div>
    <p class="e-title"><?php echo isset($title) ? $title : __('No content found', 'growtype-wc') ?></p>
    <p><?php echo isset($subtitle) ? $subtitle : __('Unfortunately no content was found', 'growtype-wc') ?></p>
    <?php if (isset($cta) && !empty($cta)) { ?>
        <?php echo $cta ?>
    <?php } ?>
</div>
