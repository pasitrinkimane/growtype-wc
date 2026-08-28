<?php

/**
 * Save extra fields info
 */
add_action('woocommerce_save_account_details', 'save_favorite_color_account_details', 12, 1);
function save_favorite_color_account_details($user_id)
{
    $account_email = $_POST['account_email'];

    $user = get_user_by('id', $user_id);
    $user_login = $user->data->user_login;

    /**
     * Update login email
     */
    if ($account_email !== $user_login) {
        global $wpdb;

        $wpdb->update(
            $wpdb->users,
            ['user_login' => $account_email],
            ['ID' => $user_id]
        );
    }

    if (class_exists('Growtype_Form')) {
        $form_name = $_POST['growtype_form_name'] ?? null;
        if (!empty(Growtype_Form_Crud::get_growtype_form_data($form_name))) {
            $main_fields = Growtype_Form_Crud::get_growtype_form_data($form_name)['main_fields'];

            foreach ($main_fields as $field) {
                if (isset($field['name']) && isset($_POST[$field['name']])) {
                    update_user_meta($user_id, $field['name'], $_POST[$field['name']]);
                }
            }
        }
    }

    apply_filters('growtype_wc_save_account_details', $_POST);

    wp_safe_redirect(wc_get_endpoint_url('edit-account'));
    exit;
}

/**
 * My account page
 */
add_action('woocommerce_edit_account_form_end', 'growtype_wc_after_my_account');
function growtype_wc_account_delete_confirmation_message(): string
{
    return __('Are you 100% sure you want to delete your account? All your data will be permanently lost.', 'growtype-wc');
}

function growtype_wc_render_account_delete_form(string $delete_url): void
{
    ?>
    <span
        class="growtype-wc-account-delete-control ms-auto"
        data-delete-account-confirmation="<?php echo esc_attr(growtype_wc_account_delete_confirmation_message()); ?>"
    >
        <input type="hidden" name="delete_account_confirmed" value="1">
        <button
            type="submit"
            class="btn btn-secondary btn-remove-account"
            formaction="<?php echo esc_url($delete_url); ?>"
            formmethod="post"
            formnovalidate
            onclick="return window.confirm(this.parentElement.getAttribute('data-delete-account-confirmation'));"
        >
            <?php esc_html_e('Delete Account', 'growtype-wc'); ?>
        </button>
    </span>
    <?php
}

function growtype_wc_after_my_account()
{
    $delete_url = add_query_arg('wc-api', 'wc-delete-account', home_url('/'));
    $delete_url = wp_nonce_url($delete_url, 'wc_delete_user');

    if (!current_user_can('manage_options')) {
        growtype_wc_render_account_delete_form($delete_url);
    }
}

/**
 *
 */
add_action('woocommerce_api_' . strtolower('wc-delete-account'), 'woocommerce_api_wc_delete_account');
function growtype_wc_clear_deleted_account_browser_state(): void
{
    $cookie_paths = array_unique(array_filter([
        '/',
        defined('COOKIEPATH') ? COOKIEPATH : '/',
        defined('SITECOOKIEPATH') ? SITECOOKIEPATH : '/',
        defined('ADMIN_COOKIE_PATH') ? ADMIN_COOKIE_PATH : '/wp-admin',
        defined('PLUGINS_COOKIE_PATH') ? PLUGINS_COOKIE_PATH : '/wp-content/plugins',
    ]));
    $cookie_domain = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';

    foreach (array_keys($_COOKIE) as $cookie_name) {
        foreach ($cookie_paths as $cookie_path) {
            setcookie((string) $cookie_name, '', time() - YEAR_IN_SECONDS, (string) $cookie_path, $cookie_domain, is_ssl(), true);
        }
        unset($_COOKIE[$cookie_name]);
    }

    wp_clear_auth_cookie();

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}

function woocommerce_api_wc_delete_account()
{
    if (!is_user_logged_in() || current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to delete this account.', 'growtype-wc'), '', ['response' => 403]);
    }

    if (
        strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST'
        || sanitize_text_field(wp_unslash($_POST['delete_account_confirmed'] ?? '')) !== '1'
    ) {
        wp_die(esc_html__('Account deletion requires explicit confirmation.', 'growtype-wc'), '', ['response' => 405]);
    }

    check_admin_referer('wc_delete_user');
    $user_id = get_current_user_id();

    growtype_wc_clear_deleted_account_browser_state();
    require_once ABSPATH . 'wp-admin/includes/user.php';
    if (!wp_delete_user($user_id)) {
        wp_die(esc_html__('The account could not be deleted. Please try again.', 'growtype-wc'), '', ['response' => 500]);
    }

    wp_safe_redirect(home_url());
    exit;
}
