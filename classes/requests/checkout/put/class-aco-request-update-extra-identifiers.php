<?php
/**
 * Update extra identifiers request class
 *
 * @package Avarda_Checkout/Classes/Put/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Update extra identifiers request class
 */
class ACO_Request_Update_Extra_Identifiers extends ACO_Request {
	/**
	 * Makes the request.
	 *
	 * @param string $aco_purchase_id Avarda purchase id.
	 * @return array
	 */
	public function request( $aco_purchase_id ) {
		$request_url  = $this->base_url . '/api/partner/payments/' . $aco_purchase_id . '/extraidentifiers';
		$request_args = apply_filters( 'aco_update_order_reference_args', $this->get_request_args() );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( $aco_purchase_id, 'PUT', 'ACO update order identifiers', $request_url, $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
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
			'attachments' => ACO_WC()->cart_items->get_cart_attachment(),
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
			'timeout' => apply_filters( 'aco_set_timeout', 10 ),
		);
	}
}
