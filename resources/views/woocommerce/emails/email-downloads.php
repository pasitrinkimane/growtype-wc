<?php
/**
 * Email Downloads.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-downloads.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.4.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

?><h2 class="<?php echo $email_improvements_enabled ? 'email-order-detail-heading' : ''; ?>" style="font-size: 18px; font-weight: 700; line-height: 1.2; margin: 24px 0 16px; margin-top: 20px; color: #111827; mso-line-height-rule: exactly;"><?php esc_html_e( 'Downloads', 'woocommerce' ); ?></h2>

<div style="margin-bottom: <?php echo $email_improvements_enabled ? '24px' : '40px'; ?>;">
	<table class="td font-family <?php echo $email_improvements_enabled ? ' email-order-details' : ''; ?>" cellspacing="0" cellpadding="6" style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden; margin-bottom: 0; background-color: #ffffff;" border="0">
		<thead>
			<tr>
				<?php foreach ( $columns as $column_id => $column_name ) : ?>
					<?php 
						$is_last = array_key_last( $columns ) === $column_id;
						$column_text_align = $email_improvements_enabled && $is_last ? 'right' : 'left';
					?>
					<th class="td" scope="col" style="text-align:<?php echo esc_attr( $column_text_align ); ?>; background-color: #F9FAFB; color: #4B5563; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 16px; border-bottom: 1px solid #E5E7EB;">
						<?php echo esc_html( $column_name ); ?>
					</th>
				<?php endforeach; ?>
			</tr>
		</thead>

		<tbody>
			<?php foreach ( $downloads as $download ) : ?>
				<tr>
					<?php foreach ( $columns as $column_id => $column_name ) : ?>
						<?php
						$is_last = array_key_last( $columns ) === $column_id;
						$column_text_align = $email_improvements_enabled && $is_last ? 'right' : 'left';
						
						$cell_style = 'padding: 12px 16px; border-bottom: 1px solid #F3F4F6; color: #374151; font-size: 14px; vertical-align: middle; line-height: 1.5; mso-line-height-rule: exactly; text-align:' . $column_text_align . ';';
						
						if ( 'download-product' === $column_id ) :
							?>
							<th class="td" scope="row" style="<?php echo esc_attr( $cell_style ); ?>">
						<?php else : ?>
							<td class="td" style="<?php echo esc_attr( $cell_style ); ?>">
						<?php endif; ?>
							<?php
							if ( has_action( 'woocommerce_email_downloads_column_' . $column_id ) ) {
								do_action( 'woocommerce_email_downloads_column_' . $column_id, $download, $plain_text );
							} else {
								switch ( $column_id ) {
									case 'download-product':
										?>
										<a href="<?php echo esc_url( get_permalink( $download['product_id'] ) ); ?>" style="color: inherit; font-weight: 600; text-decoration: none;"><?php echo wp_kses_post( $download['product_name'] ); ?></a>
										<?php
										break;
									case 'download-file':
										?>
										<a href="<?php echo esc_url( $download['download_url'] ); ?>" class="woocommerce-MyAccount-downloads-file button alt" style="display: inline-block; padding: 8px 12px; color: #374151; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600;"><?php echo esc_html( $download['download_name'] ); ?></a>
										<?php
										break;
									case 'download-expires':
										if ( ! empty( $download['access_expires'] ) ) {
											?>
											<time datetime="<?php echo esc_attr( date( 'Y-m-d', strtotime( $download['access_expires'] ) ) ); ?>" title="<?php echo esc_attr( strtotime( $download['access_expires'] ) ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $download['access_expires'] ) ) ); ?></time>
											<?php
										} else {
											esc_html_e( 'Never', 'woocommerce' );
										}
										break;
								}
							}
							?>
							<?php if ( 'download-product' === $column_id ) : ?>
								</th>
							<?php else : ?>
								</td>
							<?php endif; ?>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
