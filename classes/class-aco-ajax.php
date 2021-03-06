<?php // phpcs:ignore
/**
 * Ajax class file.
 *
 * @package Avarda_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Ajax class.
 */
class ACO_AJAX extends WC_AJAX {
	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'aco_wc_update_checkout'                => true,
			'aco_wc_get_avarda_payment'             => true,
			'aco_wc_iframe_shipping_address_change' => true,
			'aco_wc_change_payment_method'          => true,
			'aco_wc_log_js'                         => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Change payment method.
	 *
	 * @return void
	 */
	public static function aco_wc_change_payment_method() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'aco_wc_change_payment_method' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$aco_payment_method = isset( $_POST['aco'] ) ? sanitize_key( $_POST['aco'] ) : '';
		if ( 'false' === $aco_payment_method ) {
			// Set chosen payment method to first gateway that is not ours for WooCommerce.
			$first_gateway = reset( $available_gateways );
			if ( 'aco' !== $first_gateway->id ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway->id );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'aco' );
		}
		WC()->payment_gateways()->set_current_gateway( $available_gateways );
		$redirect = wc_get_checkout_url();
		$data     = array(
			'redirect' => $redirect,
		);
		wp_send_json_success( $data );
		wp_die();
	}

	/**
	 * Update checkout.
	 *
	 * @return void
	 */
	public static function aco_wc_update_checkout() {

		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'aco_wc_update_checkout' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		if ( 'aco' === WC()->session->get( 'chosen_payment_method' ) ) {

			$avarda_purchase_id = WC()->session->get( 'aco_wc_purchase_id' );

			// Set empty return array for errors.
			$return = array();

			// Check if we have a avarda purchase id.
			if ( empty( $avarda_purchase_id ) ) {
				wc_add_notice( 'Avarda purchase id is missing.', 'error' );
				wp_send_json_error();
				wp_die();
			} else {
				// Get the Avarda order from Avarda.
				$avarda_order = ACO_WC()->api->request_get_payment( $avarda_purchase_id );
				// Check if we got a wp_error.
				if ( ! $avarda_order ) {
					// Unset sessions.
					aco_wc_unset_sessions();

					wp_send_json_error();
					wp_die();
				}

				// Get the Avarda order object.
				// Calculate cart totals.
				WC()->cart->calculate_fees();
				WC()->cart->calculate_totals();

				// Check if order needs payment. If not, send refreshZeroAmount so checkout page is reloaded.
				if ( apply_filters( 'aco_check_if_needs_payment', true ) ) {
					if ( ! WC()->cart->needs_payment() ) {
						wp_send_json_success(
							array(
								'refreshZeroAmount' => 'refreshZeroAmount',
							)
						);
						wp_die();
					}
				}
				// Get current status of Avarda session.
				if ( 'B2C' === $avarda_order['mode'] ) {
					$aco_state = $avarda_order['b2C']['step']['current'];
				} elseif ( 'B2B' === $avarda_order['mode'] ) {
					$aco_state = $avarda_order['b2B']['step']['current'];
				}

				if ( ! ( 'Completed' === $aco_state || 'TimedOut' === $aco_state ) ) {
					// Update order.
					$avarda_order = ACO_WC()->api->request_update_payment( $avarda_purchase_id );

					// If the update failed - unset sessions and return error.
					if ( false === $avarda_order ) {
						// Unset sessions.
						aco_wc_unset_sessions();

						wp_send_json_error();
						wp_die();
					}
				}
			}
		}
		// Everything is okay if we get here. Send empty success and kill wp.
		wp_send_json_success();
		wp_die();

	}

	/**
	 * Shipping address change.
	 *
	 * @return void
	 */
	public static function aco_wc_iframe_shipping_address_change() {

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		$avarda_purchase_id = WC()->session->get( 'aco_wc_purchase_id' );

		// Check if we have a Avarda purchase id.
		if ( empty( $avarda_purchase_id ) ) {
			wc_add_notice( 'Avarda purchase id is missing.', 'error' );
			wp_send_json_error();
			wp_die();
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'aco_wc_iframe_shipping_address_change' ) ) { // Input var okay.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$customer_data = array();
		$update_needed = 'no';

		$zip     = isset( $_REQUEST['address']['zip'] ) ? sanitize_key( wp_unslash( $_REQUEST['address']['zip'] ) ) : '';
		$country = isset( $_REQUEST['address']['country'] ) ? strtoupper( sanitize_key( wp_unslash( $_REQUEST['address']['country'] ) ) ) : '';

		// Check if we have new country or zip.
		if ( WC()->customer->get_billing_country() !== $country || WC()->customer->get_shipping_postcode() !== $zip ) {
			$update_needed = 'yes';

			if ( ! empty( $zip ) ) {
				$customer_data['billing_postcode']  = $zip;
				$customer_data['shipping_postcode'] = $zip;
			}

			if ( ! empty( $country ) ) {
				$customer_data['billing_country']  = $country;
				$customer_data['shipping_country'] = $country;
			}

			WC()->customer->set_props( $customer_data );
			WC()->customer->save();

			WC()->cart->calculate_shipping();
			WC()->cart->calculate_totals();

			$avarda_order = ACO_WC()->api->request_update_payment( $avarda_purchase_id );

			if ( false === $avarda_order ) {
				wp_send_json_error();
				wp_die();
			}
		}

		wp_send_json_success(
			array(
				'update_needed'    => $update_needed,
				'customer_zip'     => $zip,
				'customer_country' => $country,
			)
		);
		wp_die();
	}

	/**
	 * Gets the Avarda payment from session.
	 *
	 * @return void
	 */
	public static function aco_wc_get_avarda_payment() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'aco_wc_get_avarda_payment' ) ) { // Input var okay.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$avarda_payment = ACO_WC()->api->request_get_payment( WC()->session->get( 'aco_wc_purchase_id' ) );
		if ( ! $avarda_payment ) {
			wp_send_json_error( $avarda_payment );
			wp_die();
		}
		wp_send_json_success(
			array(
				'customer_data' => $avarda_payment,
			)
		);
		wp_die();

	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function aco_wc_log_js() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'aco_wc_log_js' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$posted_message     = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$avarda_purchase_id = WC()->session->get( 'aco_wc_purchase_id' );
		$message            = "Frontend JS $avarda_purchase_id: $posted_message";
		ACO_Logger::log( $message );
		wp_send_json_success();
		wp_die();
	}
}
ACO_AJAX::init();
