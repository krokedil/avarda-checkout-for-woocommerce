<?php
/**
 * The HTML for the admin order metabox content.
 *
 * @package Avarda_Checkout_For_WooCommerce/Templates
 */

foreach ( $keys_for_meta_box as $item ) {
	?>
	<p><b><?php echo esc_html( $item['title'] ); ?></b>: <?php echo wp_kses_post( $item['value'] ); ?></p>
	<?php
}

if ( 'yes' !== $manage_orders ) {
	return;
}
if ( ! empty( $order_total_mismatch ) && in_array( $avarda_order_status, array( 'Completed' ), true ) ) {
	?>
	<div class="walley_sync_wrapper">
		<button class="button-secondary aco-refund-remaining-btn"><?php esc_html_e( 'Refund remaining amount in Avarda', 'collector-checkout-for-woocommerce' ); ?></button>
	</div>
	<?php
}


