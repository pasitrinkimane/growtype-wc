<?php

/**
 * Load custom urls
 */
add_filter('generate_rewrite_rules', function ($wp_rewrite) {
    $wp_rewrite->rules = array_merge(
        ['^growtype-wc/documentation/examples/([^/]*)/?/([^/]*)/?' => 'index.php?example_category=$matches[1]&example_type=$matches[2]'],
        $wp_rewrite->rules
    );
});

add_filter('query_vars', function ($query_vars) {
    $query_vars[] = 'example_category';
    $query_vars[] = 'example_type';
    return $query_vars;
});

/**
 * Template redirect
 */
add_action('template_redirect', function () {
    /**
     * Check if user should be redirected to specific url.
     */
    $user_can_buy = get_theme_mod('only_registered_users_can_buy') ? is_user_logged_in() : true;

    if (
        !$user_can_buy
        &&
        (
            is_woocommerce() || is_cart() || is_checkout()
        )
    ) {
        wc_clear_notices();
        $redirect_url = growtype_wc_user_can_not_buy_redirect_url();
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Check if login page and redirect accordingly
     */
    if (!is_user_logged_in() && growtype_wc_is_account_page() && class_exists('Growtype_Form') && !empty(growtype_form_login_page_url())) {
        wp_redirect(growtype_form_login_page_url());
        exit;
    }

    /**
     * Load example templates
     * {domain}/growtype-wc/documentation/examples/email/preview/?action=preview_email&email_type=WC_Email_New_Order&order_id=159
     */
    $example_category = get_query_var('example_category');
    $example_type = get_query_var('example_type');
    if ($example_category && $example_type) {
        $url_path = trim(parse_url(add_query_arg(array ()), PHP_URL_PATH), '/');
        $url_path = str_replace('growtype-wc/', '', $url_path);

        try {
            echo growtype_wc_include_view($url_path, []);
        } catch (\Exception $ex) {
            wp_redirect(home_url());
        }
        exit;
    }
});
