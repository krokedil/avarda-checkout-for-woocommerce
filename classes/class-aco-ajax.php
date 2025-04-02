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
			'aco_wc_get_avarda_payment'             => true,
			'aco_wc_iframe_shipping_address_change' => true,
			'aco_wc_change_payment_method'          => true,
			'aco_wc_log_js'                         => true,
			'aco_iframe_shipping_option_change'     => true,
			'aco_shipping_widget_get_options'       => true,
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
	}

	/**
	 * Shipping address change.
	 *
	 * @return void
	 */
	public static function aco_wc_iframe_shipping_address_change() {

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		$avarda_purchase_id = aco_get_purchase_id_from_session();

		// Check if we have a Avarda purchase id.
		if ( empty( $avarda_purchase_id ) ) {
			ACO_Logger::log( 'Missing purchase id in aco_wc_iframe_shipping_address_change function. Clearing Avarda session and reloading the checkout page.' );
			aco_wc_unset_sessions();
			wp_send_json_error( 'missing_purchase_id' );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'aco_wc_iframe_shipping_address_change' ) ) { // Input var okay.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$full_address = null;

		$customer_data = array();
		$update_needed = 'no';

		$zip     = isset( $_REQUEST['address']['zip'] ) ? sanitize_key( wp_unslash( $_REQUEST['address']['zip'] ) ) : '';
		$country = isset( $_REQUEST['address']['country'] ) ? strtoupper( sanitize_key( wp_unslash( $_REQUEST['address']['country'] ) ) ) : '';
		// Check if we have new country or zip.
		if ( WC()->customer->get_billing_country() !== $country || str_replace( ' ', '', WC()->customer->get_shipping_postcode() ) !== str_replace( ' ', '', $zip ) ) {
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

			// Calculating totals will trigger the update Avarda session sequence in ACO_Checkout class.
			WC()->cart->calculate_shipping();
			WC()->cart->calculate_totals();
		}

		if ( ACO_WC()->checkout->is_integrated_wc_shipping_enabled() ) {
			$full_address = self::process_integrated_wc_shipping();
		}

		wp_send_json_success(
			array(
				'update_needed'    => $update_needed,
				'customer_zip'     => $zip,
				'customer_country' => $country,
				'customer_data'    => $full_address,
			)
		);
	}

	/**
	 * Process integrated WooCommerce shipping.
	 *
	 * @return array
	 */
	private static function process_integrated_wc_shipping() {
		// Get the Avarda payment.
		$avarda_order = ACO_WC()->session()->get_avarda_payment();
		if ( is_wp_error( $avarda_order ) ) {
			wp_send_json_error(
				array(
					'error' => 'avarda_request_error',
				)
			);
		}

		$customer_address = aco_format_address_data( $avarda_order );

		// phpcs:disable
		$customer_data    = array(
			'billing_postcode'   => str_replace( ' ', '', $customer_address['billing']['zip'] ),
			'shipping_postcode'  => str_replace( ' ', '', $customer_address['shipping']['zip'] ?: $customer_address['billing']['zip'] ),
			'billing_country'    => $customer_address['billing']['country'],
			'shipping_country'   => $customer_address['shipping']['country'] ?: $customer_address['billing']['country'],
			'billing_city'       => $customer_address['billing']['city'],
			'shipping_city'      => $customer_address['shipping']['city'] ?: $customer_address['billing']['city'],
			'billing_address_1'  => $customer_address['billing']['address1'],
			'shipping_address_1' => $customer_address['shipping']['address1'] ?: $customer_address['billing']['address1'],
			'billing_address_2'  => $customer_address['billing']['address2'],
			'shipping_address_2' => $customer_address['shipping']['address2'] ?: $customer_address['billing']['address2'],
		);
		// phpcs:enable

		// Unset any empty values from the customer data.
		$customer_data = array_filter( $customer_data );

		WC()->customer->set_props( $customer_data );
		WC()->customer->apply_changes();
		WC()->customer->save();

		// Recalculate the shipping again.
		WC()->cart->calculate_shipping();

		return $customer_address;
	}

	/**
	 * Gets the Avarda payment from session.
	 *
	 * @return void
	 */
	public static function aco_wc_get_avarda_payment() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'aco_wc_get_avarda_payment' ) ) { // Input var okay.
			wp_send_json_error(
				array(
					'error' => 'bad_nonce',
				)
			);
			exit;
		}

		$avarda_order = ACO_WC()->api->request_get_payment( aco_get_purchase_id_from_session() );

		// Check if session TimedOut.
		if ( is_wp_error( $avarda_order ) ) {
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Failed to get the avarda order (in aco_wc_get_avarda_payment function). Clearing Avarda session and reloading the checkout page.' );
			wp_send_json_error(
				array(
					'error'    => 'wp_error',
					'redirect' => wc_get_checkout_url(),
				)
			);
		}

		// Get current status of Avarda session.
		$aco_step = aco_get_payment_step( $avarda_order );

		// Check if session TimedOut.
		if ( 'TimedOut' === $aco_step ) {
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Avarda session TimedOut (in aco_wc_get_avarda_payment function). Clearing Avarda session and reloading the checkout page.' );
			wp_send_json_error(
				array(
					'error'    => 'timeout',
					'redirect' => wc_get_checkout_url(),
				)
			);
		}

		if ( is_wp_error( $avarda_order ) ) {
			wp_send_json_error(
				array(
					'error' => 'avarda_request_error',
				)
			);
		}

		wp_send_json_success(
			array(
				'customer_data' => aco_format_address_data( $avarda_order ),
			)
		);
	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function aco_wc_log_js() {
		check_ajax_referer( 'aco_wc_log_js', 'nonce' );
		$avarda_purchase_id = aco_get_purchase_id_from_session();

		// Get the content size of the request.
		$post_size = (int) $_SERVER['CONTENT_LENGTH'] ?? 0;

		// If the post data is too long, log an error message and return.
		if ( $post_size > 1024 ) {
			ACO_Logger::log( "Frontend JS $avarda_purchase_id: message too long and can't be logged." );
			wp_send_json_success(); // Return success to not stop anything in the frontend if this happens.
		}

		$posted_message = filter_input( INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$message        = "Frontend JS $avarda_purchase_id: $posted_message";
		ACO_Logger::log( $message );
		wp_send_json_success();
	}

	/**
	 * Shipping option changed in Avarda Checkout.
	 *
	 * @return void
	 */
	public static function aco_iframe_shipping_option_change() {
		check_ajax_referer( 'aco_iframe_shipping_option_change', 'nonce' );

		if ( ! ACO_WC()->checkout->is_integrated_shipping_enabled() ) {
			wp_send_json_success( array( 'fragments' => array() ) );
		}

		self::process_integrated_partner_shipping();

		WC()->cart->calculate_totals();

		// Get the order review HTML.
		ob_start();
		woocommerce_order_review();
		$order_review = ob_get_clean();

		// Send it as a fragment in the json response.
		wp_send_json_success(
			array(
				'fragments' => array(
					'.woocommerce-checkout-review-order-table' => $order_review,
				),
			)
		);
	}

	/**
	 * Get the formatted shipping session from the shipping rates.
	 *
	 * @return void
	 */
	public static function aco_shipping_widget_get_options() {
		check_ajax_referer( 'aco_shipping_widget_get_options', 'nonce' );

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', array() );
		$shipping_package        = WC()->session->get( 'shipping_for_package_0', array() );

		$session = ACO_Shipping_Session_Model::from_shipping_rates( $shipping_package['rates'] ?? array(), $chosen_shipping_methods[0] ?? '', aco_get_purchase_id_from_session() );

		wp_send_json_success( $session );
	}

	/**
	 * Process integrated partner shipping.
	 *
	 * @return void
	 */
	private static function process_integrated_partner_shipping() {
		add_filter(
			'woocommerce_cart_shipping_packages',
			'aco_clear_shipping_package_hashes'
		);

		// Force shipping to be re-calculated.
		WC()->cart->calculate_shipping();

		remove_filter(
			'woocommerce_cart_shipping_packages',
			'aco_clear_shipping_package_hashes'
		);
	}
}
ACO_AJAX::init();
