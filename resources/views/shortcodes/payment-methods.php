<div class="b-payment-methods">
    <?php foreach ($items as $item) { ?>
        <div class="b-payment-methods-single <?php echo isset($item['class']) ? $item['class'] : ''; ?>">
            <img src="<?php echo $item['img']; ?>" alt="">
        </div>
    <?php } ?>
</div>
