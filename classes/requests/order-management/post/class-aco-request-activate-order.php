<?php
/**
 * Activate order request class
 *
 * @package Avarda_Checkout/Classes/Post/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Activate order request class
 */
class ACO_Request_Activate_Order extends ACO_Request {

	/**
	 * Makes the request.
	 *
	 * @param int $order_id WC order id.
	 * @return array
	 */
	public function request( $order_id ) {
		$order           = wc_get_order( $order_id );
		$aco_purchase_id = $order->get_transaction_id();

		$request_url  = $this->base_url . '/api/partner/payments/' . $aco_purchase_id . '/order';
		$request_args = apply_filters( 'aco_activate_order_args', $this->get_request_args( $order_id ) );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( $aco_purchase_id, 'POST', 'ACO activate order', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		ACO_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param int $order_id WC order id.
	 * @return array
	 */
	public function get_body( $order_id ) {
		$order        = wc_get_order( $order_id );
		$order_number = $order->get_order_number();
		return array(
			'items'          => ACO_WC()->order_items->get_order_items( $order_id ),
			'orderReference' => $order_number,
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int $order_id WC order id.
	 * @return array
	 */
	public function get_request_args( $order_id ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'POST',
			'body'    => wp_json_encode( $this->get_body( $order_id ) ),
			'timeout' => apply_filters( 'aco_set_timeout', 10 ),
		);
	}
}

