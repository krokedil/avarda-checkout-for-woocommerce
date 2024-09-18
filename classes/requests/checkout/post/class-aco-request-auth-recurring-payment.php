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
	 * @param string $purchase_id Purchase id from Avarda.
	 * @param bool   $auth Is admin request.
	 */
	public function __construct( $order_id, $recurring_token, $purchase_id, $auth = false ) {
		parent::__construct( $auth );
		$this->order_id                = $order_id;
		$this->order                   = wc_get_order( $this->order_id );
		$this->recurring_payment_token = ! empty( $recurring_token ) ? $recurring_token : $this->order->get_meta( '_aco_recurring_token', true );
		$this->purchase_id             = ! empty( $purchase_id ) ? $purchase_id : $this->order->get_meta( '_wc_avarda_purchase_id', true );
	}

	/**
	 * Makes the request.
	 *
	 * @return array
	 */
	public function request() {

		$request_url  = $this->base_url . "/api/partner/payments/$this->purchase_id/authorizerecurringpayment";
		$request_args = apply_filters( 'aco_auth_recurring_payment_args', $this->get_request_args() );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = ( is_wp_error( $response ) ) ? $response->get_error_code() : wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log_body = ( is_wp_error( $response ) ) ? $response->get_error_messages() : json_decode( wp_remote_retrieve_body( $response ), true );
		$log      = ACO_Logger::format_log( '', 'POST', 'ACO authorize recurring payment', $request_url, $request_args, $log_body, $code );
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

		if ( ! $this->order ) {
			return array();
		}

		return array(
			'purchase_id'           => $this->purchase_id,
			'recurringPaymentToken' => $this->recurring_payment_token,
			'items'                 => ACO_WC()->order_items->get_order_items( $this->order_id ),
			'orderReference'        => $this->order->get_order_number(),
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
