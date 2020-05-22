<?php
/**
 * Update payment request class
 *
 * @package Avarda_Checkout/Classes/Put/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update payment request class
 */
class ACO_Request_Update_Payment extends ACO_Request {

	/**
	 * Makes the request.
	 *
	 * @param string $aco_purchase_id Avarda purchase id.
	 * @return array
	 */
	public function request( $aco_purchase_id ) {
		$request_url  = $this->base_url . '/api/partner/payments/' . $aco_purchase_id . '/items';
		$request_args = apply_filters( 'aco_update_payment_args', $this->get_request_args() );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( $aco_purchase_id, 'PUT', 'ACO update payment', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
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
			'items' => ACO_WC()->cart_items->get_cart_items(),
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
			'method'  => 'PUT',
			'body'    => wp_json_encode( $this->get_body() ),
		);
	}
}

