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
	$token    = get_transient( 'aco_auth_token' );
	$currency = get_transient( 'aco_currency' );
	if ( false === $token || get_woocommerce_currency() !== $currency ) { // update token if currency is changed.
		$avarda_payment = ACO_WC()->api->request_token();
		if ( ! $avarda_payment ) {
			return;
		}
		// Set transient with 55minute life time.
		set_transient( 'aco_auth_token', $avarda_payment['token'], 55 * MINUTE_IN_SECONDS );
		set_transient( 'aco_currency', get_woocommerce_currency(), 55 * MINUTE_IN_SECONDS );
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
	} else {
		$avarda_purchase_id = WC()->session->get( 'aco_wc_purchase_id' );
		// Initialize new payment if current timed out.
		$avarda_payment = ACO_WC()->api->request_get_payment( $avarda_purchase_id );
		$aco_state      = '';
		if ( 'B2C' === $avarda_payment['mode'] ) {
			$aco_state = $avarda_payment['b2C']['step']['current'];
		} elseif ( 'B2B' === $avarda_payment['mode'] ) {
			$aco_state = $avarda_payment['b2B']['step']['current'];
		}
		if ( 'TimedOut' === $aco_state ) {
			aco_wc_initialize_payment();
		} elseif ( ! ( 'Completed' === $aco_state || 'TimedOut' === $aco_state ) ) {
			ACO_WC()->api->request_update_payment( $avarda_purchase_id, true );
		}
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
		$order = wc_get_order( $order_id );

		// If the order is already completed, return.
		if ( ! empty( $order->get_date_paid() ) ) {
			return;
		}

		// Get the Avarda order.
		$avarda_order = ACO_WC()->api->request_get_payment( $avarda_purchase_id );

		// Populate wc order address.
		aco_populate_wc_order( $order, $avarda_order );

		// Check if B2C or B2B.
		$aco_state = '';
		if ( 'B2C' === $avarda_order['mode'] ) {
			$aco_state = $avarda_order['b2C']['step']['current'];
		} elseif ( 'B2B' === $avarda_order['mode'] ) {
			$aco_state = $avarda_order['b2B']['step']['current'];
		}

		if ( 'Completed' === $aco_state ) {
			ACO_WC()->api->request_update_order_reference( $avarda_purchase_id, $order_id ); // Update order reference.
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
 * Confirms and finishes the Avarda Subscription for processing.
 *
 * @param int    $subscription_id The WooCommerce Subscription id.
 * @param string $avarda_purchase_id The Avarda purchase id.
 * @return void
 */
function aco_confirm_subscription( $subscription_id, $avarda_purchase_id ) {
	$subscription    = wc_get_order( $subscription_id );
	$avarda_order    = ACO_WC()->api->request_get_payment( $avarda_purchase_id );
	$recurring_token = $avarda_order['paymentMethods']['selectedPayment']['recurringPaymentToken'];
	update_post_meta( $subscription->get_id(), '_aco_recurring_token', $recurring_token );
	update_post_meta( $subscription->get_id(), '_wc_avarda_purchase_id', $avarda_purchase_id );

	// translators: %s Avarda recurring token.
	$note = sprintf( __( 'New recurring token for subscription: %s', 'avarda-checkout-for-woocommerce' ), sanitize_key( $recurring_token ) );
	$subscription->add_order_note( $note );
}

/**
 * Populates the wc order address.
 *
 * @param WC_Order $order The WC Order.
 * @param array    $avarda_order The Avarda order.
 * @return void
 */
function aco_populate_wc_order( $order, $avarda_order ) {

	$order_id = $order->get_id();

	$user_inputs       = array();
	$invoicing_address = array();
	$delivery_address  = array();
	if ( 'B2C' === $avarda_order['mode'] ) {
		$user_inputs       = $avarda_order['b2C']['userInputs'];
		$invoicing_address = $avarda_order['b2C']['invoicingAddress'];
		$delivery_address  = $avarda_order['b2C']['deliveryAddress'];
	} elseif ( 'B2B' === $avarda_order['mode'] ) {
		$user_inputs       = $avarda_order['b2B']['userInputs'];
		$invoicing_address = $avarda_order['b2B']['invoicingAddress'];
		$delivery_address  = $avarda_order['b2B']['deliveryAddress'];
	}

	$shipping_data = array(
		'first_name' => isset( $delivery_address['firstName'] ) ? $delivery_address['firstName'] : $invoicing_address['firstName'],
		'last_name'  => isset( $delivery_address['lastName'] ) ? $delivery_address['lastName'] : $invoicing_address['lastName'],
		'country'    => isset( $delivery_address['country'] ) ? $delivery_address['country'] : $invoicing_address['country'],
		'address1'   => isset( $delivery_address['address1'] ) ? $delivery_address['address1'] : $invoicing_address['address1'],
		'address2'   => isset( $delivery_address['address2'] ) ? $delivery_address['address2'] : $invoicing_address['address2'],
		'city'       => isset( $delivery_address['city'] ) ? $delivery_address['city'] : $invoicing_address['city'],
		'zip'        => isset( $delivery_address['zip'] ) ? $delivery_address['zip'] : $invoicing_address['zip'],
	);

	// Set Avarda payment method title.
	aco_set_payment_method_title( $order, $avarda_order );

	// First name.
	$order->set_billing_first_name( sanitize_text_field( $invoicing_address['firstName'] ) );
	$order->set_shipping_first_name( sanitize_text_field( $shipping_data['first_name'] ) );
	// Last name.
	$order->set_billing_last_name( sanitize_text_field( $invoicing_address['lastName'] ) );
	$order->set_shipping_last_name( sanitize_text_field( $shipping_data['last_name'] ) );
	// Country.
	$order->set_billing_country( strtoupper( sanitize_text_field( $invoicing_address['country'] ) ) );
	$order->set_shipping_country( strtoupper( sanitize_text_field( $shipping_data['country'] ) ) );
	// Street address1.
	$order->set_billing_address_1( sanitize_text_field( $invoicing_address['address1'] ) );
	$order->set_shipping_address_1( sanitize_text_field( $shipping_data['address1'] ) );
	// Street address2.
	$order->set_billing_address_2( sanitize_text_field( $invoicing_address['address2'] ) );
	$order->set_shipping_address_2( sanitize_text_field( $shipping_data['address2'] ) );
	// City.
	$order->set_billing_city( sanitize_text_field( $invoicing_address['city'] ) );
	$order->set_shipping_city( sanitize_text_field( $shipping_data['city'] ) );
	// Postcode.
	$order->set_billing_postcode( sanitize_text_field( $invoicing_address['zip'] ) );
	$order->set_shipping_postcode( sanitize_text_field( $shipping_data['zip'] ) );
	// Phone.
	$order->set_billing_phone( sanitize_text_field( $user_inputs['phone'] ) );
	// Email.
	$order->set_billing_email( sanitize_text_field( $user_inputs['email'] ) );

	// Save order.
	$order->save();

}

/**
 * Get Avarda Checkout order payment method title.
 *
 * @param object $order The WooCommerce order.
 * @param array  $avarda_order The Avarda order.
 * @return void
 */
function aco_set_payment_method_title( $order, $avarda_order ) {
	$aco_payment_method = '';
	if ( isset( $avarda_order['paymentMethods']['selectedPayment']['type'] ) ) {
		$aco_payment_method = sanitize_text_field( $avarda_order['paymentMethods']['selectedPayment']['type'] );
		update_post_meta( $order->get_id(), '_avarda_payment_method', $aco_payment_method );
	}

	switch ( $aco_payment_method ) {
		case 'Invoice':
			$method_title = __( 'Avarda Invoice', 'avarda-checkout-for-woocommerce' );
			break;
		case 'Loan':
			$method_title = __( 'Avarda Loan', 'avarda-checkout-for-woocommerce' );
			break;
		case 'Card':
			$method_title = __( 'Avarda Card', 'avarda-checkout-for-woocommerce' );
			break;
		case 'DirectPayment':
			$method_title = __( 'Avarda Direct Payment', 'avarda-checkout-for-woocommerce' );
			break;
		case 'PartPayment':
			$method_title = __( 'Avarda Part Payment', 'avarda-checkout-for-woocommerce' );
			break;
		case 'Swish':
			$method_title = __( 'Avarda Swish', 'avarda-checkout-for-woocommerce' );
			break;
		case 'HighAmountLoan':
			$method_title = __( 'Avarda High Amount Loan', 'avarda-checkout-for-woocommerce' );
			break;
		case 'PayPal':
			$method_title = __( 'Avarda PayPal', 'avarda-checkout-for-woocommerce' );
			break;
		case 'PayOnDelivery':
			$method_title = __( 'Avarda Pay On Delivery', 'avarda-checkout-for-woocommerce' );
			break;
		case 'B2BInvoice':
			$method_title = __( 'Avarda B2B Invoice', 'avarda-checkout-for-woocommerce' );
			break;
		case 'DirectInvoice':
			$method_title = __( 'Avarda Direct Invoice', 'avarda-checkout-for-woocommerce' );
			break;
		case 'Masterpass':
			$method_title = __( 'Avarda Masterpass', 'avarda-checkout-for-woocommerce' );
			break;
		case 'MobilePay':
			$method_title = __( 'Avarda MobilePay', 'avarda-checkout-for-woocommerce' );
			break;
		case 'Vipps':
			$method_title = __( 'Avarda Vipps', 'avarda-checkout-for-woocommerce' );
			break;
		case 'ZeroAmount':
			$method_title = __( 'Avarda Zero Amount', 'avarda-checkout-for-woocommerce' );
			break;
		default:
			$method_title = __( 'Avarda Checkout', 'avarda-checkout-for-woocommerce' );
	}

	$order->set_payment_method_title( $method_title );
	$order->save();
}


/**
 * Unsets the sessions used by the plguin.
 *
 * @return void
 */
function aco_wc_unset_sessions() {
	WC()->session->__unset( 'aco_wc_purchase_id' );
	WC()->session->__unset( 'aco_wc_jwt' );
	WC()->session->__unset( 'aco_update_md5' );
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
		$select_another_method_text = isset( $settings['select_another_method_text'] ) && '' !== $settings['select_another_method_text'] ? $settings['select_another_method_text'] : __( 'Select another payment method', 'avarda-checkout-for-woocommerce' );

		?>
		<p class="avarda-checkout-select-other-wrapper">
			<a class="checkout-button button" href="#" id="avarda-checkout-select-other">
				<?php echo esc_html( $select_another_method_text ); ?>
			</a>
		</p>
		<?php
	}
}

/**
 * Finds an Order ID based on a transaction ID (the Avarda order number).
 *
 * @param string $transaction_id Avarda order number saved as Transaction ID in WC order.
 * @return int The ID of an order, or 0 if the order could not be found.
 */
function aco_get_order_id_by_transaction_id( $transaction_id ) {
	$query_args = array(
		'fields'      => 'ids',
		'post_type'   => wc_get_order_types(),
		'post_status' => array_keys( wc_get_order_statuses() ),
		'meta_key'    => '_transaction_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'meta_value'  => sanitize_text_field( wp_unslash( $transaction_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'date_query'  => array(
			array(
				'after' => '30 day ago',
			),
		),
	);

	$orders = get_posts( $query_args );

	if ( $orders ) {
		$order_id = $orders[0];
	} else {
		$order_id = 0;
	}

	return $order_id;
}

