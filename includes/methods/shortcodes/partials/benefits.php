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
                <div class="gwc-benefits-slider growtype-theme-slider" data-gslick='{"infinite": true, "slidesToShow": 1, "slidesToScroll": 1, "arrows": false, "dots": true, "fade": true, "autoplay": false, "autoplaySpeed": 2000}'>
                    <?php foreach ($benefits as $benefit) { ?>
                        <div class="gwc-benefits-slider-slide">
                            <div class="gwc-benefits-slider-slide-images">
                                <?php if (!empty($benefit['images'])): ?>
                                    <?php foreach ($benefit['images'] as $image): ?>
                                        <?php
                                        $url = $image['url'] ?? '';
                                        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                                        $is_video = in_array($ext, ['mp4', 'webm', 'ogg']);
                                        ?>

                                        <?php if ($is_video): ?>
                                            <div class="gwc-benefits-slider-slide-img gwc-benefits-slider-slide-video">
                                                <video autoplay muted loop playsinline>
                                                    <source src="<?= $url ?>" type="video/<?= $ext ?>">
                                                </video>
                                            </div>
                                        <?php else: ?>
                                            <div class="gwc-benefits-slider-slide-img"
                                                 style="background:url('<?= $url ?>');
                                                     background-size: <?= $image['background_size'] ?? 'cover' ?>;
                                                     background-position: <?= $image['background_position'] ?? 'center' ?>;
                                                     background-repeat: no-repeat;">
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="gwc-benefits-slider-slide-description">
                                <p class="gwc-benefits-slider-slide-title"><?php echo $benefit['title'] ?></p>
                                <?php if (isset($benefit['subtitle'])) { ?>
                                    <p class="gwc-benefits-slider-slide-subtitle"><?php echo $benefit['subtitle'] ?></p>
                                <?php } ?>
                            </div>
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
