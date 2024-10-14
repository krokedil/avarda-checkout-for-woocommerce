<?php
/**
 * Get payment request class
 *
 * @package Avarda_Checkout/Classes/Put/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get payment request class
 */
class ACO_Request_Get_Payment extends ACO_Request {

	/**
	 * Makes the request.
	 *
	 * @param string $aco_purchase_id Avarda purchase id.
	 * @return array
	 */
	public function request( $aco_purchase_id ) {
		$request_url  = $this->base_url . '/api/partner/payments/' . $aco_purchase_id;
		$request_args = apply_filters( 'aco_get_payment_args', $this->get_request_args() );

		$response = wp_remote_request( $request_url, $request_args );
		$code     = ( is_wp_error( $response ) ) ? $response->get_error_code() : wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log_body = ( is_wp_error( $response ) ) ? $response->get_error_messages() : json_decode( wp_remote_retrieve_body( $response ), true );
		$log      = ACO_Logger::format_log( $aco_purchase_id, 'GET', 'ACO get payment', $request_url, $request_args, $log_body, $code );
		ACO_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @return array
	 */
	public function get_request_args() {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'GET',
			'timeout' => apply_filters( 'aco_set_timeout', 10 ),
		);
	}

	/**
	 * Process the response.
	 *
	 * @param array  $response The response.
	 * @param array  $request_args The request args.
	 * @param string $request_url The request URL.
	 *
	 * @return array|string
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		$result = parent::process_response( $response, $request_args, $request_url );

		if ( ! is_wp_error( $result ) ) {
			ACO_WC()->session()->set_avarda_payment( $result );
		}

		return $result;
	}
}
