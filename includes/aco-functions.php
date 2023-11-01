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
		$response = ACO_WC()->api->request_token();
		if ( is_wp_error( $response ) || empty( $response['token'] ) ) {
			return;
		}
		// Set transient with 55minute life time.
		set_transient( 'aco_auth_token', $response['token'], 55 * MINUTE_IN_SECONDS );
		set_transient( 'aco_currency', get_woocommerce_currency(), 55 * MINUTE_IN_SECONDS );
		$token = $response['token'];
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
	if ( is_wp_error( $avarda_payment ) ) {
		return;
	}

	// Remove old payment data if a WooCommerce order already exist.
	$order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
	$order    = $order_id ? wc_get_order( $order_id ) : null;
	if ( $order ) {
		delete_post_meta( $order_id, '_wc_avarda_purchase_id' );
		delete_post_meta( $order_id, '_transaction_id' );
		$avarda_purchase_id = ( is_array( $avarda_payment ) && isset( $avarda_payment['purchaseId'] ) ) ? $avarda_payment['purchaseId'] : '';
		ACO_Logger::log( 'Delete _wc_avarda_purchase_id & _transaction_id during aco_wc_initialize_payment. Order ID: ' . $order_id . '. Avarda purchase ID: ' . $avarda_purchase_id );
	}

	WC()->session->set( 'aco_wc_payment_data', $avarda_payment );
	WC()->session->set( 'aco_language', ACO_WC()->checkout_setup->get_language() );
	WC()->session->set( 'aco_currency', get_woocommerce_currency() );
	WC()->session->set( 'aco_last_update_hash', WC()->cart->get_cart_hash() );
	return $avarda_payment;

}

/**
 * Avarda checkout form.
 *
 * @param bool $order_id WooCommerce order ID. If used, the order items will be fetched from the order.
 */
function aco_wc_show_checkout_form( $order_id = null ) {
	if ( $order_id ) {
		aco_wc_initialize_or_update_order_from_wc_order( $order_id );
	} else {
		aco_wc_initialize_or_update_order();
	}
	?>
	<div id="checkout-form">
	</div>
	<?php
}

/**
 * Initialize or update the Avarda payment.
 *
 * @return void
 */
function aco_wc_initialize_or_update_order() {

	$avarda_payment_data     = WC()->session->get( 'aco_wc_payment_data' );
	$avarda_purchase_id      = ( is_array( $avarda_payment_data ) && isset( $avarda_payment_data['purchaseId'] ) ) ? $avarda_payment_data['purchaseId'] : '';
	$avarda_jwt_expired_time = ( is_array( $avarda_payment_data ) && isset( $avarda_payment_data['expiredUtc'] ) ) ? $avarda_payment_data['expiredUtc'] : '';
	$avarda_jwt              = ( is_array( $avarda_payment_data ) && isset( $avarda_payment_data['jwt'] ) ) ? $avarda_payment_data['jwt'] : '';
	$token                   = ( time() < strtotime( $avarda_jwt_expired_time ) ) ? 'session' : 'new_token_required';

	if ( ! empty( $avarda_purchase_id ) ) {
		// We ha ve a purchase ID, get payment from Avarda.
		$avarda_payment = ACO_WC()->api->request_get_payment( $avarda_purchase_id );

		if ( is_wp_error( $avarda_payment ) ) {
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Avarda GET request failed in aco_wc_initialize_or_update_order. Clearing Avarda session.' );
			return;
		}

		// Get payment status.
		$aco_step = aco_get_payment_step( $avarda_payment );

		switch ( $aco_step ) {
			case 'Completed':
				// Payment already completed in Avarda. Let's redirect the customer to the thankyou/confirmation page.
				$order_id = aco_get_order_id_by_purchase_id( $avarda_purchase_id );
				$order    = wc_get_order( $order_id );

				if ( is_object( $order ) ) {
					$confirmation_url = add_query_arg(
						array(
							'aco_confirm'     => 'yes',
							'aco_purchase_id' => $avarda_purchase_id,
							'wc_order_id'     => $order_id,
						),
						$order->get_checkout_order_received_url()
					);
					wp_safe_redirect( $confirmation_url );
					exit;
				}
				break;
			case 'TimedOut':
				aco_wc_initialize_payment();
				break;
			default:
				if ( 'new_token_required' === $token || empty( $avarda_jwt ) || get_woocommerce_currency() !== WC()->session->get( 'aco_currency' ) || ACO_WC()->checkout_setup->get_language() !== WC()->session->get( 'aco_language' ) ) {
					aco_wc_initialize_payment();
				} else {

					// Make sure that payment session step is ok for an update.
					if ( ! in_array( $aco_step, aco_payment_steps_approved_for_update_request(), true ) ) {
						ACO_Logger::log( sprintf( 'Aborting update in aco_wc_initialize_or_update_order_from_wc_order function since Avarda payment session %s in step %s.', $avarda_purchase_id, $aco_step ) );
						return;
					}

					$avarda_payment = ACO_WC()->api->request_update_payment( $avarda_purchase_id, null, true );
					// If the update failed - unset sessions and return error.
					if ( is_wp_error( $avarda_payment ) ) {
						// Unset sessions.
						aco_wc_unset_sessions();
						ACO_Logger::log( 'Avarda update request failed in aco_wc_initialize_or_update_order function. Clearing Avarda session.' );
					}
				}
				break;
		}
	} else {
		// We have no purchase id, let's create a new session.
		aco_wc_initialize_payment();
	}
}

/**
 * Creates or updates a Avarda order for the Pay for order feature.
 *
 * @param int $order_id The WooCommerce order id.
 *
 * @return mixed
 */
function aco_wc_initialize_or_update_order_from_wc_order( $order_id ) {
		$order = wc_get_order($order_id);
		if ($order->get_meta('_wc_avarda_purchase_id') )  { // Check if we have an order id.
			$avarda_purchase_id      = $order->get_meta( '_wc_avarda_purchase_id', true );
			$avarda_jwt_expired_time = $order->get_meta( '_wc_avarda_expiredUtc', true );

			// We ha ve a purchase ID, get payment from Avarda.
			$avarda_payment = ACO_WC()->api->request_get_payment( $avarda_purchase_id );

		if ( is_wp_error( $avarda_payment ) ) {
			return;
		}

		// Get payment status.
		$aco_step = aco_get_payment_step( $avarda_payment );

		ACO_Logger::log( sprintf( 'Checking session for %s|%s (Avarda ID: %s). Session state: %s. Trying to initialize new or updating existing checkout session.', $order_id, $order->get_order_key(), $avarda_purchase_id, $aco_step ) );

		switch ( $aco_step ) {
			case 'Completed':
				// Payment already completed in Avarda. Let's redirect the customer to the thankyou/confirmation page.
				if ( is_object( $order ) ) {
					$confirmation_url = add_query_arg(
						array(
							'aco_confirm'     => 'yes',
							'aco_purchase_id' => $avarda_purchase_id,
							'wc_order_id'     => $order_id,
						),
						$order->get_checkout_order_received_url()
					);
					wp_safe_redirect( $confirmation_url );
					exit;
				}
				break;
			case 'TimedOut':
				$avarda_order = ACO_WC()->api->request_initialize_payment( $order_id );
				break;
			default:
				if ( strtotime( $avarda_jwt_expired_time ) < time() ) {
					$avarda_order = ACO_WC()->api->request_initialize_payment( $order_id );
					aco_wc_save_avarda_session_data_to_order( $order_id, $avarda_order );
				} else {

					// Make sure that payment session step is ok for an update.
					if ( ! in_array( $aco_step, aco_payment_steps_approved_for_update_request(), true ) ) {
						ACO_Logger::log( sprintf( 'Aborting update in aco_wc_initialize_or_update_order_from_wc_order function since Avarda payment session %s in step %s.', $avarda_purchase_id, $aco_step ) );
						return;
					}

					// Try to update the order.
					$avarda_order = ACO_WC()->api->request_update_payment( $avarda_purchase_id, $order_id, true );
				}
				break;
		}
		if ( is_wp_error( $avarda_order ) ) {
			ACO_Logger::log( sprintf( 'Checking session for %s|%s (Avarda ID: %s). Avarda order does not exist, initializing new checkout session.', $order_id, $order->get_order_key(), $avarda_purchase_id ) );

			// If update order failed try to create new order.
			$avarda_order = ACO_WC()->api->request_initialize_payment( $order_id );
			if ( is_wp_error( $avarda_order ) ) {
				// If failed then bail.
				ACO_Logger::log( sprintf( 'Checkout session initilization failed for %s|%s (Avarda ID: %s). Check for "ACO initialize payment" error.', $order_id, $order->get_data_keys(), $avarda_purchase_id ) );
				return;
			}
			aco_wc_save_avarda_session_data_to_order( $order_id, $avarda_order );
			return $avarda_order;
		}
		return $avarda_order;
	} else {
		ACO_Logger::log( sprintf( 'Checking session for %s|%s (Avarda ID: %s). Avarda order does not exist, initializing new checkout session.', $order_id, ( wc_get_order( $order_id ) )->get_order_key(), 'None' ) );

		// Create new order, since we dont have one.
		$avarda_order = ACO_WC()->api->request_initialize_payment( $order_id );
		if ( false === $avarda_order ) {
			ACO_Logger::log( sprintf( 'Checkout session initilization failed for %s|%s (Avarda ID: %s). Check for "ACO initialize payment" error.', $order_id, ( wc_get_order( $order_id ) )->get_order_key(), 'None' ) );
			return;
		}
		aco_wc_save_avarda_session_data_to_order( $order_id, $avarda_order );
		return $avarda_order;
	}
}

/**
 * Save fetched Avarda session data to WC order.
 *
 * @param int   $order_id The WooCommerce Order id.
 * @param array $avarda_order The Avarda session data.
 * @return void
 */
function aco_wc_save_avarda_session_data_to_order( $order_id, $avarda_order ) {
	// Check that we don't have an error.
	if ( is_wp_error( $avarda_order ) ) {
		return;
	}
	update_post_meta( $order_id, '_wc_avarda_purchase_id', sanitize_text_field( $avarda_order['purchaseId'] ) );
	update_post_meta( $order_id, '_wc_avarda_jwt', sanitize_text_field( $avarda_order['jwt'] ) );
	update_post_meta( $order_id, '_wc_avarda_expiredUtc', sanitize_text_field( $avarda_order['expiredUtc'] ) );
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

		// Let other plugins hook into this sequence.
		do_action( 'aco_wc_confirm_avarda_order', $order_id, $avarda_order );

		// Check if B2C or B2B.
		$aco_step = '';
		if ( 'B2C' === $avarda_order['mode'] ) {
			$aco_step = $avarda_order['b2C']['step']['current'];
		} elseif ( 'B2B' === $avarda_order['mode'] ) {
			$aco_step = $avarda_order['b2B']['step']['current'];
		}

		if ( 'Completed' === $aco_step ) {
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
	$billing_company   = '';
	if ( 'B2C' === $avarda_order['mode'] ) {
		$user_inputs         = $avarda_order['b2C']['userInputs'];
		$invoicing_address   = $avarda_order['b2C']['invoicingAddress'];
		$delivery_address    = $avarda_order['b2C']['deliveryAddress'];
		$billing_first_name  = $invoicing_address['firstName'];
		$billing_last_name   = $invoicing_address['lastName'];
		$shipping_first_name = isset( $delivery_address['firstName'] ) ? $delivery_address['firstName'] : $invoicing_address['firstName'];
		$shipping_last_name  = isset( $delivery_address['lastName'] ) ? $delivery_address['lastName'] : $invoicing_address['lastName'];
	} elseif ( 'B2B' === $avarda_order['mode'] ) {
		$user_inputs         = $avarda_order['b2B']['userInputs'];
		$invoicing_address   = $avarda_order['b2B']['invoicingAddress'];
		$delivery_address    = $avarda_order['b2B']['deliveryAddress'];
		$billing_company     = $invoicing_address['name'];
		$shipping_company    = $invoicing_address['name'];
		$billing_first_name  = isset( $avarda_order['b2B']['customerInfo']['firstName'] ) ? $avarda_order['b2B']['customerInfo']['firstName'] : '';
		$billing_last_name   = isset( $avarda_order['b2B']['customerInfo']['lastName'] ) ? $avarda_order['b2B']['customerInfo']['lastName'] : '';
		$shipping_first_name = isset( $avarda_order['b2B']['deliveryAddress']['firstName'] ) ? $avarda_order['b2B']['deliveryAddress']['firstName'] : $avarda_order['b2B']['customerInfo']['firstName'];
		$shipping_last_name  = isset( $avarda_order['b2B']['deliveryAddress']['lastName'] ) ? $avarda_order['b2B']['deliveryAddress']['lastName'] : $avarda_order['b2B']['customerInfo']['lastName'];

	}

	$shipping_data = array(
		'first_name' => $shipping_first_name,
		'last_name'  => $shipping_last_name,
		'country'    => isset( $delivery_address['country'] ) ? $delivery_address['country'] : $invoicing_address['country'],
		'address1'   => isset( $delivery_address['address1'] ) ? $delivery_address['address1'] : $invoicing_address['address1'],
		'address2'   => isset( $delivery_address['address2'] ) ? $delivery_address['address2'] : $invoicing_address['address2'],
		'city'       => isset( $delivery_address['city'] ) ? $delivery_address['city'] : $invoicing_address['city'],
		'zip'        => isset( $delivery_address['zip'] ) ? $delivery_address['zip'] : $invoicing_address['zip'],
	);

	// Set Avarda payment method title.
	aco_set_payment_method_title( $order, $avarda_order );

	// First name.
	$order->set_billing_first_name( sanitize_text_field( $billing_first_name ) );
	$order->set_shipping_first_name( sanitize_text_field( $shipping_data['first_name'] ) );
	// Last name.
	$order->set_billing_last_name( sanitize_text_field( $billing_last_name ) );
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

	// Company name.
	if ( ! empty( $billing_company ) ) {
		$order->set_billing_company( sanitize_text_field( $billing_company ) );
		$order->set_shipping_company( sanitize_text_field( $shipping_company ) );
	}

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

		$aco_payment_fee = isset( $avarda_order['paymentMethods']['selectedPayment']['paymentFee'] ) ? sanitize_text_field( $avarda_order['paymentMethods']['selectedPayment']['paymentFee'] ) : '';
		if ( ! empty( $aco_payment_fee ) ) {
			update_post_meta( $order->get_id(), '_avarda_payment_method_fee', $aco_payment_fee );
		}
	}

	switch ( $aco_payment_method ) {
		case 'Invoice':
			$method_title = __( 'Invoice', 'avarda-checkout-for-woocommerce' );
			break;
		case 'Loan':
			$method_title = __( 'Loan', 'avarda-checkout-for-woocommerce' );
			break;
		case 'Card':
			$method_title = __( 'Card', 'avarda-checkout-for-woocommerce' );
			break;
		case 'DirectPayment':
			$method_title = __( 'Direct Payment', 'avarda-checkout-for-woocommerce' );
			break;
		case 'PartPayment':
			$method_title = __( 'Part Payment', 'avarda-checkout-for-woocommerce' );
			break;
		case 'Swish':
			$method_title = __( 'Swish', 'avarda-checkout-for-woocommerce' );
			break;
		case 'HighAmountLoan':
			$method_title = __( 'Avarda High Amount Loan', 'avarda-checkout-for-woocommerce' );
			break;
		case 'PayPal':
			$method_title = __( 'PayPal', 'avarda-checkout-for-woocommerce' );
			break;
		case 'PayOnDelivery':
			$method_title = __( 'Pay On Delivery', 'avarda-checkout-for-woocommerce' );
			break;
		case 'B2BInvoice':
			$method_title = __( 'B2B Invoice', 'avarda-checkout-for-woocommerce' );
			break;
		case 'DirectInvoice':
			$method_title = __( 'Direct Invoice', 'avarda-checkout-for-woocommerce' );
			break;
		case 'Masterpass':
			$method_title = __( 'Masterpass', 'avarda-checkout-for-woocommerce' );
			break;
		case 'MobilePay':
			$method_title = __( 'MobilePay', 'avarda-checkout-for-woocommerce' );
			break;
		case 'Vipps':
			$method_title = __( 'Vipps', 'avarda-checkout-for-woocommerce' );
			break;
		case 'ZeroAmount':
			$method_title = __( 'Zero Amount', 'avarda-checkout-for-woocommerce' );
			break;
		default:
			$method_title = __( 'Avarda Checkout', 'avarda-checkout-for-woocommerce' );
	}

	// pattern substitution.
	$replacements               = array(
		'{PAYMENT_METHOD_TITLE}' => $method_title,
	);
	$method_title_from_settings = 'Avarda {PAYMENT_METHOD_TITLE}';
	$method_title_filtered      = str_replace( array_keys( $replacements ), $replacements, $method_title_from_settings );
	$method_title_filtered      = apply_filters( 'aco_order_set_payment_method_title', $method_title_filtered, $method_title, $order->get_id() );

	$order->set_payment_method_title( $method_title_filtered );
	$order->save();
}


/**
 * Unsets the sessions used by the plguin.
 *
 * @return void
 */
function aco_wc_unset_sessions() {
	WC()->session->__unset( 'aco_wc_payment_data' );
	WC()->session->__unset( 'aco_update_md5' );
	WC()->session->__unset( 'aco_language' );
	WC()->session->__unset( 'aco_currency' );
}

/**
 * Prints error message as notices.
 *
 * @param WP_Error $wp_error A WordPress error object.
 * @return void
 */
function aco_extract_error_message( $wp_error ) {
	$error_message = $wp_error->get_error_message();

	if ( is_array( $error_message ) ) {
		// Rather than assuming the first element is a string, we'll force a string conversion instead.
		$error_message = implode( ' ', $error_message );
	}

	if ( function_exists( 'wc_add_notice' ) ) {
		wc_add_notice( $error_message, 'error' );
	}
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
 * Adds the extra checkout field div to the checkout page.
 *
 * @return void
 */
function aco_wc_add_extra_checkout_fields() {
	do_action( 'aco_wc_before_extra_fields' );
	?>
	<div id="aco-extra-checkout-fields">
	</div>
	<?php
	do_action( 'aco_wc_after_extra_fields' );
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

	$orders = wc_get_orders($query_args);
	
	if ( $orders ) {
		$order_id = $orders[0];
	} else {
		$order_id = 0;
	}

	return $order_id;
}

/**
 * Finds an Order ID based on a purchase ID (the Avarda order number).
 *
 * @param string $purchase_id Avarda order number saved as Purchase ID in WC order.
 * @return int The ID of an order, or 0 if the order could not be found.
 */
function aco_get_order_id_by_purchase_id( $purchase_id ) {
	$query_args = array(
		'fields'      => 'ids',
		'post_type'   => wc_get_order_types(),
		'post_status' => array_keys( wc_get_order_statuses() ),
		'meta_key'    => '_wc_avarda_purchase_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'meta_value'  => sanitize_text_field( wp_unslash( $purchase_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'date_query'  => array(
			array(
				'after' => '30 day ago',
			),
		),
	);

	$orders = wc_get_orders($query_args);

	if ( $orders ) {
		$order_id = $orders[0];
	} else {
		$order_id = 0;
	}

	return $order_id;
}

/**
 * Returns the current Avarda purchaseId from WC->session.
 *
 * @return string The purchase id.
 */
function aco_get_purchase_id_from_session() {
	$avarda_payment_data = WC()->session->get( 'aco_wc_payment_data' );
	$avarda_purchase_id  = ( is_array( $avarda_payment_data ) && isset( $avarda_payment_data['purchaseId'] ) ) ? $avarda_payment_data['purchaseId'] : '';
	return $avarda_purchase_id;
}

/**
 * Returns the current Avarda JWT token from WC->session.
 *
 * @return string The purchase id.
 */
function aco_get_jwt_token_from_session() {
	$avarda_payment_data = WC()->session->get( 'aco_wc_payment_data' );
	$jwt                 = ( is_array( $avarda_payment_data ) && isset( $avarda_payment_data['jwt'] ) ) ? $avarda_payment_data['jwt'] : '';
	return $jwt;
}

/**
 * Returns the current Avarda payment state from Avarda order.
 *
 * @param array $avarda_payment Avarda payment session.
 * @return string The payment state.
 */
function aco_get_payment_step( $avarda_payment ) {
	$aco_step = '';
	if ( 'B2C' === $avarda_payment['mode'] ) {
		$aco_step = $avarda_payment['b2C']['step']['current'];
	} elseif ( 'B2B' === $avarda_payment['mode'] ) {
		$aco_step = $avarda_payment['b2B']['step']['current'];
	}
	return $aco_step;
}

/**
 * Returns approved Avarda payment steps where it is ok to send update requests.
 *
 * @return array Approved payment steps.
 */
function aco_payment_steps_approved_for_update_request() {
	return array(
		'EmailZipEntry',
		'AmountSelection',
		'PhoneNumberEntry',
		'PhoneNumberEntryForKnownCustomer',
		'Initialized',
		'PersonalInfo',
		'PersonalInfoWithoutSsn',
		'SsnEntry',
		'EnterCompanyInfo',
		'CompanyAddressInfo',
		'CompanyAddressInfoWithoutSsn',
	);
}
