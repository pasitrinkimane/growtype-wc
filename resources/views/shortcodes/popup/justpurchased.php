<div class="popup popup-justpurchased">
    <div class="popup-inner">
        <?php if ($image_url) : ?>
            <div class="b-image">
                <img src="<?php echo $image_url ?>" alt="" class="img-fluid">
            </div>
        <?php endif; ?>
        <div class="b-content">
            <div class="b-content-main">
                <?php echo $content_main ?>
            </div>
            <div class="b-content-bottom">
                Just Now
            </div>
        </div>
    </div>
</div>
