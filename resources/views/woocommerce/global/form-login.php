<?php
/**
 * Custom WooCommerce Login Form Template using growtype-form.
 *
 * This template overrides the default woocommerce/templates/global/form-login.php template.
 * It integrates the premium growtype-form login layout.
 *
 * @package WooCommerce\Templates
 * @version 9.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( is_user_logged_in() ) {
	return;
}

if ( class_exists( 'Growtype_Form_General' ) ) {
	$growtype_form_general = new Growtype_Form_General();
	echo $growtype_form_general->form_init([
		'name'           => 'login',
		'redirect_after' => $redirect ?? '',
	]);
} else {
	// Fallback to standard WooCommerce form if growtype-form is disabled
	?>
	<form class="woocommerce-form woocommerce-form-login login" method="post" <?php echo ( $hidden ) ? 'style="display:none;"' : ''; ?>>

		<?php do_action( 'woocommerce_login_form_start' ); ?>

		<?php echo ( $message ) ? wpautop( wptexturize( $message ) ) : ''; ?>

		<p class="form-row form-row-first">
			<label for="username"><?php esc_html_e( 'Username or email', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input type="text" class="input-text" name="username" id="username" autocomplete="username" required aria-required="true" />
		</p>
		<p class="form-row form-row-last">
			<label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input class="input-text woocommerce-Input" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
		</p>
		<div class="clear"></div>

		<?php do_action( 'woocommerce_login_form' ); ?>

		<p class="form-row">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
				<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
			</label>
			<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
			<input type="hidden" name="redirect" value="<?php echo esc_url( $redirect ); ?>" />
			<button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'Login', 'woocommerce' ); ?>"><?php esc_html_e( 'Login', 'woocommerce' ); ?></button>
		</p>
		<p class="lost_password">
			<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
		</p>

		<div class="clear"></div>

		<?php do_action( 'woocommerce_login_form_end' ); ?>

	</form>
	<?php
}
