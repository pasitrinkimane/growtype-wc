<?php
/**
 * Order details table shown in emails.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-order-details.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$text_align = is_rtl() ? 'right' : 'left';

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

$heading_class              = $email_improvements_enabled ? 'email-order-detail-heading' : '';
$order_table_class          = $email_improvements_enabled ? 'email-order-details' : '';
$order_total_text_align     = $email_improvements_enabled ? 'right' : 'left';
$order_quantity_text_align  = $email_improvements_enabled ? 'right' : 'left';

if ( $email_improvements_enabled ) {
	add_filter( 'woocommerce_order_shipping_to_display_shipped_via', '__return_false' );
}

/**
 * Action hook to add custom content before order details in email.
 *
 * @param WC_Order $order Order object.
 * @param bool     $sent_to_admin Whether it's sent to admin or customer.
 * @param bool     $plain_text Whether it's a plain text email.
 * @param WC_Email $email Email object.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

<h2 class="<?php echo esc_attr( $heading_class ); ?>" style="font-size: 18px; font-weight: 700; line-height: 1.2; margin: 24px 0 16px; margin-top: 20px; color: #111827; mso-line-height-rule: exactly;">
	<?php
	if ( $email_improvements_enabled ) {
		echo wp_kses_post( __( 'Order summary', 'woocommerce' ) );
	}
	if ( $sent_to_admin ) {
		$before = '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">';
		$after  = '</a>';
	} else {
		$before = '';
		$after  = '';
	}
	if ( $email_improvements_enabled ) {
		echo '<br><span style="display: block; font-size: 13px; font-weight: 400; color: #6B7280; margin-top: 4px;">';
	}
	/* translators: %s: Order ID. */
	$order_number_string = __( '[Order #%s]', 'woocommerce' );
	if ( $email_improvements_enabled ) {
		/* translators: %s: Order ID. */
		$order_number_string = __( 'Order #%s', 'woocommerce' );
	}
	echo wp_kses_post( $before . sprintf( $order_number_string . $after . ' (<time datetime="%s">%s</time>)', $order->get_order_number(), $order->get_date_created()->format( 'c' ), wc_format_datetime( $order->get_date_created() ) ) );
	if ( $email_improvements_enabled ) {
		echo '</span>';
	}
	?>
</h2>

<div style="margin-bottom: <?php echo $email_improvements_enabled ? '24px' : '40px'; ?>;">
	<table class="td font-family <?php echo esc_attr( $order_table_class ); ?>" cellspacing="0" cellpadding="6" style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden; margin-bottom: 0; background-color: #ffffff;" border="0">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>; background-color: #F9FAFB; color: #4B5563; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 16px; border-bottom: 1px solid #E5E7EB; text-align: left;"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $order_quantity_text_align ); ?>; background-color: #F9FAFB; color: #4B5563; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 16px; border-bottom: 1px solid #E5E7EB; text-align: left;"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $order_total_text_align ); ?>; background-color: #F9FAFB; color: #4B5563; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 16px; border-bottom: 1px solid #E5E7EB; text-align: left;"><?php esc_html_e( 'Price', 'woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$image_size = $email_improvements_enabled ? 48 : 32;
			echo wc_get_email_order_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$order,
				array(
					'show_sku'      => $sent_to_admin,
					'show_image'    => $email_improvements_enabled,
					'image_size'    => array( $image_size, $image_size ),
					'plain_text'    => $plain_text,
					'sent_to_admin' => $sent_to_admin,
				)
			);
			?>
		</tbody>
	</table>
	<table class="td font-family <?php echo esc_attr( $order_table_class ); ?>" cellspacing="0" cellpadding="6" style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden; margin-bottom: 0; background-color: #ffffff;" border="0">
		<?php
		$item_totals       = $order->get_order_item_totals();
		
		if ( get_option( 'growtype_wc_disable_payment_method_in_emails' ) === 'yes' ) {
			unset( $item_totals['payment_method'] );
		}

		$item_totals_count = count( $item_totals );

		if ( $item_totals ) {
			$i = 0;
			foreach ( $item_totals as $total ) {
				++$i;
				$last_class = ( $i === $item_totals_count ) ? ' order-totals-last' : '';
				?>
				<tr class="order-totals order-totals-<?php echo esc_attr( $total['type'] ?? 'unknown' ); ?><?php echo esc_attr( $last_class ); ?>" style="background-color: #F9FAFB;">
					<th class="td text-align-left" scope="row" colspan="2" style="<?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?> padding: 5px 16px; font-size: 14px; text-align: left;">
						<?php
						echo wp_kses_post( $total['label'] ) . ' ';
						if ( $email_improvements_enabled ) {
							echo isset( $total['meta'] ) ? wp_kses_post( $total['meta'] ) : '';
						}
						?>
					</th>
					<td class="td text-align-<?php echo esc_attr( $order_total_text_align ); ?>" style="<?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?> padding: 16px; border-bottom: 1px solid #F3F4F6; color: #374151; font-size: 14px; vertical-align: middle; line-height: 1.5; mso-line-height-rule: exactly; padding: 10px 16px; font-size: 14px; text-align:right;"><?php echo wp_kses_post( $total['value'] ); ?></td>
				</tr>
				<?php
			}
		}
		if ( $order->get_customer_note() && ! $email_improvements_enabled ) {
			?>
			<tr>
				<th class="td text-align-left" scope="row" colspan="2"><?php esc_html_e( 'Note:', 'woocommerce' ); ?></th>
				<td class="td text-align-left"><?php echo wp_kses( nl2br( wc_wptexturize_order_note( $order->get_customer_note() ) ), array() ); ?></td>
			</tr>
			<?php
		}
		?>
	</table>
	<?php if ( $order->get_customer_note() && $email_improvements_enabled ) { ?>
		<table class="td font-family <?php echo esc_attr( $order_table_class ); ?>" cellspacing="0" cellpadding="6" style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden; margin-bottom: 0; background-color: #ffffff;" border="0" role="presentation">
			<tr class="order-customer-note">
				<td class="td text-align-left" style="padding: 16px; border-bottom: 1px solid #F3F4F6; color: #374151; font-size: 14px; vertical-align: middle; text-align: left; line-height: 1.5; mso-line-height-rule: exactly;">
					<b><?php esc_html_e( 'Customer note', 'woocommerce' ); ?></b><br>
					<?php echo wp_kses( nl2br( wc_wptexturize_order_note( $order->get_customer_note() ) ), array( 'br' => array() ) ); ?>
				</td>
			</tr>
		</table>
	<?php } ?>
</div>

<?php
if ( $email_improvements_enabled ) {
	remove_filter( 'woocommerce_order_shipping_to_display_shipped_via', '__return_false' );
}

/**
 * Action hook to add custom content after order details in email.
 *
 * @param WC_Order $order Order object.
 * @param bool     $sent_to_admin Whether it's sent to admin or customer.
 * @param bool     $plain_text Whether it's a plain text email.
 * @param WC_Email $email Email object.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
?>
