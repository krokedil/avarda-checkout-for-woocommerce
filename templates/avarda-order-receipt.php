<?php
/**
 * Avarda_Checkout change payment method page
 *
 * Overrides /checkout/order-receipt.php.
 *
 * @package Avarda_Checkout/Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'aco_wc_before_order_receipt' );

?>
<div class="aco-block">
	<div id="aco-iframe">
		<?php do_action( 'aco_wc_before_avarda_checkout_form_order_receipt' ); ?>
			<?php aco_wc_show_checkout_form( $order_id ); ?>
		<?php do_action( 'aco_wc_after_avarda_checkout_form_order_receipt' ); ?>
	</div>
</div>

<?php

do_action( 'aco_wc_after_order_receipt' );
