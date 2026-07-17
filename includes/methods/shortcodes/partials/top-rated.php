<?php

class Growtype_Wc_Top_Rated
{
    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode("growtype_wc_top_rated", [$this, "shortcode"]);
        }
    }

    function shortcode($attr)
    {
        $params = shortcode_atts(
            [
                "rating" => "4.8",
                "icon" => "⭐",
                "label" => "", // e.g. "TOP APP IN {country}"
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
        $country_code = growtype_wc_detect_user_country();
        $country_name = $country_code
            ? growtype_wc_country_code_to_name($country_code)
            : "";

        $label = $params["label"] ?? "";
        if (empty($label) && !empty($country_name)) {
            $label = sprintf(
                __("TOP APP IN %s", "growtype-wc"),
                mb_strtoupper($country_name),
            );
        }

        // Fallback when country can't be detected (localhost, VPN, etc.)
        if (empty($label)) {
            $label = __("TOP RATED APP", "growtype-wc");
        }

        $rating = $params["rating"] ?? "4.8";
        $icon = $params["icon"] ?? "";

        // Resolve flag emoji from country code
        $flag = $params["flag"] ?? "";
        if ($flag !== "none" && empty($flag) && !empty($country_code) && strlen($country_code) === 2) {
            $flag = mb_chr(0x1F1E6 + ord($country_code[0]) - ord("A")) .
                    mb_chr(0x1F1E6 + ord($country_code[1]) - ord("A"));
        }

        $rand_id = "tr_" . wp_rand(1000, 9999);

        ob_start();
        ?>
        <div class="gwc-top-rated" id="<?= esc_attr($rand_id) ?>"
             data-country="<?= esc_attr($country_code) ?>">
            <div class="gwc-top-rated-inner">
                <span class="gwc-top-rated-icon"><?= esc_html($icon) ?></span>
                <?php if (!empty($flag)): ?>
                <span class="gwc-top-rated-flag"><?= esc_html($flag) ?></span>
                <?php endif; ?>
                <h3 class="gwc-top-rated-label"><?= esc_html($label) ?></h3>
                <div class="gwc-top-rated-stars">
                    <svg class="gwc-top-rated-star" xmlns="http://www.w3.org/2000/svg"
                         width="13" height="13" fill="none" aria-hidden="true">
                        <path fill="currentColor" d="M12.966 5.413a.7.7 0 0 0-.595-.496L8.62 4.56 7.135.937A.69.69 0 0 0 6.5.5a.69.69 0 0 0-.635.438L4.38 4.561l-3.753.356a.7.7 0 0 0-.594.496.74.74 0 0 0 .202.765l2.836 2.596-.836 3.844a.74.74 0 0 0 .269.745.667.667 0 0 0 .759.034L6.5 11.38l3.235 2.018a.665.665 0 0 0 .76-.034.74.74 0 0 0 .269-.745l-.837-3.844 2.837-2.595a.74.74 0 0 0 .202-.766"/>
                    </svg>
                    <span class="gwc-top-rated-value"><?= esc_html(
                        $rating,
                    ) ?></span>
                </div>
            </div>
        </div>
        <style>
            .gwc-top-rated {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 8px 16px;
                background: var(--card-background-color, rgba(255,255,255,0.05));
                border-radius: var(--card-border-radius, 12px);
                font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            }
            .gwc-top-rated-inner {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
                justify-content: center;
            }
            .gwc-top-rated-icon {
                font-size: 1rem;
            }
            .gwc-top-rated-flag {
                font-size: 1rem;
                line-height: 1;
            }
            .gwc-top-rated-label {
                font-size: 0.85rem;
                font-weight: 700;
                margin: 0;
                letter-spacing: 0.02em;
            }
            .gwc-top-rated-stars {
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .gwc-top-rated-star {
                color: #FFB800;
            }
            .gwc-top-rated-value {
                font-size: 0.85rem;
                font-weight: 700;
            }
        </style>
        <?php return ob_get_clean();
    }
}
