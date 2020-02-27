<?php
/**
 * Functions file for the plugin.
 *
 * @package Avarda_Checkout/Includes
 */

/**
 * Maybe creates, stores a token as a transient and returns.AMFReader
 *
 * @return string
 */
function aco_maybe_create_token() {
	$token = get_transient( 'aco_auth_token' );
	if ( false === $token ) {
		$request  = new ACO_Request_Token();
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
