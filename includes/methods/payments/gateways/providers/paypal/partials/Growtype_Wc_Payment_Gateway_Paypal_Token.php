<?php

/**
 * PayPal Gateway Token/Auth handler.
 */
class Growtype_Wc_Payment_Gateway_Paypal_Token
{
    /** @var Growtype_Wc_Payment_Gateway_Paypal */
    private $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Get access token details from PayPal.
     * Fetches a fresh token from the API; callers should prefer get_access_token() which caches.
     *
     * @param string $client_id
     * @param string $client_secret
     * @return array|bool
     */
    public function get_access_token_details($client_id, $client_secret)
    {
        $auth      = base64_encode($client_id . ':' . $client_secret);
        $token_url = $this->gateway->get_api_url('/v1/oauth2/token');

        $response = wp_remote_post($token_url, [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => ['grant_type' => 'client_credentials'],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            error_log('[GWC PayPal Token] Error fetching token: ' . $response->get_error_message());
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : false;
    }

    /**
     * Get a cached access token.
     * Tokens are valid for 9 hours; we cache for 8 hours to avoid edge cases.
     * The cache key is scoped to the client_id so sandbox and live tokens never collide.
     *
     * @param string $client_id
     * @param string $client_secret
     * @return string|null
     */
    public function get_access_token($client_id, $client_secret): ?string
    {
        // Use a short hash of client_id as a safe cache key (never store the secret in cache keys)
        $cache_key = 'gwc_paypal_token_' . substr(md5($client_id), 0, 12);

        $cached = get_transient($cache_key);
        if (!empty($cached)) {
            return $cached;
        }

        $details = $this->get_access_token_details($client_id, $client_secret);
        if (empty($details['access_token'])) {
            error_log('[GWC PayPal Token] Failed to obtain access token.');
            return null;
        }

        $token      = $details['access_token'];
        $expires_in = isset($details['expires_in']) ? (int)$details['expires_in'] : 32400; // 9h default
        $ttl        = max(60, $expires_in - 3600); // cache for expiry - 1h buffer

        set_transient($cache_key, $token, $ttl);

        return $token;
    }
}
