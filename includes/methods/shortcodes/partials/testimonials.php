<?php

class Growtype_Wc_Testimonials
{
    const VERSION_TWEET      = 'tweet';
    const VERSION_TRUSTPILOT = 'trustpilot';
    const VERSION_CARD       = 'card';
    const VERSION_COMPACT    = 'compact';
    const VERSION_SIMPLE     = 'simple';

    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode("growtype_wc_testimonials_tweet", [
                $this,
                "shortcode",
            ]);
        }
    }

    function shortcode($attr)
    {
        $testimonials = [];

        for ($i = 0; $i < 10; $i++) {
            $author = $attr["t{$i}_author"] ?? null;
            $content = $attr["t{$i}_content"] ?? null;
            $image = $attr["t{$i}_image"] ?? null;
            $location = $attr["t{$i}_location"] ?? null;
            $username = $attr["t{$i}_username"] ?? null;

            if ($author && $content) {
                $testimonials[] = [
                    "author" => $author,
                    "content" => $content,
                    "image" => $image,
                    "location" => $location,
                    "username" => $username,
                ];
            }
        }

        return self::render(
            array_merge($attr, ["testimonials" => $testimonials]),
        );
    }

    public static function render($params = [])
    {
        $version = strtolower($params["version"] ?? self::VERSION_TWEET);
        $params["skin"] = strtolower($params["skin"] ?? "light");

        if ($version === self::VERSION_TRUSTPILOT) {
            return self::render_trustpilot($params);
        }

        if ($version === self::VERSION_CARD) {
            return self::render_card($params);
        }

        if ($version === self::VERSION_COMPACT) {
            return self::render_compact($params);
        }

        if ($version === self::VERSION_SIMPLE) {
            return self::render_simple($params);
        }

        return self::render_tweet($params);
    }

    /**
     * Centralized color palette — all versions use this single source of truth.
     */
    private static function colors($skin = "light")
    {
        $d = $skin === "dark";

        return [
            // Tweet version
            "tweet_bg" => $d ? "#16181c" : "#ffffff",
            "tweet_text" => $d ? "#e7e9ea" : "#0f1419",
            "tweet_muted" => $d ? "#71767b" : "#536471",
            "tweet_border" => $d ? "#2f3336" : "#e2e5e9",

            // Card version
            "card_bg" => $d ? "rgba(255,255,255,.03)" : "#ffffff",
            "card_border" => $d ? "rgba(255,255,255,.08)" : "#e4e4e4",
            "card_text" => $d ? "#fff" : "#222",
            "card_muted" => $d ? "rgba(255,255,255,.4)" : "#666",
            "card_avatar" => $d ? "rgba(255,255,255,.12)" : "#e4e4e4",

            // Trustpilot version
            "trustpilot_bg" => $d ? "#141414" : "#ffffff",
            "trustpilot_text" => $d ? "#fff" : "#222",
            "star_fill" => "#fff",
            "star_bg" => "green",
            "half_bg" => "#a6a6a6",
        ];
    }

    /**
     * Fetch deterministic gender-separated portrait pools.
     */
    private static function portrait_pools($count = 5)
    {
        $men = function_exists("growtype_wc_get_user_portraits")
            ? growtype_wc_get_user_portraits([
                "gender" => "men",
                "count" => $count,
                "shuffle" => false,
            ])
            : [];
        $women = function_exists("growtype_wc_get_user_portraits")
            ? growtype_wc_get_user_portraits([
                "gender" => "women",
                "count" => $count,
                "shuffle" => false,
            ])
            : [];
        return [$men, $women];
    }

    /**
     * Resolve avatar URL from testimonial data, falling back to gender pool.
     */
    private static function resolve_avatar(
        $t,
        $idx,
        $portraits_men,
        $portraits_women,
        &$m_idx,
        &$w_idx,
    ) {
        $image = $t["image"] ?? null;
        if ($image) {
            return esc_url($image);
        }

        $gender = $t["image_gender"] ?? "";
        $name = $t["image_name"] ?? "";

        if ($gender === "women" && !empty($portraits_women)) {
            $pick = $name
                ? abs(crc32($name)) % count($portraits_women)
                : $w_idx++;
            return $portraits_women[$pick] ?? "";
        }

        if ($gender === "men" && !empty($portraits_men)) {
            $pick = $name
                ? abs(crc32($name)) % count($portraits_men)
                : $m_idx++;
            return $portraits_men[$pick] ?? "";
        }

        return "https://randomuser.me/api/portraits/" .
            ($idx % 2 ? "women" : "men") .
            "/" .
            rand(0, 99) .
            ".jpg";
    }

    private static function default_testimonials()
    {
        $app = get_bloginfo("name");

        $portraits_men = function_exists("growtype_wc_get_user_portraits")
            ? growtype_wc_get_user_portraits([
                "gender" => "men",
                "count" => 5,
                "shuffle" => false,
            ])
            : [];

        $portraits_women = function_exists("growtype_wc_get_user_portraits")
            ? growtype_wc_get_user_portraits([
                "gender" => "women",
                "count" => 5,
                "shuffle" => false,
            ])
            : [];

        $m_idx = 0;
        $w_idx = 0;

        $defaults = [
            [
                "author" => "Marcus",
                "image_gender" => "men",
                "image_name" => "Marcus",
                "intro" => "Game changer",
                "title" => "Helped me land my dream job",
                "content" => "{$app} completely changed how I prepare for big moments. The feedback is instant and genuinely useful.",
                "quote" => "{$app} helped me practise my interview skills until they became second nature. I walked into that room feeling more prepared than ever — and I got the offer.",
                "location" => "Austin, TX",
                "stars" => "★★★★★",
                "avatar" => "",
                "username" => "@marcus_writes",
            ],
            [
                "author" => "Priya",
                "image_gender" => "women",
                "image_name" => "Priya",
                "intro" => "So confident now",
                "title" => "Got the raise I deserved",
                "content" => "I was nervous about my review, but after practising with {$app} I walked in calm and confident. Got the raise!",
                "quote" => "I've been comparing {$app} to other options, and {$app} has truly raised the bar! I practised my salary negotiation over and over until I felt completely confident — and it paid off.",
                "location" => "Los Angeles, CA",
                "stars" => "★★★★★",
                "avatar" => "",
                "username" => "@priyadev",
            ],
            [
                "author" => "Jamie",
                "image_gender" => "men",
                "image_name" => "Jamie",
                "intro" => "Surprisingly personal",
                "title" => "Surprisingly real and effective",
                "content" => "Honestly didn't expect an AI tool to feel this personal. {$app} understood exactly what I needed to work on.",
                "quote" => "I was skeptical at first, but {$app} totally won me over. The practice sessions feel so real — it's like having a personal coach in my pocket.",
                "location" => "Brooklyn, NY",
                "stars" => "★★★★★",
                "avatar" => "",
                "username" => "@jamie_writes",
            ],
            [
                "author" => "Sofia",
                "image_gender" => "women",
                "image_name" => "Sofia",
                "intro" => "Landed the job",
                "title" => "My secret weapon before big meetings",
                "content" => "Used {$app} for two weeks before my interview. Landed the job. My only regret is not trying it sooner.",
                "quote" => "{$app} helps me prepare before high-stakes meetings. I practise my talking points, get real-time feedback, and walk in feeling ready.",
                "location" => "Seattle, WA",
                "stars" => "★★★★★",
                "avatar" => "",
                "username" => "@sofia_ux",
            ],
            [
                "author" => "David",
                "image_gender" => "men",
                "image_name" => "David",
                "intro" => "Worth every penny",
                "title" => "Worth every penny",
                "content" => "Every session with {$app} makes me feel more prepared. It's like having a coach in your pocket, available 24/7.",
                "quote" => "I tried {$app} a while ago and now I can't stop practising! My confidence has completely transformed.",
                "location" => "San Francisco, CA",
                "stars" => "★★★★★",
                "avatar" => "",
                "username" => "@david_builds",
            ],
        ];

        foreach ($defaults as &$t) {
            if ($t["image_gender"] === "women") {
                $t["image"] = $portraits_women[$w_idx++] ?? "";
            } else {
                $t["image"] = $portraits_men[$m_idx++] ?? "";
            }
        }
        unset($t);

        return apply_filters(
            "growtype_wc_default_testimonials",
            $defaults,
            $app,
        );
    }

    /**
     * V1 — Trustpilot-style cards (dark background, star rating, intro line).
     */
    private static function render_trustpilot($params = [])
    {
        $count = max(1, intval($params["count"] ?? 5));
        $custom = $params["testimonials"] ?? [];
        $wrapper_only = !empty($params["wrapper_only"]);
        $skin = $params["skin"] ?? "dark";
        $slider = strtolower(
            $params["slider"] ?? ($wrapper_only ? "none" : "native"),
        );

        $testimonials = !empty($custom)
            ? $custom
            : self::default_testimonials();

        $c = self::colors($params["skin"] ?? "dark");
        $star_fill = $c["star_fill"];

        $star_path =
            "m8 12.136 3.422-.917 1.43 4.656L8 12.135Zm7.875-6.018H9.852L8 .125 6.148 6.118H.125L5 9.833l-1.852 5.992 4.875-3.714 3-2.278 4.852-3.715Z";

        $cards = "";
        foreach ($testimonials as $idx => $t) {
            if ($idx >= $count) {
                break;
            }

            $author = esc_html($t["author"]);
            $intro = esc_html($t["intro"] ?? ($t["title"] ?? ""));
            $content = esc_html(trim($t["content"], '"'));

            $star_svg =
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none"><path fill="' .
                $star_fill .
                '" d="' .
                esc_attr($star_path) .
                '"/></svg>';

            $cards .=
                '
            <div class="b-testimonial b-testimonial-trustpilot">
                <div class="b-testimonial-stars">
                    ' .
                str_repeat(
                    '<div class="b-testimonial-stars-single">' .
                        $star_svg .
                        "</div>",
                    4,
                ) .
                '
                    <div class="b-testimonial-stars-single">
                        <div class="b-testimonial-stars-single-half"></div>
                        ' .
                $star_svg .
                '
                    </div>
                </div>
                <div class="b-testimonial-intro">
                    <span class="b-testimonial-intro-title">' .
                $intro .
                '</span>
                    <span class="b-testimonial-intro-owner">' .
                $author .
                '</span>
                </div>
                <div class="b-testimonial-content">' .
                $content .
                '</div>
            </div>';
        }

        $bg = $c["trustpilot_bg"];
        $text = $c["trustpilot_text"];
        $half_bg = $c["half_bg"];
        $star_bg = $c["star_bg"];

        $css = "
        .b-testimonial-trustpilot {
            background: {$bg};
            box-shadow: 1px 3px 8px hsl(0 0% 0%/.10);
            padding: 20px;
            border-radius: var(--card-border-radius, 5px);
            text-align: left;
            color: {$text};
        }
        .b-testimonial-trustpilot .b-testimonial-stars {
            display: flex; gap: 10px; padding-bottom: 10px;
        }
        .b-testimonial-trustpilot .b-testimonial-stars-single {
            background: {$star_bg};
            padding: 5px;
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .b-testimonial-trustpilot .b-testimonial-stars-single svg {
            position: relative; z-index: 1;
        }
        .b-testimonial-trustpilot .b-testimonial-stars-single-half {
            position: absolute; right: 0; top: 0; bottom: 0; width: 50%;
            background: {$half_bg};
        }
        .b-testimonial-trustpilot .b-testimonial-intro {
            display: flex; justify-content: space-between; padding-bottom: 5px;
        }
        .b-testimonial-trustpilot .b-testimonial-intro-title {
            font-weight: bold; color: {$text};
        }
        .b-testimonial-trustpilot .b-testimonial-intro-owner {
            opacity: .8; color: {$text};
        }
        ";

        return self::wrap_slider(
            $css . $cards,
            $slider,
            $wrapper_only,
            !empty($params["full_width"]),
            "gwt-slick-slider",
            intval($params["slides_to_show"] ?? 2),
            isset($params["wrapper"]) ? !empty($params["wrapper"]) : true,
        );
    }

    /**
     * V3 — Glass-morphism dark cards with avatar, name, location, stars, title, quote.
     */
    private static function render_card($params = [])
    {
        $count = max(1, intval($params["count"] ?? 5));
        $gender = strtolower($params["gender"] ?? "mix");
        $custom = $params["testimonials"] ?? [];
        $wrapper_only = !empty($params["wrapper_only"]);
        $skin = $params["skin"] ?? "dark";
        $slider = strtolower(
            $params["slider"] ?? ($wrapper_only ? "none" : "slick"),
        );
        $slider = strtolower(
            $params["slider"] ?? ($wrapper_only ? "none" : "slick"),
        );

        $avatars = function_exists("growtype_wc_get_user_portraits")
            ? growtype_wc_get_user_portraits([
                "gender" => $gender,
                "count" => $count,
                "shuffle" => true,
            ])
            : [];

        $testimonials = !empty($custom)
            ? $custom
            : self::default_testimonials();

        $cards = "";
        foreach ($testimonials as $idx => $t) {
            if ($idx >= $count) {
                break;
            }

            $avatar = !empty($t["avatar"])
                ? esc_url($t["avatar"])
                : $avatars[$idx] ??
                    "https://randomuser.me/api/portraits/" .
                        ($idx % 2 ? "women" : "men") .
                        "/" .
                        rand(0, 99) .
                        ".jpg";
            $name = esc_html($t["name"] ?? ($t["author"] ?? ""));
            $location = esc_html($t["location"] ?? "");
            $stars = $t["stars"] ?? "★★★★★";
            $title = esc_html($t["title"] ?? ($t["intro"] ?? ""));
            $quote = esc_html(trim($t["quote"] ?? ($t["content"] ?? ""), '"'));

            $cards .=
                '
            <div class="ps-testimonial-card">
                <div class="ps-testimonial-card-inner">
                    <div class="ps-testimonial-header">
                        <img src="' .
                $avatar .
                '" alt="" class="ps-testimonial-avatar">
                        <div class="ps-testimonial-user">
                            <p class="ps-testimonial-name">' .
                $name .
                '</p>
                            ' .
                ($location
                    ? '<p class="ps-testimonial-location">' . $location . "</p>"
                    : "") .
                '
                        </div>
                        <div class="ps-testimonial-stars">' .
                $stars .
                '</div>
                    </div>
                    ' .
                ($title
                    ? '<h6 class="ps-testimonial-title">' . $title . "</h6>"
                    : "") .
                '
                    <div class="ps-testimonial-quote">
                        <span class="ps-testimonial-quote-mark">&ldquo;</span>
                        <p>' .
                $quote .
                '</p>
                    </div>
                </div>
            </div>';
        }

        $c = self::colors($skin);
        $card_bg = $c["card_bg"];
        $card_border = $c["card_border"];
        $text_color = $c["card_text"];
        $muted_color = $c["card_muted"];
        $avatar_border = $c["card_avatar"];

        $css = "
        .ps-testimonial-card {
            padding: 0 8px;
        }
        .ps-testimonial-card-inner {
            background: {$card_bg};
            border: 1px solid {$card_border};
            border-radius: 18px;
            padding: 20px;
            transition: border-color .3s, box-shadow .3s, transform .3s;
            position: relative;
            overflow: hidden;
        }
        .ps-testimonial-card-inner::before {
            content: \"\";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--theme-color, #3d7fa8), transparent);
            opacity: 0;
            transition: opacity .3s;
        }
        .ps-testimonial-card-inner:hover {
            border-color: color-mix(in srgb, var(--theme-color, #3d7fa8) 40%, transparent);
            box-shadow: 0 8px 40px color-mix(in srgb, var(--theme-color, #3d7fa8) 10%, transparent);
            transform: translateY(-2px);
        }
        .ps-testimonial-card-inner:hover::before { opacity: 1; }
        .ps-testimonial-header {
            display: flex; align-items: center; gap: 14px; margin-bottom: 20px;
        }
        .ps-testimonial-avatar {
            width: 52px; height: 52px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
            border: 2px solid {$avatar_border};
        }
        .ps-testimonial-user { flex: 1; min-width: 0; }
        .ps-testimonial-name {
            font-weight: 700; margin: 0; font-size: .95rem; color: {$text_color};
        }
        .ps-testimonial-location {
            font-size: .78rem; margin: 2px 0 0; color: {$muted_color};
        }
        .ps-testimonial-stars {
            color: var(--ps-star-color, #f59e0b); font-size: 18px; flex-shrink: 0; letter-spacing: 2px;
        }
        .ps-testimonial-title {
            font-weight: 700; margin: 0 0 12px; font-size: 1rem; color: {$text_color};
        }
        .ps-testimonial-quote {
            position: relative; padding-left: 20px;
        }
        .ps-testimonial-quote-mark {
            position: absolute; left: 0; top: -6px; font-size: 2.2rem; line-height: 1;
            font-weight: 800; color: var(--theme-color, #3d7fa8); opacity: .5; font-family: Georgia, serif;
        }
        .ps-testimonial-quote p {
            margin: 0; line-height: 1.7; font-size: .9rem; color: {$muted_color};
        }
        .ps-testimonial-carousel .slick-list { overflow: visible; margin: 0 -8px; }
        .ps-testimonial-carousel .slick-slide > div { height: 100%; }
        .ps-testimonial-carousel .slick-dots { padding-top: 44px; bottom: -60px; }
        .ps-testimonial-carousel .slick-dots li button { border: 1.5px solid var(--ps-border, rgba(255,255,255,.2)); }
        .ps-testimonial-carousel .slick-dots li button:hover { border-color: var(--theme-color); background: color-mix(in srgb, var(--theme-color) 30%, transparent); }
        .ps-testimonial-carousel .slick-dots li.slick-active button { width: 28px; background: var(--theme-color); border-color: var(--theme-color); }
        .ps-testimonial-carousel .slick-dots li button:before { display: none; }
        ";

        return self::wrap_slider(
            $css . $cards,
            $slider,
            $wrapper_only,
            !empty($params["full_width"]),
            "ps-testimonial-carousel",
            intval($params["slides_to_show"] ?? 2),
            isset($params["wrapper"]) ? !empty($params["wrapper"]) : true,
        );
    }

    /**
     * Compact — avatar + short quote, single-line style.
     */
    private static function render_compact($params = [])
    {
        $count = max(1, intval($params["count"] ?? 5));
        $custom = $params["testimonials"] ?? [];
        $skin = $params["skin"] ?? "light";

        $testimonials = !empty($custom)
            ? $custom
            : self::default_testimonials();

        [$portraits_men, $portraits_women] = self::portrait_pools($count);
        $m_idx = 0;
        $w_idx = 0;

        $items = "";
        foreach ($testimonials as $idx => $t) {
            if ($idx >= $count) {
                break;
            }

            $author = esc_html($t["author"]);
            $quote = esc_html(trim($t["quote"] ?? ($t["content"] ?? ""), '"'));
            $av_url = self::resolve_avatar(
                $t,
                $idx,
                $portraits_men,
                $portraits_women,
                $m_idx,
                $w_idx,
            );

            $items .=
                '
            <div class="ps-testimonial-compact">
                <img src="' .
                esc_url($av_url) .
                '" alt="' .
                $author .
                '" class="ps-testimonial-compact-avatar" width="44" height="44" loading="lazy">
                <div class="ps-testimonial-compact-body">
                    <p class="ps-testimonial-compact-quote">&ldquo;' .
                $quote .
                '&rdquo; &mdash; <span class="ps-testimonial-compact-author">' .
                $author .
                '</span></p>
                </div>
            </div>';
        }

        $c = self::colors($skin);
        $text = $c["tweet_text"];
        $muted = $c["tweet_muted"];
        $border = $c["tweet_border"];

        $css = "
        .ps-testimonial-compact {
            display: flex!important;
            align-items: center;
            gap: 14px;
            padding: 10px 11px;
            background: var(--card-background-color);
            border-radius: 14px;
            margin-bottom: 10px;
        }
        .ps-testimonial-compact-slider{
            max-width: 510px;
            margin: auto;
        }
        .ps-testimonial-compact:last-child {
            margin-bottom: 0;
        }
        .ps-testimonial-compact-avatar {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
        }
        .ps-testimonial-compact-body {
            flex: 1;
            min-width: 0;
        }
        .ps-testimonial-compact-quote {
            margin: 0;
            font-size: .88rem;
            line-height: 1.6;
            font-style: italic;
            color: var(--card-text-color);
        }
        .ps-testimonial-compact-author {
            display: inline;
            font-style: normal;
            font-size: .88rem;
            font-weight: 600;
            color: var(--card-text-color);
        }
        ";

        if (!empty($params["hide_controls"])) {
            $css .= "
        .ps-testimonial-compact-slider .slick-arrow,
        .ps-testimonial-compact-slider .slick-dots {
            display: none !important;
        }
        ";
        }

        return self::wrap_slider(
            $css . $items,
            strtolower($params["slider"] ?? "none"),
            !empty($params["wrapper_only"]),
            !empty($params["full_width"]),
            "ps-testimonial-compact-slider",
            intval($params["slides_to_show"] ?? 2),
        );
    }

    /**
     * V2 — Twitter/X-style tweet cards (white background, avatar, engagement counts).
     */
    private static function render_tweet($params = [])
    {
        $count = max(1, intval($params["count"] ?? 5));
        $custom = $params["testimonials"] ?? [];
        $wrapper_only = !empty($params["wrapper_only"]);
        $skin = $params["skin"] ?? "light";
        $slider = strtolower(
            $params["slider"] ?? ($wrapper_only ? "none" : "native"),
        );

        $testimonials = !empty($custom)
            ? $custom
            : self::default_testimonials();

        [$portraits_men, $portraits_women] = self::portrait_pools($count);
        $m_idx = 0;
        $w_idx = 0;

        $engagement = [
            ["replies" => 3, "likes" => 24],
            ["replies" => 5, "likes" => 48],
            ["replies" => 2, "likes" => 15],
            ["replies" => 7, "likes" => 68],
            ["replies" => 1, "likes" => 9],
        ];

        $timestamps = ["23h", "2d", "1d", "4h", "3d"];

        $cards = "";
        foreach ($testimonials as $idx => $t) {
            if ($idx >= $count) {
                break;
            }

            $author = esc_html($t["author"]);
            $content = esc_html(trim($t["content"], '"'));
            $eng = $engagement[$idx % count($engagement)];
            $replies = $eng["replies"];
            $likes = $eng["likes"];
            $ts = $timestamps[$idx % count($timestamps)];

            $av_url = self::resolve_avatar(
                $t,
                $idx,
                $portraits_men,
                $portraits_women,
                $m_idx,
                $w_idx,
            );

            // Extract username (handle)
            $username_raw = $t["username"] ?? null;
            if (!$username_raw) {
                $suffixes = [
                    "_dev",
                    "_write",
                    "_design",
                    "_ux",
                    "_code",
                    "_builds",
                    "Write",
                ];
                $suffix = $suffixes[$idx % count($suffixes)];
                $username_raw =
                    strtolower(str_replace(" ", "", $author)) . $suffix;
            }
            $username_raw = ltrim($username_raw, "@");
            $handle = "@" . $username_raw;

            // Generate views and reposts based on likes/replies
            $reposts = round($replies * 0.8);
            $views_count = $likes * 14 + 17;
            $views =
                $views_count >= 1000
                    ? round($views_count / 1000, 1) . "K"
                    : $views_count;

            $cards .=
                '
            <div class="b-testimonial b-testimonial-tweet">
                <div class="btt-avatar-col">
                    <div class="btt-avatar-wrap">
                        <img src="' .
                esc_url($av_url) .
                '" alt="' .
                $author .
                '" class="btt-avatar-img">
                    </div>
                </div>
                <div class="btt-main-col">
                    <div class="btt-header">
                        <div class="btt-header-left">
                            <span class="btt-name">' .
                $author .
                '</span>
                            <span class="btt-verified-badge">
                                <svg viewBox="0 0 22 22" aria-label="Verified account" role="img" width="19" height="19" fill="#536471" class="r-4qtqp9 r-yyyyoo r-1xvli5t r-bnwqim r-lrvibr r-m6rgpd r-1cvl2hr r-f9ja8p r-og9te1 r-3t4u6i" data-testid="icon-verified"><g><path d="M20.396 11c-.018-.646-.215-1.275-.57-1.816-.354-.54-.852-.972-1.438-1.246.223-.607.27-1.264.14-1.897-.131-.634-.437-1.218-.882-1.687-.47-.445-1.053-.75-1.687-.882-.633-.13-1.29-.083-1.897.14-.273-.587-.704-1.086-1.245-1.44S11.647 1.62 11 1.604c-.646.017-1.273.213-1.813.568s-.969.854-1.24 1.44c-.608-.223-1.267-.272-1.902-.14-.635.13-1.22.436-1.69.882-.445.47-.749 1.055-.878 1.688-.13.633-.08 1.29.144 1.896-.587.274-1.087.705-1.443 1.245-.356.54-.555 1.17-.574 1.817.02.647.218 1.276.574 1.817.356.54.856.972 1.443 1.245-.224.606-.274 1.263-.144 1.896.13.634.433 1.218.877 1.688.47.443 1.054.747 1.687.878.633.132 1.29.084 1.897-.136.274.586.705 1.084 1.246 1.439.54.354 1.17.551 1.816.569.647-.016 1.276-.213 1.817-.567s.972-.854 1.245-1.44c.604.239 1.266.296 1.903.164.636-.132 1.22-.447 1.68-.907.46-.46.776-1.044.908-1.681s.075-1.299-.165-1.903c.586-.274 1.084-.705 1.439-1.246.354-.54.551-1.17.569-1.816zM9.662 14.85l-3.429-3.428 1.293-1.302 2.072 2.072 4.4-4.794 1.347 1.246z"></path></g></svg>
                            </span>
                            <span class="btt-username">' .
                esc_html($handle) .
                '</span>
                            <span class="btt-dot">·</span>
                            <span class="btt-ts">' .
                esc_html($ts) .
                '</span>
                        </div>
                        <div class="btt-header-right">
                            <button class="btt-not-interested" aria-label="Not interested" tabindex="-1">
                                <svg viewBox="0 0 33 32" aria-hidden="true" width="19" height="19" fill="currentColor" class="r-4qtqp9 r-yyyyoo r-1xvli5t r-dnmrzs r-bnwqim r-lrvibr r-m6rgpd"><g><path d="M12.745 20.54l10.97-8.19c.539-.4 1.307-.244 1.564.38 1.349 3.288.746 7.241-1.938 9.955-2.683 2.714-6.417 3.31-9.83 1.954l-3.728 1.745c5.347 3.697 11.84 2.782 15.898-1.324 3.219-3.255 4.216-7.692 3.284-11.693l.008.009c-1.351-5.878.332-8.227 3.782-13.031L33 0l-4.54 4.59v-.014L12.743 20.544m-2.263 1.987c-3.837-3.707-3.175-9.446.1-12.755 2.42-2.449 6.388-3.448 9.852-1.979l3.72-1.737c-.67-.49-1.53-1.017-2.515-1.387-4.455-1.854-9.789-.931-13.41 2.728-3.483 3.523-4.579 8.94-2.697 13.561 1.405 3.454-.899 5.898-3.22 8.364C1.49 30.2.666 31.074 0 32l10.478-9.466"></path></g></svg>
                            </button>
                            <button class="btt-dots" aria-label="More options" tabindex="-1">
                                <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="currentColor"><g><path d="M3 12c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm9 2c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm7 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"></path></g></svg>
                            </button>
                        </div>
                    </div>
                    <div class="btt-body">' .
                $content .
                '</div>
                    <div class="btt-actions">
                        <button class="btt-action btt-comment" aria-label="Reply" tabindex="-1">
                            <span class="btt-action-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true" width="19" height="19" fill="currentColor" class="r-4qtqp9 r-yyyyoo r-dnmrzs r-bnwqim r-lrvibr r-m6rgpd r-1xvli5t r-1hdv0qi"><g><path d="M1.751 10c0-4.42 3.584-8 8.005-8h4.366c4.49 0 8.129 3.64 8.129 8.13 0 2.96-1.607 5.68-4.196 7.11l-8.054 4.46v-3.69h-.067c-4.49.1-8.183-3.51-8.183-8.01zm8.005-6c-3.317 0-6.005 2.69-6.005 6 0 3.37 2.77 6.08 6.138 6.01l.351-.01h1.761v2.3l5.087-2.81c1.951-1.08 3.163-3.13 3.163-5.36 0-3.39-2.744-6.13-6.129-6.13H9.756z"></path></g></svg>
                            </span>
                            ' .
                ($replies > 0
                    ? '<span class="btt-action-count">' . $replies . "</span>"
                    : "") .
                '
                        </button>
                        <button class="btt-action btt-repost" aria-label="Repost" tabindex="-1">
                            <span class="btt-action-icon">
                               <svg viewBox="0 0 24 24" aria-hidden="true" width="19" height="19" fill="currentColor" class="r-4qtqp9 r-yyyyoo r-dnmrzs r-bnwqim r-lrvibr r-m6rgpd r-1xvli5t r-1hdv0qi"><g><path d="M4.5 3.88l4.432 4.14-1.364 1.46L5.5 7.55V16c0 1.1.896 2 2 2H13v2H7.5c-2.209 0-4-1.79-4-4V7.55L1.432 9.48.068 8.02 4.5 3.88zM16.5 6H11V4h5.5c2.209 0 4 1.79 4 4v8.45l2.068-1.93 1.364 1.46-4.432 4.14-4.432-4.14 1.364-1.46 2.068 1.93V8c0-1.1-.896-2-2-2z"></path></g></svg>
                            </span>
                            ' .
                ($reposts > 0
                    ? '<span class="btt-action-count">' . $reposts . "</span>"
                    : "") .
                '
                        </button>
                        <button class="btt-action btt-heart" aria-label="Like" tabindex="-1">
                            <span class="btt-action-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true" width="19" height="19" fill="currentColor" class="r-4qtqp9 r-yyyyoo r-dnmrzs r-bnwqim r-lrvibr r-m6rgpd r-1xvli5t r-1hdv0qi"><g><path d="M16.697 5.5c-1.222-.06-2.679.51-3.89 2.16l-.805 1.09-.806-1.09C9.984 6.01 8.526 5.44 7.304 5.5c-1.243.07-2.349.78-2.91 1.91-.552 1.12-.633 2.78.479 4.82 1.074 1.97 3.257 4.27 7.129 6.61 3.87-2.34 6.052-4.64 7.126-6.61 1.111-2.04 1.03-3.7.477-4.82-.561-1.13-1.666-1.84-2.908-1.91zm4.187 7.69c-1.351 2.48-4.001 5.12-8.379 7.67l-.503.3-.504-.3c-4.379-2.55-7.029-5.19-8.382-7.67-1.36-2.5-1.41-4.86-.514-6.67.887-1.79 2.647-2.91 4.601-3.01 1.651-.09 3.368.56 4.798 2.01 1.429-1.45 3.146-2.1 4.796-2.01 1.954.1 3.714 1.22 4.601 3.01.896 1.81.846 4.17-.514 6.67z"></path></g></svg>
                            </span>
                            ' .
                ($likes > 0
                    ? '<span class="btt-action-count">' . $likes . "</span>"
                    : "") .
                '
                        </button>
                        <button class="btt-action btt-analytics" aria-label="Views" tabindex="-1">
                            <span class="btt-action-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true" width="19" height="19" fill="currentColor" class="r-4qtqp9 r-yyyyoo r-dnmrzs r-bnwqim r-lrvibr r-m6rgpd r-1xvli5t r-1hdv0qi"><g><path d="M8.75 21V3h2v18h-2zM18 21V8.5h2V21h-2zM4 21l.004-10h2L6 21H4zm9.248 0v-7h2v7h-2z"></path></g></svg>
                            </span>
                            <span class="btt-action-count">' .
                $views .
                '</span>
                        </button>
                        <div class="btt-actions-right">
                            <button class="btt-action btt-bookmark" aria-label="Bookmark" tabindex="-1">
                                <span class="btt-action-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="currentColor"><g><path d="M4 4.5C4 3.12 5.119 2 6.5 2h11C18.881 2 20 3.12 20 4.5v18.44l-8-5.71-8 5.71V4.5zM6.5 4c-.276 0-.5.22-.5.5v14.56l6-4.29 6 4.29V4.5c0-.28-.224-.5-.5-.5h-11z"></path></g></svg>
                                </span>
                            </button>
                            <button class="btt-action btt-share" aria-label="Share" tabindex="-1">
                                <span class="btt-action-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" width="18" height="18" fill="currentColor"><g><path d="M12 2.59l5.7 5.7-1.41 1.42L13 6.41V16h-2V6.41L7.71 9.71 6.3 8.29 12 2.59zM4.5 14H3v5c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-5h-1.5v5H5v-5z"></path></g></svg>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>';
        }

        $c = self::colors($skin);
        $bg = $c["tweet_bg"];
        $text = $c["tweet_text"];
        $muted = $c["tweet_muted"];
        $border = $c["tweet_border"];

        $css =
            "
        .b-testimonial-tweet {
            display: flex !important;
            gap: 12px;
            background: {$bg};
            border: 1px solid {$border};
            border-radius: var(--card-border-radius, 16px);
            padding: 16px;
            text-align: left;
            font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;
            color: {$text};
            box-shadow: var(--card-box-shadow, 0 1px 3px rgba(0,0,0,.05));
            max-width: 550px;
        }
        @media (max-width: 768px) {
            .b-testimonial-tweet {
                gap: 10px;
            }
        }
        .b-testimonial-tweet .btt-avatar-col {
            flex-shrink: 0;
        }
        .b-testimonial-tweet .btt-avatar-wrap {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
        }
        .b-testimonial-tweet .btt-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .b-testimonial-tweet .btt-main-col {
            flex: 1;
            min-width: 0;
        }
        .b-testimonial-tweet .btt-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 2px;
            line-height: 20px;
        }
        .b-testimonial-tweet .btt-header-left {
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 0;
            flex-wrap: wrap;
        }
        .b-testimonial-tweet .btt-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: {$text};
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-flex;
            align-items: center;
        }
        .b-testimonial-tweet .btt-verified-badge {
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
            margin-left: 2px;
            color: #1d9bf0;
        }
        .b-testimonial-tweet .btt-username {
            font-size: 0.9rem;
            color: {$muted};
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .b-testimonial-tweet .btt-dot {
            font-size: 0.9rem;
            color: {$muted};
            flex-shrink: 0;
        }
        .b-testimonial-tweet .btt-ts {
            font-size: 0.9rem;
            color: {$muted};
            flex-shrink: 0;
        }
        .b-testimonial-tweet .btt-header-right {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
            margin-top: -6px;
            margin-right: -6px;
        }
        .b-testimonial-tweet .btt-not-interested,
        .b-testimonial-tweet .btt-dots {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 50%;
            color: {$muted};
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s, color 0.2s;
            pointer-events: auto;
            outline: none;
        }
        .b-testimonial-tweet .btt-not-interested:hover,
        .b-testimonial-tweet .btt-dots:hover {
            background-color: " .
            ($skin === "dark"
                ? "rgba(29, 155, 240, 0.1)"
                : "rgba(29, 155, 240, 0.08)") .
            ";
            color: #1d9bf0;
        }
        .b-testimonial-tweet .btt-body {
            font-size: 0.95rem;
            line-height: 1.5;
            color: {$text};
            margin-top: 2px;
            margin-bottom: 12px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .b-testimonial-tweet .btt-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 12px;
            margin-left: -8px;
        }
        .b-testimonial-tweet .btt-actions-right {
            display: flex;
            align-items: center;
        }
        .b-testimonial-tweet .btt-action {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            color: {$muted};
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
            transition: color 0.2s;
            pointer-events: auto;
            outline: none;
        }
        .b-testimonial-tweet .btt-action-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            transition: background-color 0.2s;
        }
        .b-testimonial-tweet .btt-action-count {
            transition: color 0.2s;
            font-size: 0.8rem;
            font-weight: 400;
        }

        .b-testimonial-tweet .btt-comment:hover {
            color: #1d9bf0;
        }
        .b-testimonial-tweet .btt-comment:hover .btt-action-icon {
            background-color: " .
            ($skin === "dark"
                ? "rgba(29, 155, 240, 0.1)"
                : "rgba(29, 155, 240, 0.08)") .
            ";
        }

        .b-testimonial-tweet .btt-repost:hover {
            color: #00ba7c;
        }
        .b-testimonial-tweet .btt-repost:hover .btt-action-icon {
            background-color: " .
            ($skin === "dark"
                ? "rgba(0, 186, 124, 0.1)"
                : "rgba(0, 186, 124, 0.08)") .
            ";
        }

        .b-testimonial-tweet .btt-heart:hover {
            color: #f91880;
        }
        .b-testimonial-tweet .btt-heart:hover .btt-action-icon {
            background-color: " .
            ($skin === "dark"
                ? "rgba(249, 24, 128, 0.1)"
                : "rgba(249, 24, 128, 0.08)") .
            ";
        }

        .b-testimonial-tweet .btt-analytics:hover {
            color: #1d9bf0;
        }
        .b-testimonial-tweet .btt-analytics:hover .btt-action-icon {
            background-color: " .
            ($skin === "dark"
                ? "rgba(29, 155, 240, 0.1)"
                : "rgba(29, 155, 240, 0.08)") .
            ";
        }

        .b-testimonial-tweet .btt-bookmark:hover {
            color: #1d9bf0;
        }
        .b-testimonial-tweet .btt-bookmark:hover .btt-action-icon {
            background-color: " .
            ($skin === "dark"
                ? "rgba(29, 155, 240, 0.1)"
                : "rgba(29, 155, 240, 0.08)") .
            ";
        }

        .b-testimonial-tweet .btt-share:hover {
            color: #1d9bf0;
        }
        .b-testimonial-tweet .btt-share:hover .btt-action-icon {
            background-color: " .
            ($skin === "dark"
                ? "rgba(29, 155, 240, 0.1)"
                : "rgba(29, 155, 240, 0.08)") .
            ";
        }
        ";

        return self::wrap_slider(
            $css . $cards,
            $slider,
            $wrapper_only,
            !empty($params["full_width"]),
            "gwt-slick-slider",
            intval($params["slides_to_show"] ?? 2),
            isset($params["wrapper"]) ? !empty($params["wrapper"]) : true,
        );
    }

    private static function render_simple($params = [])
    {
        $count = max(1, intval($params["count"] ?? 3));
        $custom = $params["testimonials"] ?? [];
        $wrapper_only = !empty($params["wrapper_only"]);
        $slider = strtolower(
            $params["slider"] ?? ($wrapper_only ? "none" : "slick"),
        );

        $testimonials = !empty($custom)
            ? $custom
            : self::default_testimonials();

        $gradients = [
            "var(--ps-gradient-cta)",
            "linear-gradient(135deg, var(--ps-color-secondary-dark, #06b6d4), var(--ps-color-secondary, #22d3ee))",
            "linear-gradient(135deg, var(--ps-color-soft, #818cf8), var(--ps-color-warm, #f472b6))",
            "linear-gradient(135deg, #6366f1, #a855f7)",
            "linear-gradient(135deg, #f59e0b, #ef4444)",
            "linear-gradient(135deg, #10b981, #06b6d4)",
        ];

        $cards = "";
        foreach ($testimonials as $idx => $t) {
            if ($idx >= $count) {
                break;
            }

            $name = esc_html($t["name"] ?? ($t["author"] ?? ""));
            $quote = esc_html($t["quote"] ?? ($t["content"] ?? ""));
            $role = esc_html($t["role"] ?? "");
            $stars = $t["stars"] ?? "★★★★★";

            // Auto-generate initials from name
            if (!empty($t["initials"])) {
                $initials = esc_html($t["initials"]);
            } else {
                $words = preg_split("/\s+/", trim($name));
                $initials = "";
                foreach ($words as $w) {
                    if (strlen($w) > 0) {
                        $initials .= strtoupper(mb_substr($w, 0, 1));
                    }
                }
                $initials = mb_substr($initials, 0, 2);
            }

            // Avatar background gradient — use provided, then cycle defaults
            $avatar_bg = !empty($t["avatar_bg"])
                ? esc_attr($t["avatar_bg"])
                : $gradients[$idx % count($gradients)];

            $cards .=
                '
            <figure class="ps-testi card" style="margin:0;">
                <div class="ps-stars">' .
                $stars .
                '</div>
                <blockquote class="ps-testi-q">' .
                $quote .
                '</blockquote>
                <div class="ps-testi-who">
                    <div class="ps-testi-av" style="background:' .
                $avatar_bg .
                ';">' .
                $initials .
                '</div>
                    <div>
                        <p class="ps-testi-name">' .
                $name .
                '</p>' .
                ($role
                    ? '
                        <p class="ps-testi-role">' . $role . "</p>"
                    : "") .
                '
                    </div>
                </div>
            </figure>';
        }

        $css = '
        .ps-testis { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
        @media(max-width:860px){ .ps-testis{ grid-template-columns:1fr; } }

        .ps-testi { padding:28px; transition:all .22s; }
        .ps-testi:hover { transform:translateY(-3px); border-color:var(--ps-border-hover); background:var(--ps-bg-glass-hover); }
        .ps-stars { color:#fbbf24; letter-spacing:2px; font-size:.85rem; margin-bottom:14px; }
        .ps-testi-q { font-size:.95rem; color:var(--ps-text); line-height:1.78; margin:0 0 22px; }
        .ps-testi-q::before { content:\'\\201C\'; font-size:1.8rem; color:var(--ps-color-primary); line-height:0; vertical-align:-10px; margin-right:2px; }
        .ps-testi-who { display:flex; align-items:center; gap:12px; }
        .ps-testi-av  { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:800; color:#fff; flex-shrink:0; }
        .ps-testi-name{ font-size:.88rem; font-weight:700; margin:0; }
        .ps-testi-role{ font-size:.78rem; color:var(--ps-text-muted); margin:0; }
        ';

        if ($wrapper_only) {
            return "<style>" . $css . "</style>" . $cards;
        }

        // Static grid — no slider
        if ($slider === "none") {
            return "<style>" . $css . "</style><div class=\"ps-testis\">" . $cards . "</div>";
        }

        // Slider mode: emit CSS ourselves then pass only cards to wrap_slider
        return "<style>" . $css . "</style>" . self::wrap_slider(
            $cards,
            $slider,
            $wrapper_only,
            !empty($params["full_width"]),
            "gwt-slick-slider",
            intval($params["slides_to_show"] ?? 2),
            isset($params["wrapper"]) ? !empty($params["wrapper"]) : true,
        );
    }

    /**
     * Shared slider wrapper for both versions.
     */
    private static function wrap_slider(
        $html,
        $slider,
        $wrapper_only,
        $full_width = false,
        $class = "gwt-slick-slider",
        $slides_to_show = 2,
        $wrapper = true,
    ) {
        $style = "";
        $cards = $html;
        $pos = strpos($html, "<div");
        if ($pos !== false) {
            $style = "<style>" . trim(substr($html, 0, $pos)) . "</style>";
            $cards = substr($html, $pos);
        } elseif (preg_match("/^(\s*[^{}]+\{[^}]*\}\s*)+/s", $html, $m)) {
            $style = "<style>" . $m[0] . "</style>";
            $cards = substr($html, strlen($m[0]));
        }

        if ($slider === "none" || $wrapper_only) {
            if ($wrapper) {
                $wrapper_style = "<style>
                    .ps-testimonials-wrapper {
                        display: grid;
                        grid-template-columns: repeat(3, minmax(0, 1fr));
                        gap: 10px;
                        align-items: flex-start;
                    }
                    @media (max-width: 768px) {
                        .ps-testimonials-wrapper {
                            grid-template-columns: repeat(1, minmax(0, 1fr));
                        }
                    }
                </style>";
                return $style .
                    $wrapper_style .
                    '<div class="ps-testimonials-wrapper">' .
                    $cards .
                    "</div>";
            }
            return $style . $cards;
        }

        $uid = "gwt-" . wp_rand(1000, 9999);
        $fw_css = $full_width ? ".{$class} .slick-list{overflow:visible}" : "";

        if ($slider === "slick") {
            $slick_css = '
            .gwt-slick-slider:not(.slick-initialized) { opacity: 0; }
            .gwt-slick-slider { margin-bottom: 90px!important; transition: opacity .25s; }
            .gwt-slick-slider .b-testimonial { margin: 0 8px; }
            .gwt-slick-slider .slick-dots {
                padding-top: 44px;
                display: flex !important;
                justify-content: center;
                gap: 10px;
                list-style: none;
                margin: 0;
                padding-left: 0;
                bottom: -50px;
            }
            .gwt-slick-slider .slick-dots li { margin: 0; width: auto; height: auto; }
            .gwt-slick-slider .slick-dots li button {
                width: 10px; height: 10px; padding: 0; border-radius: 50%;
                border: 1.5px solid var(--ps-border, rgba(255, 255, 255, .2));
                background: transparent; font-size: 0; cursor: pointer; transition: all .25s;
            }
            .gwt-slick-slider .slick-dots li button:before { display: none; }
            .gwt-slick-slider .slick-dots li.slick-active button {
                width: 24px; border-radius: 100px;
                background: var(--link-color, var(--theme-color, #1d9bf0));
                border-color: var(--link-color, var(--theme-color, #1d9bf0));
            }
            ';
            $computed_slides = $slides_to_show ?? 2;
            // Responsive breakpoints must be ordered largest → smallest (Slick requirement).
            // Extra breakpoints only needed for the 4-slide full-width layout.
            $responsive_breakpoints =
                $full_width && $slides_to_show === null
                    ? "[{ breakpoint: 1200, settings: { slidesToShow: 3 } },{ breakpoint: 900, settings: { slidesToShow: 2 } },{ breakpoint: 768, settings: { slidesToShow: 1 } }]"
                    : "[{ breakpoint: 768, settings: { slidesToShow: 1 } }]";

            return $style .
                '
            <style>' .
                $slick_css .
                ($fw_css ? $fw_css : "") .
                '</style>
            <div class="gwt-testimonials ' .
                $class .
                '" id="' .
                $uid .
                '">' .
                $cards .
                '</div>
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof jQuery === "undefined" || typeof jQuery.fn.slick === "undefined") return;
                var $slider = jQuery("#' .
                $uid .
                '");
                if (!$slider.length || $slider.find("> *").length === 0) return;
                $slider.slick({
                    slidesToShow: ' .
                $computed_slides .
                ',
                    slidesToScroll: 1,
                    dots: true,
                    arrows: false,
                    autoplay: true,
                    autoplaySpeed: 4500,
                    pauseOnHover: true,
                    centerMode: false,
                    responsive: ' .
                $responsive_breakpoints .
                '
                });
            });
            </script>';
        }

        return $style .
            '
        <div class="gwt-testimonials" id="' .
            $uid .
            '">
            <div class="gwt-slider">' .
            $cards .
            '</div>
        </div>
        <style>.gwt-slider{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;gap:16px;padding-bottom:24px;scrollbar-width:none}.gwt-slider::-webkit-scrollbar{display:none}.gwt-slider>*{flex:0 0 100%;scroll-snap-align:start}</style>';
    }
}
