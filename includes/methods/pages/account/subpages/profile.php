<?php

class Growtype_Wc_Account_Profile
{
    public const PROFILE_PICTURE_MAX_BYTES = 3 * MB_IN_BYTES;
    public const PROFILE_PICTURE_ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct()
    {
        // 1. Register routing endpoints
        add_action('init', [$this, 'add_endpoints']);
        add_filter('query_vars', [$this, 'add_query_vars'], 0);
        add_filter('woocommerce_get_query_vars', [$this, 'add_wc_query_vars'], 0);
        add_filter(
            'growtype_wc_get_account_subpage_intro_available_pages',
            [$this, 'add_intro_details'],
            10,
            2
        );

        // 2. Register endpoint rendering hooks for profile
        add_action(
            'woocommerce_account_profile_endpoint',
            [$this, 'render_endpoint'],
            10
        );

        // 3. Handle form submission redirects for profile page
        add_action('woocommerce_save_account_details_errors', [$this, 'validate_profile_picture_upload'], 10, 2);
        add_action('woocommerce_save_account_details', [$this, 'handle_profile_form_redirect'], 5);
    }

    public static function profile_picture_allowed_mimes(): array
    {
        return [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
    }

    public function validate_profile_picture_upload($errors, $user): void
    {
        if (empty($_POST['is_profile_form']) || empty($_FILES['profile_picture']['name'])) {
            return;
        }

        $file = $_FILES['profile_picture'];
        $upload_error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($upload_error !== UPLOAD_ERR_OK) {
            $errors->add('profile_picture_upload_failed', __('The profile photo could not be uploaded. Please try another image.', 'growtype-wc'));
            return;
        }

        $tmp_name = (string) ($file['tmp_name'] ?? '');
        $actual_size = is_file($tmp_name) ? (int) filesize($tmp_name) : (int) ($file['size'] ?? 0);
        if ($actual_size > self::PROFILE_PICTURE_MAX_BYTES) {
            $errors->add('profile_picture_too_large', __('Profile photos must be 3 MB or smaller.', 'growtype-wc'));
            return;
        }

        $detected_mime = $tmp_name !== '' && is_file($tmp_name) ? wp_get_image_mime($tmp_name) : false;
        $filetype = wp_check_filetype((string) $file['name'], self::profile_picture_allowed_mimes());
        if (
            !in_array($detected_mime, self::PROFILE_PICTURE_ALLOWED_MIME_TYPES, true) ||
            !in_array($filetype['type'] ?? '', self::PROFILE_PICTURE_ALLOWED_MIME_TYPES, true) ||
            $detected_mime !== ($filetype['type'] ?? '')
        ) {
            $errors->add('profile_picture_invalid_type', __('Choose a JPEG, PNG, or WebP image.', 'growtype-wc'));
        }
    }

    public function add_endpoints()
    {
        add_rewrite_endpoint('profile', EP_ROOT | EP_PAGES);
        $rules = get_option('rewrite_rules');
        if (is_array($rules) && !isset($rules['(.?.+?)/profile(/(.*))?/?$'])) {
            flush_rewrite_rules(false);
        }
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'profile';
        return $vars;
    }

    public function add_wc_query_vars($vars)
    {
        $vars['profile'] = 'profile';
        return $vars;
    }

    public function add_intro_details($available_pages, $subpage)
    {
        // New Profile page details
        $available_pages['profile'] =
            __('Profile', 'growtype-wc') .
            ' <div class="e-subtitle">' .
            __('Adjust profile details', 'growtype-wc') .
            '</div>';

        // Rename edit-account endpoint to Account & Security
        $available_pages['edit-account'] =
            __('Account & Security', 'growtype-wc') .
            ' <div class="e-subtitle">' .
            __('Adjust password & security details', 'growtype-wc') .
            '</div>';

        return $available_pages;
    }

    public function render_endpoint()
    {
        wp_enqueue_style('dashicons');

        $paths = [
            get_stylesheet_directory() . '/views/woocommerce/myaccount/form-profile.php',
            get_stylesheet_directory() . '/resources/views/woocommerce/myaccount/form-profile.php',
            GROWTYPE_WC_PATH . 'resources/views/woocommerce/myaccount/form-profile.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                include $path;
                return;
            }
        }
    }

    public function handle_profile_form_redirect($user_id)
    {
        if (isset($_POST['is_profile_form'])) {
            if (!empty($_POST['delete_profile_picture']) && $user_id) {
                delete_user_meta($user_id, 'profile_picture');
            } elseif (!empty($_FILES['profile_picture']['name']) && $user_id) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';

                $attachment_id = media_handle_upload('profile_picture', 0, [], [
                    'test_form' => false,
                    'mimes' => self::profile_picture_allowed_mimes(),
                ]);
                if (!is_wp_error($attachment_id)) {
                    $url = wp_get_attachment_url($attachment_id);
                    update_user_meta($user_id, 'profile_picture', $url);
                }
            }


            remove_action('woocommerce_save_account_details', 'save_favorite_color_account_details', 12);
            add_action('woocommerce_save_account_details', function () {
                wp_safe_redirect(wc_get_endpoint_url('profile'));
                exit;
            }, 12);
        }
    }
}

new Growtype_Wc_Account_Profile();
