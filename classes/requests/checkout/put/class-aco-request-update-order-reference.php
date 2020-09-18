<?php
/**
 * Update order reference request class
 *
 * @package Avarda_Checkout/Classes/Put/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update order reference request class
 */
class ACO_Request_Update_Order_Reference extends ACO_Request {

	/**
	 * Makes the request.
	 *
	 * @param string $aco_purchase_id Avarda purchase id.
	 * @param string $order_id WooCommerce order id.
	 * @return array
	 */
	public function request( $aco_purchase_id, $order_id ) {
		$request_url  = $this->base_url . '/api/partner/payments/' . $aco_purchase_id . '/extraidentifiers';
		$request_args = apply_filters( 'aco_update_order_reference_args', $this->get_request_args( $order_id ) );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( $aco_purchase_id, 'PUT', 'ACO update order reference', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		ACO_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param string $order_id WooCommerce order id.
	 * @return array
	 */
	public function get_body( $order_id ) {
		$order = wc_get_order( $order_id );
		return array(
			'orderReference' => (string) $order->get_order_number(),
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $order_id WooCommerce order id.
	 * @return array
	 */
	public function get_request_args( $order_id ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'PUT',
			'body'    => wp_json_encode( $this->get_body( $order_id ) ),
			'timeout' => apply_filters( 'aco_set_timeout', 10 ),
		);
	}
}

