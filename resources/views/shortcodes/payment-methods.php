<div class="b-payment-methods-icons" style="justify-content: <?php echo $params[
    "justify_content"
]; ?>;">
    <?php foreach ($icons as $icon) { ?>
        <div class="b-payment-methods-icon-single <?php echo isset(
            $icon["class"],
        )
            ? $icon["class"]
            : ""; ?>"<?php if (!empty($params["icon_width"])) {
    echo ' style="max-width:' .
        esc_attr($params["icon_width"]) .
        ";min-width:" .
        esc_attr($params["icon_width"]) .
        '"';
} ?>>
            <img src="<?php echo $icon["url"]; ?>" alt="">
        </div>
    <?php } ?>
</div>
