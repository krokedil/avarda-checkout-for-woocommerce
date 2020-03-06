<?php
/**
 * API Class file.
 *
 * @package Avarda_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACO_API class.
 *
 * Class that has functions for the Avarda communication.
 */
class ACO_API {
	/**
	 * Main request.
	 *
	 * @return mixed
	 */
	public function request() {
		$request  = new ACO_Request();
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Creates an Avarda Checkout token.
	 *
	 * @return mixed
	 */
	public function request_token() {
		$request  = new ACO_Request_Token();
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Initialize an Avarda Checkout payment.
	 *
	 * @return mixed
	 */
	public function request_initialize_payment() {
		$request  = new ACO_Request_Initialize_Payment();
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Gets an Avarda Checkout payment.
	 *
	 * @return mixed
	 */
	public function request_get_payment() {
		$aco_purchase_id = WC()->session->get( 'aco_wc_purchase_id' );

		$request  = new ACO_Request_Get_Payment();
		$response = $request->request( $aco_purchase_id );

		return $this->check_for_api_error( $response );
	}

	/**
	 * Checks for WP Errors and returns either the response as array or a false.
	 *
	 * @param array $response The response from the request.
	 * @return mixed
	 */
	private function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			aco_extract_error_message( $response );
			return false;
		}
		return $response;
	}
}
