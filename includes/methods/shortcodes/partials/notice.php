<?php

class Growtype_Wc_Notice
{
    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode('growtype_wc_notice', array ($this, 'shortcode'));
        }
    }

    /**
     * Shortcode handler
     *
     * @param $attr
     * @return string
     */
    function shortcode($attr)
    {
        return self::render($attr);
    }

    /**
     * Renders the banner based on discount periods
     *
     * @param array $params
     * @return false|string
     */
    public static function render($params = [])
    {
        ob_start();
        if (function_exists('wc_print_notices')) {
            wc_print_notices();
        }
        return ob_get_clean();
    }
}
