<?php
/**
 * Avarda_Checkout checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package Avarda_Checkout/Templates
 */


do_action( 'aco_wc_before_checkout_form' );
do_action( 'woocommerce_before_checkout_form', WC()->checkout() );
// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>
<form name="checkout" class="checkout woocommerce-checkout">
	<?php do_action( 'aco_wc_before_wrapper' ); ?>
	<div id="aco-wrapper">
		<div id="aco-order-review">
			<?php do_action( 'aco_wc_before_order_review' ); ?>
			<?php woocommerce_order_review(); ?>
			<?php do_action( 'aco_wc_after_order_review' ); ?>
		</div>
	</div>
	<?php do_action( 'aco_wc_after_wrapper' ); ?>
</form>
<?php do_action( 'aco_wc_after_checkout_form' ); ?>
<div id="aco-iframe">
	<?php do_action( 'aco_wc_before_avarda_checkout_form' ); ?>
	<?php aco_wc_show_checkout_form(); ?>
	<?php do_action( 'aco_wc_after_avarda_checkout_form' ); ?>
</div>
