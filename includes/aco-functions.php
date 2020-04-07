<?php
/**
 * Functions file for the plugin.
 *
 * @package  Avarda_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Maybe creates, stores a token as a transient and returns.AMFReader
 *
 * @return string
 */
function aco_maybe_create_token() {
	$token = get_transient( 'aco_auth_token' );
	if ( false === $token ) {
		$avarda_payment = ACO_WC()->api->request_token();
		if ( ! $avarda_payment ) {
			return;
		}
		// Set transient with 55minute life time.
		set_transient( 'aco_auth_token', $avarda_payment['token'], 55 * MINUTE_IN_SECONDS );
		$token = $avarda_payment['token'];
	}
	return $token;
}


/**
 * Initialize the Avarda payment.
 *
 * @return array
 */
function aco_wc_initialize_payment() {
	// Need to calculate these here, because WooCommerce hasn't done it yet.
	WC()->cart->calculate_fees();
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();

	// Initialize payment.
	$avarda_payment = ACO_WC()->api->request_initialize_payment();
	if ( ! $avarda_payment ) {
		return;
	}
	WC()->session->set( 'aco_wc_purchase_id', $avarda_payment['purchaseId'] );
	WC()->session->set( 'aco_wc_jwt', $avarda_payment['jwt'] );
	return $avarda_payment;

}

/**
 * Avarda checkout form.
 */
function aco_wc_show_checkout_form() {
	if ( null === WC()->session->get( 'aco_wc_jwt' ) ) {
		aco_wc_initialize_payment();
	}
	?>
	<div id="checkout-form">
	</div>
	<?php
}

/**
 * Confirms and finishes the Avarda Order for processing.
 *
 * @param int    $order_id The WooCommerce Order id.
 * @param string $avarda_purchase_id The Avarda purchase id.
 * @return void
 */
function aco_confirm_avarda_order( $order_id = null, $avarda_purchase_id ) {
	if ( $order_id ) {
		$order        = wc_get_order( $order_id );
		$avarda_order = ACO_WC()->api->request_get_payment( $avarda_purchase_id );

		if ( 'Completed' === $avarda_order['state'] ) {
			// Payment complete and set transaction id.
			// translators: Avarda purchase ID.
			$note = sprintf( __( 'Payment via Avarda Checkout. Purchase ID: %s', 'avarda-checkout-for-woocommerce' ), sanitize_text_field( $avarda_order['purchaseId'] ) );
			$order->add_order_note( $note );
			$order->payment_complete( $avarda_purchase_id );
			do_action( 'aco_wc_payment_complete', $order_id, $avarda_order );
		}
	}
}


/**
 * Unsets the sessions used by the plguin.
 *
 * @return void
 */
function aco_wc_unset_sessions() {
	WC()->session->__unset( 'aco_wc_purchase_id' );
	WC()->session->__unset( 'aco_wc_jwt' );
}

/**
 * Prints error message as notices.
 *
 * @param WP_Error $wp_error A WordPress error object.
 * @return void
 */
function aco_extract_error_message( $wp_error ) {
	wc_print_notice( $wp_error->get_error_message(), 'error' );
}

/**
 * Shows select another payment method button in Avarda Checkout page.
 */
function aco_wc_show_another_gateway_button() {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

	if ( count( $available_gateways ) > 1 ) {
		$settings                   = get_option( 'woocommerce_aco_settings' );
		$select_another_method_text = isset( $settings['select_another_method_text'] ) && '' !== $settings['select_another_method_text'] ? $settings['select_another_method_text'] : __( 'Select another payment method', 'klarna-checkout-for-woocommerce' );

		?>
		<p class="avarda-checkout-select-other-wrapper">
			<a class="checkout-button button" href="#" id="avarda-checkout-select-other">
				<?php echo $select_another_method_text; ?>
			</a>
		</p>
		<?php
	}
}

