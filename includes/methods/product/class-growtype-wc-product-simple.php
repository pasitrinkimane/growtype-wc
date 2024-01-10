<?php

/**
 * Growtype product methods
 * Requires: woocommerce plugin
 */
class Growtype_Wc_Auction
{
    public static function user_active_bids_ids($user_ID)
    {
        global $wpdb;

        $postids = array ();
        $userauction = $wpdb->get_results("SELECT DISTINCT auction_id FROM " . $wpdb->prefix . "simple_auction_log WHERE userid = $user_ID ", ARRAY_N);

        if (isset($userauction) && !empty($userauction)) {
            foreach ($userauction as $auction) {
                $postids[] = $auction[0];
            }
        }

        return $postids;
    }
}
