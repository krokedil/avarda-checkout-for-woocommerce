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
	 * @param string $aco_purchase_id Avarda purchase id.
	 * @param bool   $admin Whether request is made from admin or not.
	 * @return mixed
	 */
	public function request_get_payment( $aco_purchase_id, $admin = false ) {
		$request  = new ACO_Request_Get_Payment();
		$response = $request->request( $aco_purchase_id );

		// Check if the request was made from admin and not at checkout.
		if ( $admin ) {
			return $response;
		} else {
			return $this->check_for_api_error( $response );
		}
	}

	/**
	 * Updates an Avarda Checkout payment.
	 *
	 * @param string $aco_purchase_id Avarda purchase id.
	 * @return mixed
	 */
	public function request_update_payment( $aco_purchase_id ) {
		$request  = new ACO_Request_Update_Payment();
		$response = $request->request( $aco_purchase_id );
		return $this->check_for_api_error( $response );
	}

	/**
	 * Activates an Avarda Checkout order.
	 *
	 * @param int $order_id WC order id.
	 * @return mixed
	 */
	public function request_activate_order( $order_id ) {
		$request  = new ACO_Request_Activate_Order();
		$response = $request->request( $order_id );
		return $response;
	}

	/**
	 * Cancels an Avarda Checkout order.
	 *
	 * @param int $order_id WC order id.
	 * @return mixed
	 */
	public function request_cancel_order( $order_id ) {
		$request  = new ACO_Request_Cancel_Order();
		$response = $request->request( $order_id );
		return $response;
	}

	/**
	 * Refund an Avarda Checkout order.
	 *
	 * @param int $order_id WC order id.
	 * @return mixed
	 */
	public function request_refund_order( $order_id ) {
		$request  = new ACO_Request_Refund_Order();
		$response = $request->request( $order_id );
		return $response;
	}

	/**
	 * Return an Avarda Checkout order.
	 *
	 * @param int   $order_id WC order id.
	 * @param array $refunded_items The refunded items.
	 * @return mixed
	 */
	public function request_return_order( $order_id, $refunded_items ) {
		$request  = new ACO_Request_Return_Order();
		$response = $request->request( $order_id, $refunded_items );
		return $response;
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
