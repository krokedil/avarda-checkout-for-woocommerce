<?php
/**
 * Token request class
 *
 * @package Avarda_Checkout/Classes/Post/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Token request class
 */
class ACO_Request_Token extends ACO_Request {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// Run parent constructor and set auth to true.
		parent::__construct( true );
	}
	/**
	 * Makes the request.
	 *
	 * @return array
	 */
	public function request() {
		$request_url  = $this->base_url . '/api/partner/tokens';
		$request_args = apply_filters( 'aco_create_token_args', $this->get_request_args() );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( '', 'POST', 'ACO create auth token', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
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
		$request_content = wp_json_encode(
			array(
				'clientId'     => htmlspecialchars_decode( $this->client_id ),
				'clientSecret' => htmlspecialchars_decode( $this->client_secret ),
			),
			JSON_UNESCAPED_SLASHES
		);
		return $request_content;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @return array
	 */
	public function get_request_args() {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'POST',
			'body'    => $this->get_body(),
		);
	}
}
