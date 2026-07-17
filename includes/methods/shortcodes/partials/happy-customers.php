<?php

class Growtype_Wc_Happy_Customers
{
    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode('growtype_wc_happy_customers', array ($this, 'shortcode'));
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
        $gender = strtolower($params['gender'] ?? 'mix');
        $amount = intval($params['amount'] ?? 4);
        $label = wp_kses($params['label'] ?? 'Over 54k+ happy users', ['b' => [], 'strong' => [], 'span' => ['style' => [], 'class' => []]]);
        $shuffle = strtolower($params['shuffle'] ?? 'false') === 'true';

        static $styles_output = false;
        $styles = '';
        if (!$styles_output) {
            $styles_output = true;
            $styles = '
<style>
.gt-happy-customers {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #1a1a1a;
    border-radius: 9999px;
    padding: 6px 16px 6px 2px;
    color: #ffffff;
    font-size: 16px;
    font-weight: 500;
    width: fit-content;
}
.gt-happy-customers-avatars {
    display: flex;
}
.gt-happy-customers-avatars img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid #1a1a1a;
}
.gt-happy-customers-avatars img + img {
    margin-left: -12px;
}
.gt-happy-customers-label {
    font-size: 14px;
}
</style>';
        }

        $avatarList = growtype_wc_get_user_portraits([
            'gender' => $gender,
            'count' => $amount,
            'shuffle' => $shuffle,
        ]);

        $images = '';
        foreach ($avatarList as $avatar) {
            $images .= '<img src="' . esc_url($avatar) . '" />';
        }

        return $styles . '
    <div class="gt-happy-customers">
        <div class="gt-happy-customers-avatars">' . $images . '</div>
        <span class="gt-happy-customers-label">' . $label . '</span>
    </div>
    ';
    }
}
