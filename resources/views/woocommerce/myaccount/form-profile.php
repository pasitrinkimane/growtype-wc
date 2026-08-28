<?php
defined('ABSPATH') || exit;

$user = wp_get_current_user();
$user_id = $user->ID;

$profile_picture = get_user_meta($user_id, 'profile_picture', true);
$default_avatar = get_avatar_url($user_id, ['size' => 300]);
$current_photo = !empty($profile_picture) ? $profile_picture : $default_avatar;
$has_custom_photo = !empty($profile_picture);
$profile_picture_max_bytes = Growtype_Wc_Account_Profile::PROFILE_PICTURE_MAX_BYTES;
$profile_picture_allowed_types = Growtype_Wc_Account_Profile::PROFILE_PICTURE_ALLOWED_MIME_TYPES;
$profile_picture_validation_message = __('Choose a JPEG, PNG, or WebP image up to 3 MB.', 'growtype-wc');

do_action('woocommerce_before_edit_account_form');
?>

<form class="woocommerce-EditAccountForm edit-account" action="" method="post" enctype="multipart/form-data" <?php do_action('woocommerce_edit_account_form_tag'); ?> >
    <input type="hidden" name="is_profile_form" value="1"/>

    <div class="main-fields">
        <?php do_action('woocommerce_edit_account_form_start'); ?>

        <div class="fields-group">
            <div class="fields-group-title">
                <h3 class="e-title"><?php esc_html_e('Public details', 'growtype-wc'); ?></h3>
            </div>

            <div class="row g-3 fields-group-fields">
                <div class="e-wrapper col-md-12">
                    <label class="form-label"><?php esc_html_e('Profile photo', 'growtype-wc'); ?></label>
                    <div class="profile-avatar d-flex align-items-center gap-3">
                        <div class="position-relative" style="width:54px;height:54px;cursor:pointer;" onclick="document.getElementById('profilePhotoUpload').click();">
                            <?php echo growtype_wc_get_account_avatar_html($user_id, 54); ?>
                            <?php if ($has_custom_photo) : ?>
                                <button type="button" class="btn-close-avatar" onclick="event.stopPropagation(); document.getElementById('deleteProfilePicture').value='1'; this.form.submit();" title="<?php esc_attr_e('Remove photo', 'growtype-wc'); ?>" style="position:absolute;top:-4px;right:-4px;width:20px;height:20px;border-radius:50%;background:#ffffff;border:1px solid #ddd;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0;box-shadow:0 2px 4px rgba(0,0,0,0.15);">
                                    <span class="dashicons dashicons-no-alt" style="font-size:14px;width:14px;height:14px;line-height:14px;color:#000;"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="delete_profile_picture" id="deleteProfilePicture" value="0"/>
                        <input type="file" id="profilePhotoUpload" name="profile_picture" accept="<?php echo esc_attr(implode(',', $profile_picture_allowed_types)); ?>" data-max-file-size="<?php echo esc_attr($profile_picture_max_bytes); ?>" style="display:none;" onchange="const file=this.files[0];const allowed=<?php echo esc_attr(wp_json_encode($profile_picture_allowed_types)); ?>;if(file&amp;&amp;(file.size&gt;<?php echo esc_attr($profile_picture_max_bytes); ?>||(file.type&amp;&amp;!allowed.includes(file.type)))){window.alert('<?php echo esc_js($profile_picture_validation_message); ?>');this.value='';return;}this.form.submit();"/>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('profilePhotoUpload').click();">
                            <?php echo $has_custom_photo ? __('Change photo', 'growtype-wc') : __('Upload photo', 'growtype-wc'); ?>
                        </button>
                    </div>
                    <small class="form-text text-muted"><?php echo esc_html($profile_picture_validation_message); ?></small>
                </div>

                <div class="e-wrapper col-md-3">
                    <label for="account_display_name" class="form-label"><?php esc_html_e('Display name', 'growtype-wc'); ?>
                        <span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" value="<?php echo esc_attr($user->display_name); ?>" placeholder="<?php esc_attr_e('Display name', 'growtype-wc'); ?>"/>
                </div>
            </div>
        </div>

        <div class="fields-group mt-4">
            <div class="fields-group-title">
                <h3 class="e-title"><?php esc_html_e('Personal details', 'growtype-wc'); ?></h3>
            </div>

            <div class="row g-3 fields-group-fields">
                <div class="e-wrapper col-md-3">
                    <label for="account_first_name" class="form-label"><?php esc_html_e('First name', 'growtype-wc'); ?>
                        <span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr($user->first_name); ?>" placeholder="<?php esc_attr_e('First name', 'growtype-wc'); ?>"/>
                </div>

                <div class="e-wrapper col-md-3">
                    <label for="account_last_name" class="form-label"><?php esc_html_e('Last name', 'growtype-wc'); ?>
                        <span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr($user->last_name); ?>" placeholder="<?php esc_attr_e('Last name', 'growtype-wc'); ?>"/>
                </div>

                <?php do_action('woocommerce_edit_account_form_personal_details'); ?>
            </div>
        </div>

        <!-- Hidden email field so WooCommerce save_account_details validation passes -->
        <input type="hidden" name="account_email" id="account_email" value="<?php echo esc_attr($user->user_email); ?>"/>

        <?php do_action('woocommerce_edit_account_form'); ?>
    </div>

    <div class="row row-submit mt-4 pt-3 pb-5 mb-4">
        <div class="d-grid gap-2 d-md-flex">
            <?php wp_nonce_field('save_account_details', 'save-account-details-nonce'); ?>
            <button type="submit" class="woocommerce-Button button btn btn-primary" name="save_account_details" value="<?php esc_attr_e('Save changes', 'growtype-wc'); ?>"><?php esc_html_e('Save changes', 'growtype-wc'); ?></button>
            <input type="hidden" name="action" value="save_account_details"/>
        </div>
    </div>
</form>

<?php do_action('woocommerce_after_edit_account_form'); ?>
