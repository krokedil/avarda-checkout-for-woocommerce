<?php // phpcs:ignore
/**
 * Refund order request class
 *
 * Class for WooCommerce edit order.
 *
 * @package Avarda_Checkout/Classes/Post/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Refund order request class
 */
class ACO_Request_Refund_Order extends ACO_Request {

	/**
	 * Makes the request.
	 *
	 * @param int    $order_id WC order id.
	 * @param string $amount the refunded amount.
	 * @param string $reason the refund message.
	 * @return array
	 */
	public function request( $order_id, $amount, $reason ) {
		$order           = wc_get_order( $order_id );
		$aco_purchase_id = $order->get_transaction_id();

		$request_url  = $this->base_url . '/api/partner/payments/' . $aco_purchase_id . '/refund';
		$request_args = apply_filters( 'aco_refund_order_args', $this->get_request_args( $order_id, $amount, $reason ) );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = ( is_wp_error( $response ) ) ? $response->get_error_code() : wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log_body = ( is_wp_error( $response ) ) ? $response->get_error_messages() : json_decode( wp_remote_retrieve_body( $response ), true );
		$log      = ACO_Logger::format_log( $aco_purchase_id, 'POST', 'ACO refund order', $request_url, $request_args, $log_body, $code );
		ACO_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param int    $order_id WC order id.
	 * @param string $amount the refunded amount.
	 * @param string $reason the refund message.
	 * @return array
	 */
	public function get_body( $order_id, $amount, $reason ) {
		$order            = wc_get_order( $order_id );
		$order_number     = $order->get_order_number();
		$aco_total_amount = ! empty( $order->get_meta( '_avarda_payment_amount', true ) ) ? $order->get_meta( '_avarda_payment_amount', true ) : 0;

		return array(
			'orderReference' => $order_number,
			'amount'         => number_format( $amount, 2, '.', '' ),
			'note'           => $reason,
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int    $order_id WC order id.
	 * @param string $amount the refunded amount.
	 * @param string $reason the refund message.
	 * @return array
	 */
	public function get_request_args( $order_id, $amount, $reason ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'POST',
			'body'    => wp_json_encode( $this->get_body( $order_id, $amount, $reason ) ),
			'timeout' => apply_filters( 'aco_set_timeout', 10 ),
		);
	}
}
