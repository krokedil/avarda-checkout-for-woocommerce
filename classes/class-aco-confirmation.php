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
		$order_id           = filter_input( INPUT_GET, 'wc_order_id', FILTER_SANITIZE_NUMBER_INT );

		// Return if we don't have our parameters set.
		if ( empty( $aco_confirm ) || empty( $avarda_purchase_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		$order = empty( $order ) ? false : $order;

		if ( empty( $order ) ) {
			// Find relevant order in Woo.
			$order = aco_get_order_by_purchase_id( $avarda_purchase_id );
		}

		if ( empty( $order ) ) {
			$order_key = sanitize_text_field( $order_key );
			$order_id  = wc_get_order_id_by_order_key( $order_key );
			$order     = wc_get_order( $order_id );
		}

		// Verify the meta data after we get an order, to ensure that we don't process a wrong order.
		if ( $order->get_meta( '_wc_avarda_purchase_id' ) !== $avarda_purchase_id ) {
			$order = false;
		}

		if ( ! $order ) {
			// If no order is found, bail. @TODO Add a fallback order creation here?
			wc_add_notice( __( 'Something went wrong in the checkout process. Please contact the store.', 'avarda-checkout-for-woocommerce' ), 'error' );
			ACO_Logger::log( ': No WC order found in confirmation page. Avarda Purchase ID: ' . $avarda_purchase_id );
			return;
		}

		$order_id = $order->get_id();

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
