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
	$avarda_payment = aco_wc_initialize_payment();
	?>
	<div id="checkout-form">
	</div>
	<?php
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
 * Maybe shows error messages if any are set.
 *
 * @return void
 */
/*
 function aco_maybe_show_validation_error_message() { TODO
	if ( isset( $_GET['aco_validation_error'] ) && is_checkout() ) {
		$errors = json_decode( base64_decode( $_GET['aco_validation_error'] ), true );
		foreach ( $errors as $error ) {
			wc_add_notice( $error, 'error' );
		}
	}
} */

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

