<?php

class Growtype_Wc_Rating_Badges
{
    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode("growtype_wc_rating_badges", [$this, "shortcode"]);
        }
    }

    function shortcode($attr)
    {
        $params = shortcode_atts(
            [
                "rating_value" => "4.8",
                "rating_label" => "",
                "reviews_value" => "50,000+",
                "reviews_label" => "",
                "reviews_footer" => "",
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
        $rating_value = $params["rating_value"] ?? "4.8";
        $rating_label = $params["rating_label"] ?? __("Rating", "growtype-wc");
        $reviews_value = $params["reviews_value"] ?? "500+";
        $reviews_label =
            $params["reviews_label"] ?? __("More than", "growtype-wc");
        $reviews_footer =
            $params["reviews_footer"] ?? __("5-star reviews", "growtype-wc");

        $laurel_left = self::laurel_svg("left");
        $laurel_right = self::laurel_svg("right");

        ob_start();
        ?>
        <div class="gwc-rating-badges" aria-label="<?= esc_attr(
            __("App ratings", "growtype-wc"),
        ) ?>">
            <div class="gwc-rating-badge">
                <span class="gwc-rating-laurel" aria-hidden="true"><?= $laurel_left ?></span>
                <div class="gwc-rating-text">
                    <p class="gwc-rating-label"><?= esc_html(
                        $rating_label,
                    ) ?></p>
                    <p class="gwc-rating-value"><?= esc_html(
                        $rating_value,
                    ) ?></p>
                    <div class="gwc-rating-stars" aria-hidden="true">
                        <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                    </div>
                </div>
                <span class="gwc-rating-laurel gwc-rating-laurel--flip" aria-hidden="true"><?= $laurel_right ?></span>
            </div>
            <div class="gwc-rating-badge">
                <span class="gwc-rating-laurel" aria-hidden="true"><?= $laurel_left ?></span>
                <div class="gwc-rating-text">
                    <p class="gwc-rating-label"><?= esc_html(
                        $reviews_label,
                    ) ?></p>
                    <p class="gwc-rating-value"><?= esc_html(
                        $reviews_value,
                    ) ?></p>
                    <div class="gwc-rating-footer"><?= esc_html(
                        $reviews_footer,
                    ) ?></div>
                </div>
                <span class="gwc-rating-laurel" aria-hidden="true"><?= $laurel_right ?></span>
            </div>
        </div>
        <style>
            .gwc-rating-badges {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .gwc-rating-badge {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .gwc-rating-laurel {
                width: 20px;
                height: auto;
                flex-shrink: 0;
                opacity: 0.5;
                line-height: 0;
            }
            .gwc-rating-laurel svg {
                width: 20px;
                height: auto;
                display: block;
                color: currentColor;
            }
            .gwc-rating-text {
                text-align: center;
                min-width: 80px;
            }
            .gwc-rating-label {
                font-size: 0.7rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                margin: 0 0 2px;
                opacity: 0.6;
            }
            .gwc-rating-value {
                font-size: 1.15rem;
                font-weight: 800;
                margin: 0;
                line-height: 1.2;
            }
            .gwc-rating-stars {
                font-size: 0.65rem;
                color: #facc15;
                letter-spacing: 1px;
                margin-top: 2px;
            }
            .gwc-rating-footer {
                font-size: 0.65rem;
                font-weight: 500;
                margin-top: 2px;
                opacity: 0.7;
            }
        </style>
        <?php return ob_get_clean();
    }

    private static function laurel_svg(string $side): string
    {
        static $cache = [];

        if (isset($cache[$side])) {
            return $cache[$side];
        }

        $file = GROWTYPE_WC_PATH . "public/icons/lavr-" . $side . ".svg";

        if (!file_exists($file)) {
            $cache[$side] = "";
            return "";
        }

        $svg = file_get_contents($file);

        // Strip xml/doctype declarations, keep the <svg> only
        $svg = preg_replace("/<\?xml.*?\?>/i", "", $svg);
        $svg = preg_replace("/<!DOCTYPE[^>]*>/i", "", $svg);
        $svg = trim($svg);

        // Make currentColor work for theme integration
        $svg = str_replace("<svg ", '<svg style="color:inherit" ', $svg);

        $cache[$side] = $svg;
        return $svg;
    }
}
