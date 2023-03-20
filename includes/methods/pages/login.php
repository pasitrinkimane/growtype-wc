<?php

add_action('wp_enqueue_scripts', 'growtype_wc_login_scripts');
function growtype_wc_login_scripts()
{
    global $post;
    if (isset($post) && $post->ID == get_option('woocommerce_myaccount_page_id')) {
        wp_enqueue_script('login-main', GROWTYPE_WC_URL_PUBLIC . '/scripts/wc-login.js', [], '1.0.0', true);
    }
}
