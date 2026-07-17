<?php

/**
 * Detect user's country via IP geolocation.
 *
 * Uses Cloudflare header (free, instant) when available,
 * falls back to ip-api.com (free, no key required).
 * Result is cached in session for the request lifetime.
 *
 * @return string Two-letter country code (e.g. "US", "LT") or empty string.
 */
function growtype_wc_detect_user_country(): string
{
    static $country = null;

    if ($country !== null) {
        return $country;
    }

    // 1. Cloudflare header — instant, no API call
    if (!empty($_SERVER["HTTP_CF_IPCOUNTRY"])) {
        $code = strtoupper(sanitize_text_field($_SERVER["HTTP_CF_IPCOUNTRY"]));
        if (strlen($code) === 2) {
            $country = $code;
            return $country;
        }
    }

    // 2. Session cache — avoid repeat API calls within a visit
    if (WC()->session) {
        $cached = WC()->session->get("growtype_wc_user_country");
        if ($cached) {
            $country = $cached;
            return $country;
        }
    }

    // 3. ip-api.com — free, no key, 45 req/min limit
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    if ($ip && $ip !== "127.0.0.1" && $ip !== "::1") {
        $response = wp_remote_get(
            "http://ip-api.com/json/{$ip}?fields=countryCode",
            ["timeout" => 3],
        );

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (
                !empty($body["countryCode"]) &&
                strlen($body["countryCode"]) === 2
            ) {
                $code = strtoupper($body["countryCode"]);
                $country = $code;

                if (WC()->session) {
                    WC()->session->set("growtype_wc_user_country", $code);
                }

                return $country;
            }
        }
    }

    $country = "";

    // Allow override via filter (useful in dev/staging without Cloudflare)
    $country = apply_filters("growtype_wc_detected_country", $country);

    return $country;
}

/**
 * Convert two-letter country code to readable country name.
 */
function growtype_wc_country_code_to_name(string $code): string
{
    static $map = null;

    if ($map === null) {
        $map = [
            "US" => "the United States",
            "GB" => "the United Kingdom",
            "CA" => "Canada",
            "AU" => "Australia",
            "DE" => "Germany",
            "FR" => "France",
            "IT" => "Italy",
            "ES" => "Spain",
            "NL" => "the Netherlands",
            "BR" => "Brazil",
            "MX" => "Mexico",
            "JP" => "Japan",
            "KR" => "South Korea",
            "IN" => "India",
            "LT" => "Lithuania",
            "PL" => "Poland",
            "PT" => "Portugal",
            "SE" => "Sweden",
            "NO" => "Norway",
            "DK" => "Denmark",
            "FI" => "Finland",
            "IE" => "Ireland",
            "BE" => "Belgium",
            "AT" => "Austria",
            "CH" => "Switzerland",
            "NZ" => "New Zealand",
            "SG" => "Singapore",
            "AE" => "the United Arab Emirates",
            "SA" => "Saudi Arabia",
            "ZA" => "South Africa",
            "AR" => "Argentina",
            "CL" => "Chile",
            "CO" => "Colombia",
            "PE" => "Peru",
            "PH" => "the Philippines",
            "MY" => "Malaysia",
            "TH" => "Thailand",
            "VN" => "Vietnam",
            "ID" => "Indonesia",
            "TR" => "Turkey",
            "EG" => "Egypt",
            "NG" => "Nigeria",
            "KE" => "Kenya",
            "RO" => "Romania",
            "CZ" => "the Czech Republic",
            "HU" => "Hungary",
            "GR" => "Greece",
            "UA" => "Ukraine",
            "RU" => "Russia",
            "CN" => "China",
            "TW" => "Taiwan",
            "HK" => "Hong Kong",
        ];
    }

    return $map[strtoupper($code)] ?? $code;
}
