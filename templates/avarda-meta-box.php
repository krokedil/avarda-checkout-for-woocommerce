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
		<p class="aco-metabox-line"><span class="aco-metabox-line-title"><?php echo wp_kses_post( $item['title'] ); ?>:</span> <span class="aco-metabox-line-value"><?php echo wp_kses_post( $item['value'] ); ?></span></p>
		<?php
	}
	echo wp_kses_post( $item['after'] ?? '' );
}
