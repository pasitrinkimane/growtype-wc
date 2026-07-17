<?php

class Growtype_Wc_Gift_Banner
{
    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode("growtype_wc_gift_banner", [$this, "shortcode"]);
        }
    }

    function shortcode($attr)
    {
        $params = shortcode_atts(
            [
                "text" => "",
                "mark" => "",
                "hidden" => "false",
            ],
            $attr,
        );

        if ($params["hidden"] === "true") {
            return "";
        }

        return self::render($params);
    }

    public static function render($params = [])
    {
        $text = $params["text"] ?? __("Take the quiz - get", "growtype-wc");
        $mark =
            $params["mark"] ?? __("PRINTABLE GUIDE FOR FREE", "growtype-wc");

        ob_start();
        ?>
        <div class="gwc-gift-banner">
            <span class="gwc-gift-banner-icon">🎁</span>
            <p class="gwc-gift-banner-text">
                <?= esc_html($text) ?>
                <strong><?= esc_html($mark) ?></strong>
            </p>
        </div>
        <style>
            .gwc-gift-banner {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 8px 16px;
                background: var(--card-background-color, rgba(250,204,21,0.06));
                border: 1px solid var(--card-border-color, rgba(250,204,21,0.15));
                border-radius: var(--card-border-radius, 12px);
            }
            .gwc-gift-banner-icon {
                font-size: 1.4rem;
                flex-shrink: 0;
            }
            .gwc-gift-banner-text {
                font-size: 0.85rem;
                font-weight: 500;
                margin: 0;
                line-height: 1.4;
            }
            .gwc-gift-banner-text strong {
                font-weight: 800;
                color: var(--theme-color);
            }
        </style>
        <?php return ob_get_clean();
    }
}
