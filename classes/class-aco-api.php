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
	 * @param int $order_id The WooCommerce Order id.
	 *
	 * @return mixed
	 */
	public function request_initialize_payment( $order_id = false ) {
		$request  = new ACO_Request_Initialize_Payment();
		$response = $request->request( $order_id );

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
	 * @param int    $order_id The WooCommerce order id.
	 * @param bool   $force If true always update the order, even if not needed.
	 * @return mixed
	 */
	public function request_update_payment( $aco_purchase_id, $order_id = null, $force = false ) {
		$request  = new ACO_Request_Update_Payment();
		$response = $request->request( $aco_purchase_id, $order_id, $force );
		return $this->check_for_api_error( $response );
	}

	/**
	 * Updates order reference.
	 *
	 * @param string $aco_purchase_id Avarda purchase id.
	 * @param string $order_id WooCommerce order id.
	 * @return mixed
	 */
	public function request_update_order_reference( $aco_purchase_id, $order_id ) {
		$request  = new ACO_Request_Update_Order_Reference();
		$response = $request->request( $aco_purchase_id, $order_id );
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
	 *
	 * Creates recurring order.
	 *
	 * @param int    $order_id WC order id.
	 * @param string $recurring_token  Avarda recurring token.
	 *
	 * @return array
	 */
	public function create_recurring_order( $order_id, $recurring_token ) {
		return ( new ACO_Request_Auth_Recurring_Payment( $order_id, $recurring_token, false ) )->request();

	}

	/**
	 * Checks for WP Errors and returns either the response as array or a false.
	 *
	 * @param array $response The response from the request.
	 * @return mixed
	 */
	private function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) && ! is_admin() ) {
			aco_extract_error_message( $response );
		}
		return $response;
	}
}
