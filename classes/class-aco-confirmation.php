<?php
/**
 * Confirmation class.
 *
 * @package Avarda_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Callback class.
 */
class ACO_Confirmation {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'confirm_order' ) );
	}

	/**
	 * Redirects the customer to the proper thank you page.
	 *
	 * @return void
	 */
	public function confirm_order() {
		$aco_confirm        = filter_input( INPUT_GET, 'aco_confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$avarda_purchase_id = filter_input( INPUT_GET, 'aco_purchase_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_key          = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// Return if we dont have our parameters set.
		if ( empty( $aco_confirm ) || empty( $avarda_purchase_id ) ) {
			return;
		}

		// Find relevant order in Woo.
		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_wc_avarda_purchase_id',
			'meta_value'  => $avarda_purchase_id,
			'date_query'  => array(
				array(
					'after' => '5 day ago',
				),
			),
		);

		$orders = wc_get_orders( $query_args );
		if ( ! $orders ) {
			// If no order is found, bail. @TODO Add a fallback order creation here?
			wc_add_notice( __( 'Something went wrong in the checkout process. Please contact the store.', 'error' ) );
			ACO_Logger::log( ': No WC order found in confirmation page. Avarda Purchase ID: ' . $avarda_purchase_id );
			return;
		}
		$order = $orders[0];

		// Confirm the order.
		ACO_Logger::log( $avarda_purchase_id . ': Confirm the Avarda order from the confirmation page. Order ID: ' . $order_id );
		aco_confirm_avarda_order( $order_id, $avarda_purchase_id );

		// Unset sessions.
		aco_wc_unset_sessions();

		// Redirect and exit.
        wp_redirect( $order->get_checkout_order_received_url() ); // phpcs:ignore
		exit;
	}
}
ACO_Confirmation::get_instance();
