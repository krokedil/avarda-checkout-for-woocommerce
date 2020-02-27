<?php
/**
 * Functions file for the plugin.
 *
 * @package Avarda_Checkout/Includes
 */

/**
 * Maybe creates, stores a token as a transient and returns.AMFReader
 *
 * @param int $order_id WooCommerce order id.
 * @return string
 */
function aco_maybe_create_token( $order_id ) {
	$token = get_transient( 'aco_auth_token' );
	if ( false === $token ) {
		$request  = new ACO_Request_Token( $order_id );
		$response = $request->request();
		if ( is_wp_error( $response ) || ! isset( $response['token'] ) ) {
			return $response;
		}
		// Set transient with 55minute life time.
		set_transient( 'aco_auth_token', $response['token'], 55 * MINUTE_IN_SECONDS );
		$token = $response['token'];
	}
	return $token;
}
