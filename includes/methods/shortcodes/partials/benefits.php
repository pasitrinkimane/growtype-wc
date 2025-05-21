<?php

/**
 *
 */
class Growtype_Wc_Benefits_Shortcode
{
    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode('growtype_wc_benefits', array ($this, 'shortcode'));
        }
    }

    /**
     * @param $attr
     * @return string
     * Posts shortcode
     */
    function shortcode($attr)
    {
        return self::render($attr);
    }

    /**
     * @return false|string
     */
    public static function render($params = [])
    {
        ob_start();

        $benefits = [];
        $benefits = apply_filters('growtype_wc_benefits', $benefits, $params);

        if (!empty($benefits)) {
            $is_slider = $params['slider'] ?? false;

            if ($is_slider) {
                ?>
                <div class="gwc-benefits-slider growtype-theme-slider" data-gslick='{"infinite": true, "slidesToShow": 1, "slidesToScroll": 1, "arrows": false, "dots": true, "fade": true, "autoplay": true, "autoplaySpeed": 2000}'>
                    <?php foreach ($benefits as $benefit) { ?>
                        <div class="gwc-benefits-slider-slide">
                            <div class="gwc-benefits-slider-slide-description">
                                <p class="gwc-benefits-slider-slide-title"><?php echo $benefit['title'] ?></p>
                                <?php if (isset($benefit['subtitle'])) { ?>
                                    <p class="gwc-benefits-slider-slide-subtitle"><?php echo $benefit['subtitle'] ?></p>
                                <?php } ?>
                            </div>
                            <?php if (isset($benefit['image']['url'])) { ?>
                                <div class="gwc-benefits-slider-slide-img" style="background:url(<?= $benefit['image']['url'] ?>);background-size: <?= $benefit['image']['background_size'] ?? 'cover' ?>;background-position: <?= $benefit['image']['background_position'] ?? 'center' ?>; background-repeat: no-repeat;"></div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <?php
            } else {
                ?>
                <ul class="gwc-benefits list-check">
                    <?php foreach ($benefits as $benefit) { ?>
                        <li><?php echo $benefit['title'] ?></li>
                    <?php } ?>
                </ul>
                <?php
            }
        } ?>

        <?php
        return ob_get_clean();
    }
}
