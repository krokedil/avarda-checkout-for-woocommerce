<?php
/**
 * Get invoices request class
 *
 * @package Avarda_Checkout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get invoices request class
 */
class ACO_Request_Get_Invoices extends ACO_Request {

	/**
	 * Makes the request.
	 *
	 * @param string $order_id WC order id.
	 * @return array
	 */
	public function request( $order_id ) {
		$order           = wc_get_order( $order_id );
		$aco_purchase_id = $order->get_transaction_id();

		$request_url  = $this->base_url . '/api/partner/invoices/' . $aco_purchase_id;
		$request_args = apply_filters( 'aco_get_invoices_args', $this->get_request_args() );

		$response = wp_remote_request( $request_url, $request_args );
		$code     = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( $aco_purchase_id, 'GET', 'ACO get MY Pages invoices', $request_url, $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
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
}

