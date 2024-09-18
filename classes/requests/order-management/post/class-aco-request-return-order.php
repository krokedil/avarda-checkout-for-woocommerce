<?php // phpcs:ignore
/**
 * Return request class
 *
 * Class for WooCommerce whole and partial refunds.
 *
 * @package Avarda_Checkout/Classes/Post/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Return request class
 */
class ACO_Request_Return_Order extends ACO_Request {

	/**
	 * Makes the request.
	 *
	 * @param int   $order_id WC order id.
	 * @param array $refunded_items The refunded items.
	 * @return array
	 */
	public function request( $order_id, $refunded_items ) {
		$order           = wc_get_order( $order_id );
		$aco_purchase_id = $order->get_transaction_id();

		$request_url  = $this->base_url . '/api/partner/payments/' . $aco_purchase_id . '/return';
		$request_args = apply_filters( 'aco_return_args', $this->get_request_args( $order_id, $refunded_items ) );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = ( is_wp_error( $response ) ) ? $response->get_error_code() : wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log_body = ( is_wp_error( $response ) ) ? $response->get_error_messages() : json_decode( wp_remote_retrieve_body( $response ), true );
		$log      = ACO_Logger::format_log( $aco_purchase_id, 'POST', 'ACO Return', $request_url, $request_args, $log_body, $code );
		ACO_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param int   $order_id WC order id.
	 * @param array $refunded_items The refunded items.
	 * @return array
	 */
	public function get_body( $order_id, $refunded_items ) {
		$order        = wc_get_order( $order_id );
		$order_number = $order->get_order_number();

		return array(
			'items'          => $refunded_items,
			'orderReference' => $order_number,
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int   $order_id WC order id.
	 * @param array $refunded_items The refunded items.
	 * @return array
	 */
	public function get_request_args( $order_id, $refunded_items ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'POST',
			'body'    => wp_json_encode( $this->get_body( $order_id, $refunded_items ) ),
			'timeout' => apply_filters( 'aco_set_timeout', 10 ),
		);
	}
}
