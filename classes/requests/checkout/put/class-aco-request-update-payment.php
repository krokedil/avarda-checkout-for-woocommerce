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
	 * @param int    $order_id The WooCommerce order id.
	 * @param bool   $force If true always update the order, even if not needed.
	 * @return array|string Array if update is needed, otherwise string.
	 */
	public function request( $aco_purchase_id, $order_id = null, $force = false ) {
		$request_url  = $this->base_url . '/api/partner/payments/' . $aco_purchase_id . '/items';
		$request_args = apply_filters( 'aco_update_payment_args', $this->get_request_args( $order_id ) );
		// Check if we need to update.
		// @Todo - return false if no update is needed. Before we can do this change we need to change the return data in check_for_api_error() function.
		if ( WC()->session->get( 'aco_update_md5' ) && WC()->session->get( 'aco_update_md5' ) === md5( wp_json_encode( $request_args ) ) && ! $force ) {
			$log = ACO_Logger::format_log( $aco_purchase_id, 'PUT', 'ACO update payment', $request_url, $request_args, 'No update needed', '' );
			ACO_Logger::log( $log );

			return 'No update needed';
		}
		WC()->session->set( 'aco_update_md5', md5( wp_json_encode( $request_args ) ) );

		$response = wp_remote_request( $request_url, $request_args );
		$code     = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( $aco_purchase_id, 'PUT', 'ACO update payment', $request_url, $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		ACO_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function get_body( $order_id ) {

		$request_body = array();
		if ( $order_id ) {
			$request_body['items'] = ACO_WC()->order_items->get_order_items( $order_id );
		} else {
			$request_body['items'] = ACO_WC()->cart_items->get_cart_items();
		}

		return apply_filters( 'aco_update_args', $request_body, $order_id );
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function get_request_args( $order_id ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'PUT',
			'body'    => wp_json_encode( apply_filters( 'aco_wc_api_request_args', $this->get_body( $order_id ) ) ),
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
			$request_body = json_decode( $request_args['body'], true );
			ACO_WC()->session()->update_avarda_order_items( $request_body['items'] );
		}

		return $result;
	}
}
