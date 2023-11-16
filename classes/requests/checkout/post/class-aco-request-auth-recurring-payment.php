<?php // phpcs:ignore
/**
 * Recurring Payment
 *
 * @package Avarda_Checkout/Classes/Post/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Authorize recurring payment class
 */
class ACO_Request_Auth_Recurring_Payment extends ACO_Request {

	/**
	 * WooCommerce order id.
	 *
	 * @var int $order_id
	 */
	protected $order_id;

	/**
	 * Recurring token from Avarda.
	 *
	 * @var string $recurring_payment_token
	 */
	protected $recurring_payment_token;

	/**
	 * Class constructor.
	 *
	 * @param int    $order_id WooCommerce order id.
	 * @param string $recurring_token Recurring token from Avarda.
	 * @param bool   $auth Is admin request.
	 */
	public function __construct( $order_id, $recurring_token, $auth = false ) {
		parent::__construct( $auth );
		$this->order_id                = $order_id;
		$this->recurring_payment_token = $recurring_token;
	}

	/**
	 * Makes the request.
	 *
	 * @return array
	 */
	public function request() {
		$order         = wc_get_order( $this->order_id );
		$purchase_id   = $order->get_meta( '_wc_avarda_purchase_id', true );
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
		foreach ( $subscriptions as $subscription ) {
			$purchase_id = $subscription->get_meta( '_wc_avarda_purchase_id', true );
		}
		$request_url  = $this->base_url . "/api/partner/payments/$purchase_id/authorizerecurringpayment";
		$request_args = apply_filters( 'aco_auth_recurring_payment_args', $this->get_request_args() );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = ACO_Logger::format_log( '', 'POST', 'ACO authorize recurring payment', $request_url, $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		ACO_Logger::log( $log );

		$formatted_response = $this->process_response( $response, $request_args, $request_url );
		return $formatted_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @return array
	 */
	public function get_body() {

		$order = wc_get_order( $this->order_id );

		if ( ! $order ) {
			return array();
		}

		return array(
			'purchase_id'           => $order->get_meta( '_wc_avarda_purchase_id', true ),
			'recurringPaymentToken' => $this->recurring_payment_token,
			'items'                 => ACO_WC()->order_items->get_order_items( $this->order_id ),
			'orderReference'        => $order->get_order_number(),
		);
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
			'body'    => wp_json_encode( $this->get_body() ),
			'timeout' => apply_filters( 'aco_set_timeout', 10 ),
		);
	}
}

