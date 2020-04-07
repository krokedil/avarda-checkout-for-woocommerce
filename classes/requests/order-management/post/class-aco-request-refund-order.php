<?php
/**
 * Refund order request class
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
	 * @param int $order_id WC order id.
	 * @return array
	 */
	public function request( $order_id ) {
		$order           = wc_get_order( $order_id );
		$aco_purchase_id = $order->get_transaction_id();

		$request_url  = $this->base_url . '/api/partner/payments/' . $aco_purchase_id . '/refund';
		$request_args = apply_filters( 'aco_refund_order_args', $this->get_request_args( $order_id ) );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( '', 'POST', 'ACO refund order', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
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
		$order           = wc_get_order( $order_id );
		$order_number    = $order->get_order_number();
		$aco_purchase_id = $order->get_transaction_id();
		$order_refunds   = $order->get_refunds();

		foreach ( $order_refunds as $order_refund ) {
			if ( method_exists( $order_refund, 'get_reason' ) && $order_refund->get_reason() ) {
				$refund_reason = (string) $order_refund->get_reason();
			}
		}
		return array(
			'orderReference' => $order_number,
			'tranId'         => $aco_purchase_id,
			'note'           => isset( $refund_reason ) ? 'Reason: ' . $refund_reason : '',
			'amount'         => $order->get_total(),
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
		);
	}
}

