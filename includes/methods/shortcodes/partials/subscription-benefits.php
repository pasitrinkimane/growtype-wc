<?php

/**
 *
 */
class Growtype_Wc_Subscription_Benefits_Shortcode
{
    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode('growtype_wc_subscription_benefits', array ($this, 'shortcode'));
        }
    }

    /**
     * @param $attr
     * @return string
     * Posts shortcode
     */
    function shortcode($attr)
    {
        return self::benefits();
    }


    public static function benefits()
    {
        ob_start();

        ?>
        <ul class="list-check">
            <li>Unlock a vast library of intimate AI characters</li>
            <li>Access exclusive images without blur</li>
            <li>Receive thoughtful, evaluation-based responses for more satisfying interactions</li>
            <li>Experience the thrill of audio answers</li>
            <li>Receive 100 FREE tokens each month</li>
            <li>Unlock advanced character customization</li>
            <li>Stay ahead with early access to new chat features and updates</li>
        </ul>
        <?php

        return ob_get_clean();
    }
}
