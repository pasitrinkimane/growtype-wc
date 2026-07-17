<?php

/**
 *
 */
class Growtype_Wc_Benefits_Shortcode
{
    const VERSIONS = ["highlight", "compact"];

    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode("growtype_wc_benefits", [$this, "shortcode"]);
        }
    }

    function shortcode($attr)
    {
        return self::render($attr);
    }

    // ── Public API ─────────────────────────────────────────────────────────

    public static function render($params = [])
    {
        $benefits = apply_filters("growtype_wc_benefits", [], $params);
        $version = $params["version"] ?? null;

        ob_start();

        if ($version && self::version_exists($version)) {
            $method = self::version_method($version);

            // Allow themes/plugins to intercept any version
            $overrides = apply_filters(
                "growtype_wc_benefits_version_overrides",
                [],
                $version,
                $benefits,
                $params,
            );

            if (isset($overrides[$version])) {
                echo call_user_func($overrides[$version], $benefits, $params);
            } elseif (method_exists(static::class, $method)) {
                echo call_user_func(
                    [static::class, $method],
                    $benefits,
                    $params,
                );
            }
        } elseif (!empty($benefits)) {
            $is_slider = $params["slider"] ?? false;
            echo $is_slider
                ? self::render_slider($benefits)
                : self::render_list($benefits);
        }

        return ob_get_clean();
    }

    // ── Version helpers ────────────────────────────────────────────────────

    /**
     * Check whether a version name is registered.
     */
    public static function version_exists(string $version): bool
    {
        $versions = apply_filters(
            "growtype_wc_benefits_versions",
            static::VERSIONS,
        );

        return in_array($version, $versions, true);
    }

    /**
     * Resolve a version name to its render method.
     */
    public static function version_method(string $version): string
    {
        return "render_version_" . $version;
    }

    /**
     * Register a new version at runtime.
     */
    public static function register_version(string $version): void
    {
        add_filter("growtype_wc_benefits_versions", function (
            array $versions,
        ) use ($version): array {
            if (!in_array($version, $versions, true)) {
                $versions[] = $version;
            }
            return $versions;
        });
    }

    // ── Version: highlight ─────────────────────────────────────────────────

    private static function render_version_highlight(
        array $benefits,
        array $params,
    ): string {
        $output = "";

        $output .= self::highlight_styles();

        if (!empty($benefits)) {
            $output .= self::highlight_cards($benefits);
        }

        return $output;
    }

    private static function highlight_styles(): string
    {
        ob_start(); ?>
        <style>
        .ps-benefits-highlight {
            display: flex;
            align-items: center;
            gap: 14px;
            max-width: 480px;
            margin: 0 auto 28px;
            padding: 16px 20px;
            background: var(--card-background-color, rgba(255,255,255,.04));
            border: 1px solid var(--ps-border, rgba(255,255,255,.1));
            border-radius: 14px;
        }
        .ps-benefits-highlight-icon {
            flex-shrink: 0;
            color: var(--ps-color-primary, #3d7fa8);
            line-height: 0;
        }
        .ps-benefits-highlight-text {
            margin: 0;
            font-size: .92rem;
            font-weight: 500;
            line-height: 1.5;
            color: var(--ps-text, #fff);
        }
        .ps-benefits-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 14px;
        }
        .ps-benefits-card {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 18px;
            background: var(--card-background-color, rgba(255,255,255,.035));
            border: 1px solid var(--ps-border, rgba(255,255,255,.07));
            border-radius: 14px;
            transition: border-color .2s, box-shadow .2s;
        }
        .ps-benefits-card:hover {
            border-color: color-mix(in srgb, var(--ps-color-primary, #3d7fa8) 30%, transparent);
            box-shadow: 0 4px 20px color-mix(in srgb, var(--ps-color-primary, #3d7fa8) 8%, transparent);
        }
        .ps-benefits-card-img {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            overflow: hidden;
        }
        .ps-benefits-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .ps-benefits-card-body {
            flex: 1;
            min-width: 0;
        }
        .ps-benefits-card-title {
            margin: 0;
            font-size: .92rem;
            font-weight: 600;
            line-height: 1.4;
            color: var(--ps-text, #fff);
        }
        .ps-benefits-card-subtitle {
            margin: 4px 0 0;
            font-size: .82rem;
            line-height: 1.45;
            color: var(--ps-text-muted, rgba(255,255,255,.55));
        }
        </style>
        <?php return ob_get_clean();
    }

    private static function highlight_header(): string
    {
        ob_start(); ?>
        <div class="ps-benefits-highlight">
            <div class="ps-benefits-highlight-icon">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="16" cy="16" r="16" fill="currentColor" opacity=".15"/>
                    <circle cx="16" cy="16" r="13" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M16.833 11.836h-1.666v5h5v-1.667h-3.334v-3.333Z" fill="currentColor"/>
                </svg>
            </div>
            <p class="ps-benefits-highlight-text">It takes only 10 minutes a day. Small changes lead to big results.</p>
        </div>
        <?php return ob_get_clean();
    }

    private static function highlight_cards(array $benefits): string
    {
        ob_start(); ?>
        <div class="ps-benefits-cards">
            <?php foreach ($benefits as $benefit):
                $tags_attr = self::tags_attr($benefit); ?>
                <div class="ps-benefits-card"<?= $tags_attr ?>>
                    <?php if (!empty($benefit["images"][0]["url"])): ?>
                        <div class="ps-benefits-card-img">
                            <img src="<?= esc_url(
                                $benefit["images"][0]["url"],
                            ) ?>" alt="" loading="lazy">
                        </div>
                    <?php endif; ?>
                    <div class="ps-benefits-card-body">
                        <p class="ps-benefits-card-title"><?= $benefit[
                            "title"
                        ] ?></p>
                        <?php if (!empty($benefit["subtitle"])): ?>
                            <p class="ps-benefits-card-subtitle"><?= $benefit[
                                "subtitle"
                            ] ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
            endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    // ── Version: compact ───────────────────────────────────────────────────

    private static function render_version_compact(
        array $benefits,
        array $params,
    ): string {
        ob_start(); ?>
        <div class="ps-benefits-compact">
            <?php foreach ($benefits as $benefit): ?>
                <div class="ps-benefits-compact-row">
                    <div class="ps-benefits-compact-icon">
                        <?php if (!empty($benefit["images"][0]["url"])): ?>
                            <img src="<?= esc_url(
                                $benefit["images"][0]["url"],
                            ) ?>" alt="" width="32" height="32" loading="lazy">
                        <?php else: ?>
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="16" cy="16" r="16" fill="currentColor" opacity=".15"/>
                                <circle cx="16" cy="16" r="13" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M16.833 11.836h-1.666v5h5v-1.667h-3.334v-3.333Z" fill="currentColor"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="ps-benefits-compact-text">
                        <span class="ps-benefits-compact-title"><?= $benefit[
                            "title"
                        ] ?></span>
                        <?php if (!empty($benefit["subtitle"])): ?>
                            <span class="ps-benefits-compact-subtitle"><?= $benefit[
                                "subtitle"
                            ] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <style>
        .ps-benefits-compact {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .ps-benefits-compact-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            background: var(--card-background-color, rgba(255,255,255,.035));
            border: 1px solid var(--ps-border, rgba(255,255,255,.07));
            border-radius: 14px;
        }
        .ps-benefits-compact-icon {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ps-color-primary, #3d7fa8);
            line-height: 0;
        }
        .ps-benefits-compact-icon img {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            object-fit: cover;
        }
        .ps-benefits-compact-text {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .ps-benefits-compact-title {
            font-size: .92rem;
            font-weight: 600;
            line-height: 1.4;
            color: var(--ps-text, #fff);
        }
        .ps-benefits-compact-subtitle {
            font-size: .82rem;
            line-height: 1.45;
            color: var(--ps-text-muted, rgba(255,255,255,.55));
        }
        </style>
        <?php return ob_get_clean();
    }

    // ── Default: slider ────────────────────────────────────────────────────

    private static function render_slider(array $benefits): string
    {
        ob_start(); ?>
        <div class="gwc-benefits-slider growtype-theme-slider" data-gslick='{"infinite": true, "slidesToShow": 1, "slidesToScroll": 1, "arrows": false, "dots": true, "fade": true, "autoplay": false, "autoplaySpeed": 2000}'>
            <?php foreach ($benefits as $benefit):
                $tags_attr = self::tags_attr($benefit); ?>
                <div class="gwc-benefits-slider-slide"<?= $tags_attr ?>>
                    <div class="gwc-benefits-slider-slide-images">
                        <?php if (!empty($benefit["images"])):
                            foreach ($benefit["images"] as $image):
                                $url = $image["url"] ?? "";
                                $ext = strtolower(
                                    pathinfo(
                                        parse_url($url, PHP_URL_PATH),
                                        PATHINFO_EXTENSION,
                                    ),
                                );
                                if (in_array($ext, ["mp4", "webm", "ogg"])): ?>
                                    <div class="gwc-benefits-slider-slide-img gwc-benefits-slider-slide-video">
                                        <video width="390" height="844" autoplay muted loop playsinline preload="none">
                                            <source src="<?= $url ?>" type="video/<?= $ext ?>">
                                        </video>
                                    </div>
                                <?php else: ?>
                                    <div class="gwc-benefits-slider-slide-img"
                                         style="background:url('<?= $url ?>');
                                             background-size: <?= $image[
                                                 "background_size"
                                             ] ?? "cover" ?>;
                                             background-position: <?= $image[
                                                 "background_position"
                                             ] ?? "center" ?>;
                                             background-repeat: no-repeat;">
                                    </div>
                                <?php endif;
                            endforeach;
                        endif; ?>
                    </div>
                    <div class="gwc-benefits-slider-slide-description">
                        <p class="gwc-benefits-slider-slide-title"><?= $benefit[
                            "title"
                        ] ?></p>
                        <?php if (!empty($benefit["subtitle"])): ?>
                            <p class="gwc-benefits-slider-slide-subtitle"><?= $benefit[
                                "subtitle"
                            ] ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
            endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    // ── Default: list ──────────────────────────────────────────────────────

    private static function render_list(array $benefits): string
    {
        ob_start(); ?>
        <ul class="gwc-benefits list-check">
            <?php foreach ($benefits as $benefit): ?>
                <li<?= self::tags_attr($benefit) ?>><?= $benefit[
    "title"
] ?></li>
            <?php endforeach; ?>
        </ul>
        <?php return ob_get_clean();
    }

    // ── Shared helpers ─────────────────────────────────────────────────────

    private static function tags_attr(array $benefit): string
    {
        if (empty($benefit["tags"])) {
            return "";
        }

        return ' data-tags="' . esc_attr(implode(" ", $benefit["tags"])) . '"';
    }
}
