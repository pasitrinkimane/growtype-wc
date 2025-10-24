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
        $label = esc_html($params['label'] ?? 'Over 54k+ happy users');
        $shuffle = strtolower($params['shuffle'] ?? 'false') === 'true';

        $avatars = [
            'male' => [
                'https://randomuser.me/api/portraits/men/10.jpg',
                'https://randomuser.me/api/portraits/men/11.jpg',
                'https://randomuser.me/api/portraits/men/12.jpg',
                'https://randomuser.me/api/portraits/men/13.jpg',
                'https://randomuser.me/api/portraits/men/14.jpg',
                'https://randomuser.me/api/portraits/men/15.jpg',
                'https://randomuser.me/api/portraits/men/16.jpg',
                'https://randomuser.me/api/portraits/men/17.jpg',
                'https://randomuser.me/api/portraits/men/18.jpg',
                'https://randomuser.me/api/portraits/men/19.jpg',
                'https://randomuser.me/api/portraits/men/20.jpg',
            ],
            'female' => [
                'https://randomuser.me/api/portraits/women/10.jpg',
                'https://randomuser.me/api/portraits/women/11.jpg',
                'https://randomuser.me/api/portraits/women/12.jpg',
                'https://randomuser.me/api/portraits/women/13.jpg',
                'https://randomuser.me/api/portraits/women/14.jpg',
                'https://randomuser.me/api/portraits/women/15.jpg',
                'https://randomuser.me/api/portraits/women/16.jpg',
                'https://randomuser.me/api/portraits/women/17.jpg',
                'https://randomuser.me/api/portraits/women/18.jpg',
                'https://randomuser.me/api/portraits/women/19.jpg',
                'https://randomuser.me/api/portraits/women/20.jpg',
            ],
        ];

        // Build avatar pool based on gender
        if ($gender === 'mix') {
            $half = max(1, floor($amount / 2));
            $rest = $amount - $half;

            $maleAvatars = array_slice($avatars['male'], 0, $half);
            $femaleAvatars = array_slice($avatars['female'], 0, $rest);

            $pool = array_merge($maleAvatars, $femaleAvatars);
        } elseif (isset($avatars[$gender])) {
            $pool = $avatars[$gender];
        } else {
            $pool = $avatars['male'];
        }

        if ($shuffle) {
            shuffle($pool);
        }

        // Limit the number of avatars shown
        $avatarList = array_slice($pool, 0, max(1, $amount));

        $images = '';
        foreach ($avatarList as $index => $avatar) {
            $margin = $index === 0 ? '0' : '-12px';
            $images .= '<img src="' . esc_url($avatar) . '" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid #1a1a1a; margin-left: ' . $margin . ';" />';
        }

        return '
    <div style="display: flex; align-items: center; gap: 10px; background: #1a1a1a; border-radius: 9999px; padding: 6px 16px; padding-left: 2px; color: #ffffff; font-size: 16px; font-weight: 500; width: fit-content;">
        <div style="display: flex;">' . $images . '</div>
        <span style="font-size: 14px;">' . $label . '</span>
    </div>
    ';
    }
}
