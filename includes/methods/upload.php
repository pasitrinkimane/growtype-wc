<?php

/**
 * Class Growtype_Wc_Upload_Data
 */
class Growtype_Wc_Upload_Data
{
    public function __construct()
    {
        add_shortcode('wc_upload_form', array ($this, 'wc_upload_form_shortcode_function'));
    }

    /**
     * Upload form shortcode
     * [wc_upload_form fields="occupation*:Teisinį išsilavinimą turintys ar teisę studijuojantys asmenys|Moksleivis|Kiti=select,gradegroup*:5-6 kl.|7-8 kl.|9-10 kl.|11-12 kl.=select|hidden,school*=hidden|select,first_and_last_name*,email*,phone*,birthday*=skip_backend_validation,country*:Gyvenu Lietuvoje|Gyvenu užsienyje=select,child_first_and_last_name*=hidden|skip_backend_validation,username*=hidden|skip_backend_validation,password*,repeat_password*" accept_text='Susipažinau su privatumo taisyklėmis' confirm_text="Sutinku, kad mano užregistruotas vaikas dalyvautų Konstitucijos egzamine" promo_text="Sutinku el.paštu gauti pranešimus apie Konstitucijos egzaminą" recaptchav3=""]
     */
    function wc_upload_form_shortcode_function($args)
    {
        global $wp_session;

        /**
         * Disable form if user is not logged in
         */
        if (!is_user_logged_in()) {
            return null;
        }

        if (empty($args)) {
            return null;
        }

        /**
         * Enqueue scripts
         */
        $this->growtype_wc_upload_data_enqueue_scripts();

        /**
         * Initiate scripts
         */
        add_action('wp_footer', function () {
            $this->growtype_wc_upload_scripts_init();
            $this->growtype_wc_upload_validation_scripts_init();
        }, 99);

        /**
         * Form Settings
         */

        $placeholder_enabled = false;

        $fields = !empty($args['fields']) ? explode(',', $args['fields']) : null;

        if (empty($fields)) {
            return null;
        }

        $recaptchav3 = isset($args['recaptchav3']) && !empty($args['recaptchav3']) ? $args['recaptchav3'] : null;
        $accept_text = $args['accept_text'] ?? null;
        $confirm_text = $args['confirm_text'] ?? null;
        $promo_text = $args['promo_text'] ?? null;

        if (!empty($recaptchav3) && !function_exists('recaptcha_setup')) {
            add_action('wp_footer', function () use (&$recaptchav3) {
                recaptcha_setup($recaptchav3);
            }, 99);
        }

        if (isset($_POST["wc-upload-form-submit"]) && sanitize_text_field($_POST["wc-upload-form-submit"]) === 'true') {
            $fields_values = $this->map_form_fields_values($fields, $_POST);

            if (isset($_POST['promo_checkbox'])) {
                $fields_values['promo_checkbox'] = $_POST['promo_checkbox'];
            }

            if (!empty($fields_values)) {
                $registration = $this->save_uploaded_data($fields_values);

                if ($registration['success']) {
                    $user_login = $registration['user']['id'];
                    $user = get_user_by('id', $user_login);
                    $user_id = $user->ID;
                    wp_set_current_user($user_id, $user_login);
                    wp_set_auth_cookie($user_id);
                    do_action('wp_login', $user_login);

                    return wp_redirect(get_permalink(get_page_by_path('profile')->ID));
                }
            } else {
                $registration['success'] = false;
                $registration['message'] = __('Please fill all required fields.', 'growtype-wc');
            }

            /**
             * Prepare redirect details
             */
            $status_args = array (
                'status' => $registration['success'] ? 'success' : 'fail',
                'message' => $registration['message'],
            );

            $fields_values_args = $fields_values;

            $query_args = array_merge($status_args, $fields_values_args);

            $redirect = add_query_arg($query_args, get_permalink());

            return wp_redirect($redirect);
        }

        ob_start();
        ?>
        <div class="wc-upload-form-wrapper">

            <?php if (isset($_GET['status']) && !empty($_GET['status'])) {
                $status_message = sanitize_text_field(filter_input(INPUT_GET, 'message'));
                if ($_GET['status'] === 'success') { ?>
                    <div class="alert alert-success" role="alert">
                        <?= __($status_message, "growtype-wc") ?>
                    </div>
                <?php } else { ?>
                    <div class="alert alert-danger" role="alert">
                        <?= __($status_message, "growtype-wc") ?>
                    </div>
                <?php }
            } ?>

            <div id="wc-upload-form-container" class="container">
                <div class="form-wrapper">
                    <h2><?= __("Upload content", "growtype-wc") ?></h2>
                    <form id="wc-upload-form" class="b-form" action="<?php the_permalink(); ?>" method="post">
                        <div class="row g-3">
                            <?php
                            foreach ($fields as $field) { ?>
                                <?php
                                $field_name = str_replace('*', '', str_replace(' ', '_', $field));
                                $required = str_contains($field, '*');
                                $field_type = 'input';
                                $field_label_enabled = true;
                                $field_hidden = false;
                                $field_value = sanitize_text_field(filter_input(INPUT_GET, $field_name));

                                if (str_contains($field, ':') || str_contains($field, '=')) {
                                    $field_settings = substr($field, strpos($field, "=") + 1);
                                    $field_settings_values = explode('|', $field_settings);

                                    if (str_contains($field, ':')) {
                                        $field_name = str_replace(':' . substr($field_name, strpos($field_name, ":") + 1), '', $field_name);
                                        $field_options = str_replace('=' . $field_settings, '', substr($field, strpos($field, ":") + 1));
                                        $field_options = explode('|', $field_options);
                                    } else {
                                        $field_name = str_replace('=' . substr($field, strpos($field, "=") + 1), '', $field_name);
                                        $field_options = [];
                                    }

                                    if (in_array('radio', $field_settings_values)) {
                                        $field_type = 'radio';
                                    } elseif (in_array('select', $field_settings_values)) {
                                        $field_type = 'select';
                                    }

                                    if (in_array('nolabel', $field_settings_values)) {
                                        $field_label_enabled = false;
                                    }

                                    if (in_array('hidden', $field_settings_values)) {
                                        $field_hidden = true;
                                    }
                                }

                                $types = [
                                    'email' => 'email',
                                    'password' => 'password',
                                    'repeat_password' => 'password',
                                ];

                                $type = $types[$field_name] ?? 'text';

                                $labels = [
                                    'email' => __('Email address', 'growtype-wc'),
                                    'password' => __('Password', 'growtype-wc'),
                                    'repeat_password' => __('Repeat Password', 'growtype-wc'),
                                    'first_name' => __('First name', 'growtype-wc'),
                                    'last_name' => __('Last name', 'growtype-wc'),
                                    'first_and_last_name' => __('First and Last name', 'growtype-wc'),
                                    'phone' => __('Phone', 'growtype-wc'),
                                    'birthday' => __('Birthday', 'growtype-wc'),
                                    'city' => __('City', 'growtype-wc'),
                                    'occupation' => __('Occupation', 'growtype-wc'),
                                    'country' => __('Country', 'growtype-wc'),
                                    'school' => __('School', 'growtype-wc'),
                                    'grade' => __('Grade', 'growtype-wc'),
                                    'gradegroup' => __('Grade group', 'growtype-wc'),
                                    'child_first_and_last_name' => __('Child first and last name', 'growtype-wc'),
                                    'username' => __('User name', 'growtype-wc'),
                                    'subject' => __('Subject', 'growtype-wc'),
                                ];

                                $label = $labels[$field_name] ?? ucfirst($field_name);
                                $label = str_replace('_', ' ', $label);
                                $label = $required ? $label . '*' : $label;

                                if ($placeholder_enabled) {
                                    $placeholder = __('Enter your', 'growtype-wc') . ' ' . strtolower($label);
                                }

                                ?>
                                <div class="col-auto" style="<?= $field_hidden ? 'display:none;' : '' ?>" data-name="<?= $field_name ?>">
                                    <?php
                                    if ($field_label_enabled) { ?>
                                        <label for="<?= $field_name ?>" class="form-label">
                                            <?= $label ?>
                                        </label>
                                    <?php } ?>

                                    <?php
                                    if ($field_type === 'select') { ?>
                                        <select name="<?= $field_name ?>" id="<?= $field_name ?>">
                                            <?php
                                            foreach ($field_options as $field_option) { ?>
                                                <option value="<?= sanitize_text_field(strtolower(str_replace(' ', '_', $field_option))) ?>"><?= str_replace('_', ' ', $field_option) ?></option>
                                            <?php } ?>
                                        </select>
                                    <?php } elseif ($field_type === 'radio') { ?>
                                        <?php
                                        foreach ($field_options as $field_option) { ?>
                                            <div class="radio-wrapper">
                                                <input type="radio" id="<?= str_replace(' ', '_', strtolower($field_option)) ?>" name="<?= $field_name ?>" value="<?= strtolower($field_option) ?>" <?= $required ? 'required' : '' ?>>
                                                <label for="<?= str_replace(' ', '_', strtolower($field_option)) ?>"><?= str_replace('_', ' ', $field_option) ?></label>
                                            </div>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <input type="<?= $type ?>"
                                               class="form-control"
                                               name="<?= $field_name ?>"
                                               id="<?= $field_name ?>"
                                               placeholder="<?= $placeholder ?? null ?>"
                                            <?= $required ? 'required' : '' ?>
                                               value="<?= !str_contains($field_name, 'password') ? $field_value : null ?>"
                                        >
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="row mt-2 pt-1">
                            <div class="col-12">
                                <?php if (!empty($accept_text)) { ?>
                                    <div class="form-check mt-3" data-name="terms_checkbox">
                                        <input type="checkbox" name="terms_checkbox" class="form-check-input" id="terms_checkbox" required>
                                        <label class="form-check-label" for="terms_checkbox"><?= $accept_text ?></label>
                                    </div>
                                <?php } ?>

                                <?php if (!empty($confirm_text)) { ?>
                                    <div class="form-check mt-3" data-name="confirm_checkbox">
                                        <input type="checkbox" name="confirm_checkbox" class="form-check-input" id="confirm_checkbox" required>
                                        <label class="form-check-label" for="confirm_checkbox"><?= $confirm_text ?></label>
                                    </div>
                                <?php } ?>

                                <?php if (!empty($promo_text)) { ?>
                                    <div class="form-check mt-3" data-name="promo_checkbox">
                                        <input type="checkbox" name="promo_checkbox" class="form-check-input" id="promo_checkbox">
                                        <label class="form-check-label" for="promo_checkbox"><?= $promo_text ?></label>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <input type="text" hidden="" name='wc-upload-form-submit' value="true"/>

                                <?php if (!empty($recaptchav3)) { ?>
                                    <div class="g-recaptcha"
                                         data-sitekey="<?= $recaptchav3 ?>"
                                         data-size="invisible"
                                         data-callback="uploadFormSubmit">
                                    </div>
                                <?php } ?>

                                <button type="submit" class="btn btn-primary"><?= __("Upload", "growtype-wc") ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php

        $form = ob_get_clean();

        return $form;
    }

    /**
     * @param $data
     * @return array
     * Register user method
     */
    function save_uploaded_data($data)
    {
        global $wpdb, $user_ID;

        d('test124');

        $email = isset($data['email']) ? sanitize_text_field($data['email']) : null;
        $username = isset($data['username']) ? sanitize_text_field($data['username']) : null;
        $username = !empty($username) ? $username : $email;
        $password = isset($data['password']) ? sanitize_text_field($_REQUEST['password']) : null;
        $repeat_password = isset($data['repeat_password']) ? sanitize_text_field($_REQUEST['repeat_password']) : null;

        if (empty($username) || empty($password) || empty($email)) {
            $response['success'] = false;
            $response['message'] = __("Missing required values", "growtype-wc");
            return $response;
        }

        if (!empty($repeat_password)) {
            if ($password !== $repeat_password) {
                $response['success'] = false;
                $response['message'] = __("Passwords do not match", "growtype-wc");
                return $response;
            }
        }

        $validate_password = validate_password($password);

        if ($validate_password['success'] === false) {
            $response['success'] = $validate_password['success'];
            $response['message'] = $validate_password['message'];
            return $response;
        }

        /**
         * Save with unique email. Check if username is provided and email already exists in database.
         */
        if ($username !== $email && email_exists($email)) {
            $email_exploded = explode('@', $email);
            $username_formatted = urlencode(str_replace(' ', '', $username));
            $email = $email_exploded[0] . '+' . $username_formatted . '@' . $email_exploded[1];
        }

        $status = wp_create_user($username, $password, $email);

        if (is_wp_error($status)) {
            $response['success'] = false;
            $response['message'] = __("Profile already registered.", "growtype-wc");
        } else {
            $user_id = $status;

            /**
             * Save extra values
             */
            $skipped_values = ['username', 'password', 'repeat_password', 'email', 'submit'];
            foreach ($data as $key => $value) {
                if (!in_array($key, $skipped_values) && !str_contains($value, 'password') && !empty($value)) {
                    if ($key === 'first_and_last_name') {
                        $first_name = explode(' ', $value)[0] ?? null;
                        $last_name = explode(' ', $value)[1] ?? null;
                        $middle_name = explode(' ', $value)[2] ?? null;
                        if (empty($middle_name)) {
                            update_user_meta($user_id, 'first_name', sanitize_text_field($first_name));
                            update_user_meta($user_id, 'last_name', sanitize_text_field($last_name));
                        } else {
                            update_user_meta($user_id, 'first_name', sanitize_text_field($value));
                        }
                    }
                    update_user_meta($user_id, $key, sanitize_text_field($value));
                }
            }

            $response['user']['id'] = $user_id;
            $response['user']['username'] = $username;
            $response['success'] = true;
            $response['message'] = __("Registration successful.", "growtype-wc");
        }

        return $response;
    }

    /**
     * @param $fields
     * @param $posted_data
     * @return array
     * Map post fields with shortcode fields
     */
    function map_form_fields_values($fields, $posted_data)
    {
        $fields_values = [];

        foreach ($fields as $key => $field) {
            $required = str_contains($field, '*');
            $skip_backend_validation = str_contains($field, 'skip_backend_validation');
            $field = str_replace('*', '', str_replace(' ', '_', $field));
            $field = str_replace(':' . substr($field, strpos($field, ":") + 1), '', $field);
            $field = str_replace('=' . substr($field, strpos($field, "=") + 1), '', $field);
            $fields_values[$field] = $posted_data[$field] ?? null;

            if ($required && isset($posted_data[$field]) && empty($fields_values[$field]) && !$skip_backend_validation) {
                return [];
            }
        }

        return $fields_values;
    }

    /**
     * Required scripts
     */
    function growtype_wc_upload_data_enqueue_scripts()
    {
        wp_enqueue_style('growtype-wc-upload-css', plugin_dir_url(dirname(dirname(__FILE__))) . '/public/styles/growtype-wc-upload.css', array (), '1.0', 'all');
        wp_enqueue_script('jquery.validate.js', 'https://ajax.aspnetcdn.com/ajax/jquery.validate/1.10.0/jquery.validate.min.js', '', '', true);

        if (get_locale() === 'lt_LT') {
            wp_enqueue_script('jquery.validate.js.localization', 'https://ajax.aspnetcdn.com/ajax/jquery.validate/1.10.0/localization/messages_lt.js', '', '', true);
        }
    }

    /**
     *
     */
    function growtype_wc_upload_scripts_init()
    {
        ?>
        <script>
            if (window.location.search.length > 0 && window.location.search.indexOf('action') !== -1) {
                window.history.replaceState(null, null, window.location.pathname);
            } else if (window.location.search.length > 0 && window.location.search.indexOf('message') !== -1) {
                window.growtypeWcUploadFormFailed = true;
                window.history.replaceState(null, null, window.location.pathname);
            }
        </script>
        <?php
    }

    /**
     * Validate form
     */
    function growtype_wc_upload_validation_scripts_init()
    {
        ?>
        <script>
            $.validator.setDefaults({ignore: ":hidden:not(select)"});

            if ($("#wc-upload-form select").length > 0) {
                $("#wc-upload-form select").each(function () {
                    if ($(this).attr('required') !== undefined) {
                        $(this).on("change", function () {
                            $(this).valid();
                        });
                    }
                });
            }

            $('#wc-upload-form button[type="submit"]').click(function () {
                var isValid = $("#wc-upload-form").valid();
                if (!isValid) {
                    event.preventDefault();
                }
            });

            $('#wc-upload-form').validate({
                errorPlacement: function (error, element) {
                    // console.log(element)
                    if (element.is("#wc-upload-form select")) {
                        element.closest(".col-auto").append(error);
                    } else if (element.is("#wc-upload-form input[type='checkbox']")) {
                        element.closest(".form-check").append(error);
                    } else {
                        error.insertAfter(element);
                    }
                },
                // messages: {
                //     occupation: {
                //         required: "Pasirinkite kategoriją",
                //     },
                //     first_and_last_name: {
                //         required: "Šis laukas būtinas",
                //     },
                //     email: {
                //         required: "Šis laukas būtinas",
                //     },
                //     phone: {
                //         required: "Šis laukas būtinas",
                //     },
                //     birthday: {
                //         required: "Šis laukas būtinas",
                //     },
                //     country: {
                //         required: "Šis laukas būtinas",
                //     },
                //     password: {
                //         required: "Šis laukas būtinas",
                //     },
                //     repeat_password: {
                //         required: "Šis laukas būtinas",
                //     },
                //     terms_checkbox: {
                //         required: "Šis laukas būtinas",
                //     },
                //     child_first_and_last_name: {
                //         required: "Šis laukas būtinas",
                //     },
                //     username: {
                //         required: "Šis laukas būtinas",
                //     },
                //     confirm_checkbox: {
                //         required: "Šis laukas būtinas",
                //     },
                // },
            });

            $('#wc-upload-form').validate().settings.ignore = ".chosen-search-input";
        </script>
        <?php
    }

    /**
     * @param $recaptchav3
     */
    function recaptcha_setup($recaptchav3)
    {
        ?>
        <style>
            .grecaptcha-badge {
                display: none !important;
            }
        </style>
        <script src="https://www.google.com/recaptcha/api.js?render=<?= $recaptchav3 ?>"></script>
        <script>
            $('#wc-upload-form').submit(function (event) {
                event.preventDefault();
                $(this).find('button[type="submit"]').attr('disabled', true);
                grecaptcha.reset();
                grecaptcha.execute();
            });

            function uploadFormSubmit(token) {
                document.getElementById("wc-upload-form").submit();
            }
        </script>
        <?php
    }
}
