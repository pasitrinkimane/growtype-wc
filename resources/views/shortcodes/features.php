<div class="b-features">
    <?php foreach ($items as $item) { ?>
        <div class="b-features-single">
            <i class="icon <?php echo $item['icon']; ?>"></i>
            <p class="title"><?php echo $item['title']; ?></p>
            <p class="subtitle"><?php echo $item['description']; ?></p>
        </div>
    <?php } ?>
</div>
