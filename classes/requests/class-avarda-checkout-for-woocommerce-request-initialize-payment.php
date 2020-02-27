<?php
/**
 * Initialize payment request class
 *
 * @package Avarda_Checkout/Classes/Put/Requests
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
	 * @return array
	 */
	public function request() {
		$request_url  = $this->base_url . '/api/partner/payments';
		$request_args = apply_filters( 'aco_initialize_payment_args', $this->get_request_args() );
		error_log( 'req arg ' . var_export( $request_args, true ) );

		$response = wp_remote_request( $request_url, $request_args );
		$code     = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( '', 'POST', 'ACO initialize payment', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		ACO_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @return array
	 */
	public function get_body() {
		return array(
			'language' => 'English', // TODO: Supported values: English/Swedish/Finnish/Norwegian/Estonian/Danish.
			'items'    => 'helper function generating items', // TODO.
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @return array
	 */
	public function get_request_args() {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'POST',
			'body'    => wp_json_encode( $this->get_body() ),
		);
	}
}

