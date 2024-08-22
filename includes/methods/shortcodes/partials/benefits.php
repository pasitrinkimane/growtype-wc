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
        return self::benefits($attr);
    }

    /**
     * @return false|string
     */
    public static function benefits($params = [])
    {
        ob_start();

        $benefits = [];
        $benefits = apply_filters('growtype_wc_benefits', $benefits, $params);

        if (!empty($benefits)) { ?>
            <ul class="list-check">
                <?php foreach ($benefits as $benefit) { ?>
                    <li><?php echo $benefit ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <?php
        return ob_get_clean();
    }
}
