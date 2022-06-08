<?php // phpcs:ignore
/**
 * Initialize payment request class
 *
 * @package Avarda_Checkout/Classes/Post/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Initialize payment request class
 */
class ACO_Request_Initialize_Payment extends ACO_Request {

	/**
	 * Makes the request.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function request( $order_id = null ) {
		$request_url  = $this->base_url . '/api/partner/payments';
		$request_args = apply_filters( 'aco_initialize_payment_args', $this->get_request_args( $order_id ) );

		$response = wp_remote_request( $request_url, $request_args );
		$code     = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( '', 'POST', 'ACO initialize payment', $request_url, $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		ACO_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function get_body( $order_id ) {
		$order = wc_get_order( $order_id );

		$request_body = array(
			'checkoutSetup' => ACO_WC()->checkout_setup->get_checkout_setup(),
		);
		if ( $order_id ) {
			$request_body['items'] = ACO_WC()->order_items->get_order_items( $order_id );
			// Add customer address if it exist.
			if ( $order->get_billing_company() ) {
				$customer_address = ACO_WC()->customer->get_b2b_customer( $order_id );
				if ( ! empty( $customer_address ) ) {
					$request_body['b2B'] = $customer_address;
				}
			} else {
				$customer_address = ACO_WC()->customer->get_b2c_customer( $order_id );
				if ( ! empty( $customer_address ) ) {
					$request_body['b2C'] = $customer_address;
				}
			}
		} else {
			$request_body['items'] = ACO_WC()->cart_items->get_cart_items();
		}

		return apply_filters( 'aco_create_args', $request_body, $order_id );
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function get_request_args( $order_id ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'POST',
			'body'    => wp_json_encode( apply_filters( 'aco_wc_api_request_args', $this->get_body( $order_id ) ) ),
		);
	}
}

