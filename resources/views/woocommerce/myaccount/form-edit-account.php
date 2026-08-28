<?php
defined('ABSPATH') || exit;

$user = wp_get_current_user();
do_action('woocommerce_before_edit_account_form');
?>

<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php do_action('woocommerce_edit_account_form_tag'); ?> >
    <!-- Hidden personal fields so WooCommerce save_account_details validation passes -->
    <input type="hidden" name="account_first_name" value="<?php echo esc_attr($user->first_name); ?>"/>
    <input type="hidden" name="account_last_name" value="<?php echo esc_attr($user->last_name); ?>"/>
    <input type="hidden" name="account_display_name" value="<?php echo esc_attr($user->display_name); ?>"/>

    <div class="main-fields">
        <?php do_action('woocommerce_edit_account_form_start'); ?>

        <div class="fields-group">
            <div class="fields-group-title">
                <h3 class="e-title"><?php esc_html_e('Account details', 'growtype-wc'); ?></h3>
            </div>

            <div class="row g-3 fields-group-fields">
                <div class="e-wrapper col-md-6">
                    <label for="account_email" class="form-label"><?php esc_html_e('Email address', 'growtype-wc'); ?>
                        <span class="required">*</span></label>
                    <input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr($user->user_email); ?>"/>
                </div>
            </div>
        </div>

        <div class="fields-group">
            <div class="fields-group-title">
                <h3 class="e-title"><?php esc_html_e('Password details', 'growtype-wc'); ?></h3>
            </div>

            <div class="row g-3 fields-group-fields">
                <div class="e-wrapper col-md-6">
                    <label for="password_current" class="form-label"><?php esc_html_e('Current password (leave blank to leave unchanged)', 'growtype-wc'); ?></label>
                    <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="off"/>
                </div>

                <div class="e-wrapper col-md-6">
                    <label for="password_1" class="form-label"><?php esc_html_e('New password (leave blank to leave unchanged)', 'growtype-wc'); ?></label>
                    <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="off"/>
                </div>

                <div class="e-wrapper col-md-6">
                    <label for="password_2" class="form-label"><?php esc_html_e('Confirm new password', 'growtype-wc'); ?></label>
                    <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="off"/>
                </div>
            </div>
        </div>

        <?php do_action('woocommerce_edit_account_form'); ?>
    </div>

    <div class="row row-submit mt-4 pt-3 pb-5 mb-4">
        <div class="d-grid gap-2 d-md-flex">
            <?php wp_nonce_field('save_account_details', 'save-account-details-nonce'); ?>
            <button type="submit" class="woocommerce-Button button btn btn-primary" name="save_account_details" value="<?php esc_attr_e('Save changes', 'growtype-wc'); ?>"><?php esc_html_e('Save changes', 'growtype-wc'); ?></button>
            <input type="hidden" name="action" value="save_account_details"/>

            <?php
            $delete_url = add_query_arg('wc-api', 'wc-delete-account', home_url('/'));
            $delete_url = wp_nonce_url($delete_url, 'wc_delete_user');
            if (!current_user_can('manage_options')) {
                growtype_wc_render_account_delete_form($delete_url);
            }
            ?>
        </div>
    </div>
</form>

<?php do_action('woocommerce_after_edit_account_form'); ?>
