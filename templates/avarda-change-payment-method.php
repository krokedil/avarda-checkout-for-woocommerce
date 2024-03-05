<?php
/**
 * Avarda_Checkout change payment method page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package Avarda_Checkout/Templates
 */

do_action( 'aco_wc_before_checkout_form' );
?>

<div id="aco-iframe">
	<?php do_action( 'aco_wc_before_avarda_checkout_form' ); ?>
	<?php aco_wc_show_checkout_form(); ?>
	<?php do_action( 'aco_wc_after_avarda_checkout_form' ); ?>
</div>
