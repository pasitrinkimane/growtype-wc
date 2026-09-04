<?php

class Growtype_Wc_Banner_Shortcode
{
    function __construct()
    {
        if (!is_admin() && !wp_is_json_request()) {
            add_shortcode('growtype_wc_banner', array ($this, 'shortcode'));
        }
    }

    /**
     * Return the canonical seasonal promotion periods for a reference date.
     *
     * Other components, such as landing-page carousels, should consume this
     * method instead of copying campaign dates.
     *
     * @param DateTimeInterface|string|null $reference
     * @return array
     */
    public static function get_discount_periods($reference = null)
    {
        $timezone = wp_timezone();

        try {
            $reference = $reference instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($reference)->setTimezone($timezone)
                : new DateTimeImmutable((string) ($reference ?? 'now'), $timezone);
        } catch (Exception $exception) {
            $reference = current_datetime();
        }

        $year = (int) $reference->format('Y');
        $new_year_start_year = (int) $reference->format('n') === 1 ? $year - 1 : $year;
        $date = static function (int $date_year, string $month_day) use ($timezone): string {
            return (new DateTimeImmutable($date_year . '-' . $month_day, $timezone))->format('Y-m-d');
        };
        $easter = (new DateTimeImmutable($year . '-03-21', $timezone))
            ->modify('+' . easter_days($year) . ' days');
        $back_to_school_start = new DateTimeImmutable($year . '-07-15', $timezone);
        $back_to_school_end = new DateTimeImmutable($year . '-10-01', $timezone);
        $summer_end = $back_to_school_start->modify('-1 day');
        $halloween_start = $back_to_school_end->modify('+1 day');
        $thanksgiving = (new DateTimeImmutable($year . '-11-01', $timezone))
            ->modify('first thursday of this month')
            ->modify('+3 weeks');
        $black_friday = $thanksgiving->modify('+1 day');
        $cyber_monday = $black_friday->modify('+3 days');
        $black_friday_end = $cyber_monday->modify('-1 day');
        $cyber_monday_end = $cyber_monday->modify('+3 days');
        $christmas_start = $cyber_monday_end->modify('+1 day');

        $periods = [
            'halloween' => [
                'start' => $halloween_start->format('Y-m-d'),
                'end' => $date($year, '10-31'),
                'intro_title' => 'Spooky Savings',
                'intro_subtitle' => 'Limited time offer',
                'background_images' => [GROWTYPE_WC_URL_PUBLIC . 'images/banners/halloween/graveyard.png'],
                'decor' => [GROWTYPE_WC_URL_PUBLIC . 'images/banners/halloween/pumpkin.svg'],
                'font' => [
                    'family' => 'Rubik Wet Paint',
                    'url' => 'https://fonts.googleapis.com/css2?family=Rubik+Wet+Paint&display=swap',
                ],
            ],
            'black_friday' => [
                'start' => $black_friday->modify('-11 days')->format('Y-m-d'),
                'end' => $black_friday_end->format('Y-m-d'),
                'intro_title' => 'Black Friday Deal',
                'intro_subtitle' => 'Exclusive offer',
                'background_images' => [GROWTYPE_WC_URL_PUBLIC . 'images/banners/friday/bg.jpg'],
                'font' => [
                    'family' => 'Tiny5',
                    'url' => 'https://fonts.googleapis.com/css2?family=Tiny5&display=swap',
                ],
                'styles' => ['background-position: top'],
            ],
            'cyber_monday' => [
                'start' => $cyber_monday->format('Y-m-d'),
                'end' => $cyber_monday_end->format('Y-m-d'),
                'intro_title' => 'Cyber Monday',
                'intro_subtitle' => 'Limited time offer',
                'background_images' => [GROWTYPE_WC_URL_PUBLIC . 'images/banners/friday/bg.jpg'],
                'font' => [
                    'family' => 'Tiny5',
                    'url' => 'https://fonts.googleapis.com/css2?family=Tiny5&display=swap',
                ],
                'styles' => ['background-position: top'],
            ],
            'christmas' => [
                'start' => $christmas_start->format('Y-m-d'),
                'end' => $date($year, '12-26'),
                'intro_title' => 'Merry Deals Await!',
                'intro_subtitle' => 'Limited time offer',
                'background_images' => [GROWTYPE_WC_URL_PUBLIC . 'images/banners/christmas/banner.png'],
                'font' => [
                    'family' => 'Mountains of Christmas',
                    'url' => 'https://fonts.googleapis.com/css2?family=Mountains+of+Christmas:wght@400;700&display=swap',
                ],
                'styles' => ['background-position: top'],
            ],
            'new_year' => [
                'start' => $date($new_year_start_year, '12-27'),
                'end' => $date($new_year_start_year + 1, '01-07'),
                'intro_title' => 'New Year, New Savings!',
                'intro_subtitle' => "Don't Miss Out",
                'background_images' => [GROWTYPE_WC_URL_PUBLIC . 'images/banners/newyear/happy.jpg'],
            ],
            'valentines' => [
                'start' => $date($year, '01-29'),
                'end' => $date($year, '02-14'),
                'intro_title' => 'Show Some Love!',
                'intro_subtitle' => 'Valentine’s Day Special',
                'font' => [
                    'family' => 'Pacifico',
                    'url' => 'https://fonts.googleapis.com/css2?family=Pacifico&display=swap',
                ],
                'background_images' => [GROWTYPE_WC_URL_PUBLIC . 'images/banners/valentines/pink.jpg'],
            ],
            'easter' => [
                'start' => $easter->modify('-14 days')->format('Y-m-d'),
                'end' => $easter->modify('+2 days')->format('Y-m-d'),
                'intro_title' => 'Easter Sale',
                'intro_subtitle' => 'Hop into Savings this Easter!',
                'background_images' => [GROWTYPE_WC_URL_PUBLIC . 'images/banners/spring/pink.jpg'],
                'font' => [
                    'family' => 'Itim',
                    'url' => 'https://fonts.googleapis.com/css2?family=Itim&display=swap',
                ],
            ],
            'back_to_school' => [
                'start' => $back_to_school_start->format('Y-m-d'),
                'end' => $back_to_school_end->format('Y-m-d'),
                'intro_title' => 'Back to School',
                'intro_subtitle' => 'Gear Up for School!',
                'background_images' => [GROWTYPE_WC_URL_PUBLIC . 'images/banners/school/intro.jpg'],
                'font' => [
                    'family' => 'Itim',
                    'url' => 'https://fonts.googleapis.com/css2?family=Itim&display=swap',
                ],
            ],
            'summer' => [
                'start' => $date($year, '06-01'),
                'end' => $summer_end->format('Y-m-d'),
                'intro_title' => 'Summer Sale',
                'intro_subtitle' => 'Sizzling Summer Deals!',
                'background_images' => [GROWTYPE_WC_URL_PUBLIC . 'images/banners/summer/relax.jpg'],
                'font' => [
                    'family' => 'Rubik Gemstones',
                    'url' => 'https://fonts.googleapis.com/css2?family=Rubik+Gemstones&display=swap',
                ],
            ],
        ];

        return $periods;
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
        $params['id'] = $params['id'] ?? uniqid();
        $params['id'] = 'growtype-wc-banner-' . $params['id'];
        $params['countdown_duration'] = $params['countdown_duration'] ?? '600';
        $params['show_countdown'] = filter_var($params['show_countdown'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $params['hide_on_countdown_expired'] = filter_var($params['hide_on_countdown_expired'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $params['show_default_banner'] = filter_var($params['show_default_banner'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $params['type'] = $params['type'] ?? $_GET['growtype_wc_banner_type'] ?? 'default';
        $params['style'] = $params['style'] ?? $_GET['growtype_wc_banner_style'] ?? 'default';
        $params['url'] = $params['url'] ?? '';
        $params['discount_label'] = $params['discount_label'] ?? '';
        $params['discount_periods_enabled'] = filter_var($params['discount_periods_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $params['show_only_when_discount_period'] = filter_var($params['show_only_when_discount_period'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $params['discount_amount'] = $params['discount_amount'] ?? '65';
        $params['discount_amount'] = apply_filters('growtype_wc_banner_discount_amount', $params['discount_amount'], $params);

        $current_date = current_time('Y-m-d');

        if ($params['discount_periods_enabled']) {
            $discount_periods = self::get_discount_periods();
            $discount_periods = apply_filters(
                'growtype_wc_banner_discount_periods',
                $discount_periods,
                current_datetime()
            );
            $discount_periods = !empty($discount_periods) ? $discount_periods : [];

            $current_banner = [];

            if (!empty($params['type'])) {
                $current_banner = $discount_periods[$params['type']] ?? [];
            }

            if (empty($current_banner)) {
                $discount_period_is_active = false;
                foreach ($discount_periods as $key => $period) {
                    if ($current_date >= $period['start'] && $current_date <= $period['end']) {
                        $current_banner = $period;
                        $params['type'] = $key;
                        $discount_period_is_active = true;
                        break;
                    }
                }

                if (!$discount_period_is_active && $params['show_only_when_discount_period']) {
                    return '';
                }
            }
        }

        if (empty($current_banner)) {
            if ($params['show_default_banner']) {
                $current_banner = apply_filters('growtype_wc_banner_default', [
                    'intro_title' => 'Hurry Up!',
                    'intro_subtitle' => 'Limited-Time Offer'
                ], $params);
            } else {
                return '';
            }
        }

        if (!empty($params['discount_label'])) {
            $current_banner['discount_label'] = $params['discount_label'];
        }

        if (!isset($current_banner['url'])) {
            $current_banner['url'] = $params['url'];
        }

        if (!isset($current_banner['discount_label'])) {
            $current_banner['discount_label'] = sprintf(__('GET %s OFF', 'growtype-wc'), $params['discount_amount'] . '%');
        }

        if (!isset($current_banner['show_countdown'])) {
            $current_banner['show_countdown'] = $params['show_countdown'];
        }

        if (!isset($current_banner['countdown'])) {
            $current_banner['countdown'] = [
                'duration' => $params['countdown_duration'],
                'labels' => ['Years', 'Months', 'Weeks', 'Days', 'Hr', 'Min', 'Sec']
            ];
        }

        $current_banner = apply_filters('growtype_wc_banner_details', $current_banner, $params);

        $styles = $current_banner['styles'] ?? [];

        if (!empty($current_banner['background_images'])) {
            $styles[] = "background-image: url('" . esc_url($current_banner['background_images'][array_rand($current_banner['background_images'])]) . "');background-size: cover;background-position: center;";
        }

        if ($params['show_countdown']) {
            $styles[] = 'display: none;';
        }

        $styles = array_reverse($styles);

        $style_attribute = !empty($styles) ? 'style="' . implode('; ', $styles) . ';"' : '';

        $rendered_banner = '';

        if (!empty($current_banner)) {
            ob_start();
            ?>
            <div id="<?php echo $params['id']; ?>"
                 class="growtype-wc-banner"
                <?php echo $style_attribute; ?>
                 data-type="<?php echo $params['type'] ?>"
                 data-style="<?php echo $params['style'] ?>"
                 data-url="<?php echo esc_url($current_banner['url'] ?? ''); ?>"
            >
                <div class="banner-content">
                    <div class="banner-content-inner">
                        <div class="banner-content-intro">
                            <?php if (isset($current_banner['intro_title'])) { ?>
                                <h3 class="e-title"><?php echo esc_html($current_banner['intro_title']); ?></h3>
                            <?php } ?>
                            <?php if (isset($current_banner['intro_subtitle'])) { ?>
                                <p class="e-subtitle"><?php echo esc_html($current_banner['intro_subtitle']); ?></p>
                            <?php } ?>
                            <?php if (isset($current_banner['discount_label'])) { ?>
                                <p class="banner-content-discount anim-pulse">
                                    <?php echo $current_banner['discount_label']; ?>
                                </p>
                            <?php } ?>
                        </div>

                        <?php if (isset($current_banner['discount_label'])) { ?>
                            <p class="banner-content-discount anim-pulse">
                                <?php echo $current_banner['discount_label']; ?>
                            </p>
                        <?php } ?>

                        <?php
                        if (
                            !empty($current_banner['show_countdown']) &&
                            !empty($current_banner['countdown'])
                        ) {
                            $countdown = $current_banner['countdown'];
                            $compact = filter_var($countdown['compact'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                            $time = intval($current_banner['duration'] ?? 600);
                            $format = sanitize_text_field($countdown['format'] ?? 'HMS');
                            $labels = !empty($countdown['labels'])
                                ? implode(',', array_map('sanitize_text_field', $countdown['labels']))
                                : '';
                            $description = sanitize_text_field($countdown['description'] ?? 'Offer ends in');

                            // build the shortcode string in one shot
                            $shortcode = sprintf(
                                '[growtype_wc_countdown compact="%s" time="%d" format="%s" labels="%s" description="%s"]',
                                $compact,
                                $time,
                                $format,
                                $labels,
                                $description
                            );

                            echo '<div class="banner-content-timer">';
                            echo do_shortcode($shortcode);
                            echo '</div>';
                        }
                        ?>

                        <?php if (isset($current_banner['decor']) && !empty($current_banner['decor'])): ?>
                            <div class="banner-content-decor">
                                <?php foreach ($current_banner['decor'] as $decor) { ?>
                                    <img src="<?= esc_url($decor) ?>" class="img-fluid banner-decor" alt="Decor"/>
                                <?php } ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php

            $rendered_banner = ob_get_clean();

            if (isset($current_banner['font']['family'])) {
                $banner_id = $params['id'];

                add_action('wp_head', function () use ($banner_id, $current_banner) {
                    ?>
                    <link href="<?= $current_banner['font']['url'] ?>" rel="stylesheet">
                    <style>
                        <?= '#' . $banner_id ?>
                        .e-title {
                            font-family: "<?= $current_banner['font']['family'] ?>", cursive;
                        }

                        <?= '#' . $banner_id ?>
                        .banner-content-discount {
                            font-family: "<?= $current_banner['font']['family'] ?>", cursive;
                        }
                    </style>
                    <?php
                });
            }

            add_action('wp_footer', function () use ($params) { ?>
                <script>
                    $(document).ready(function () {
                        let wcBanner = jQuery('#<?php echo $params['id']; ?>');
                        if (wcBanner.length !== 0) {
                            // Guard: growtype-wc.js may be deferred and not yet run.
                            window.growtype_wc = window.growtype_wc || {};
                            window.growtype_wc.countdown = window.growtype_wc.countdown || {};
                            window.growtype_wc.countdown['over'] = '<?php echo esc_html__('Last Chance', 'growtype-wc'); ?>';

                            jQuery('#<?php echo $params['id']; ?> .gwc-time-countdown').each(function (index, element) {
                                let hideOnCountdownExpired = '<?php echo $params['hide_on_countdown_expired']; ?>';
                                let cookieName = jQuery(this).attr('id');
                                let isVisible = growtypeCookie.getCookie(cookieName) === null || growtypeWcConvertStringToSeconds(growtypeCookie.getCookie(cookieName)) > 0 ? true : false;

                                if (hideOnCountdownExpired) {
                                    if (isVisible) {
                                        $(this).closest('.growtype-wc-banner').fadeIn();
                                    } else {
                                        $(this).closest('.growtype-wc-banner').hide();
                                    }
                                } else {
                                    $(this).closest('.growtype-wc-banner').show();
                                }

                                $(element).on('countdownExpired', function () {
                                    $(this).closest('.growtype-wc-banner').fadeOut();
                                });
                            });

                            jQuery('#<?php echo $params['id']; ?>.growtype-wc-banner').each(function (index, element) {
                                $(element).on('click', function () {
                                    let url = $(this).attr('data-url');

                                    if (url) {
                                        window.location.href = url;
                                    }
                                });
                            });
                        }
                    });
                </script>
                <?php
            }, 100);
        }

        if (!empty($rendered_banner)) {
            add_filter('body_class', static function (array $classes): array {
                $classes[] = 'has-growtype-wc-banner';
                return $classes;
            });
        }

        return $rendered_banner;
    }
}
