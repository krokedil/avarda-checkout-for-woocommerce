<?php
/**
 * Handles callbacks for the plugin.
 *
 * @package Avarda_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Callback class.
 */
class ACO_Callbacks {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_aco_wc_notification', array( $this, 'notification_cb' ) );
	}

	/**
	 * Handles notification callbacks. Triggered by Avarda 1-10 minutes after finalized purchase.
	 *
	 * @return void
	 */
	public function notification_cb() {

		$post_body   = file_get_contents( 'php://input' );
		$data        = json_decode( $post_body, true );
		$purchase_id = sanitize_text_field( $data['purchaseId'] );
		$order_id    = aco_get_order_id_by_transaction_id( sanitize_text_field( $data['purchaseId'] ) );
		$order       = wc_get_order( $order_id );

		$aco_sub_payment_change = filter_input( INPUT_GET, 'aco-sub-payment-change', FILTER_SANITIZE_STRING );

		if ( ! empty( $aco_sub_payment_change ) ) {
			aco_confirm_subscription( $aco_sub_payment_change, $purchase_id );
			ACO_Logger::log( 'Notification callback hit for Avarda purchase ID: ' . $purchase_id . '. Subscription payment method update. WC Subscription ID: ' . $aco_sub_payment_change );
			header( 'HTTP/1.1 200 OK' );
			exit;
		}

		ACO_Logger::log( 'Notification callback hit for Avarda purchase ID: ' . $purchase_id . '. WC order ID: ' . $order_id );

		if ( ! is_object( $order ) ) {
			ACO_Logger::log( 'Aborting notification callback. No WooCommerce order found. Order id retreived from aco_get_order_id_by_transaction_id: ' . $order_id );
			header( 'HTTP/1.1 200 OK' );
			exit;
		}
		// Maybe abort the callback (if the order already has been processed in Woo).
		if ( ! empty( $order->get_date_paid() ) ) {
			ACO_Logger::log( 'Aborting notification callback. Order ' . $order->get_order_number() . '( order ID ' . $order_id . ') already processed.' );
		} else {
			aco_confirm_avarda_order( $order_id, sanitize_text_field( $data['purchaseId'] ) );
		}
		header( 'HTTP/1.1 200 OK' );
		exit;
	}
}
new ACO_Callbacks();
