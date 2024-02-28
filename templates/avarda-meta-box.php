<?php
/**
 * The HTML for the admin order metabox content.
 *
 * @package Avarda_Checkout_For_WooCommerce/Templates
 */

foreach ( $keys_for_meta_box as $item ) {
	echo wp_kses_post( $item['before'] ?? '' );
	if ( empty( $item['title'] ) ) {
		?>
		<p><?php echo wp_kses_post( $item['value'] ); ?></p>
		<?php
	} else {
		?>
		<p><b><?php echo wp_kses_post( $item['title'] ); ?></b>: <?php echo wp_kses_post( $item['value'] ); ?></p>
		<?php
	}
	echo wp_kses_post( $item['after'] ?? '' );
}
