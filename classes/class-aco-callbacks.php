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
		add_action( 'aco_payment_created_callback', array( $this, 'execute_aco_payment_created_callback' ), 10, 2 );
	}

	/**
	 * Handles notification callbacks. Triggered by Avarda 0-10 minutes after finalized purchase.
	 *
	 * @return void
	 */
	public function notification_cb() {

		$post_body   = file_get_contents( 'php://input' );
		$data        = json_decode( $post_body, true );
		$purchase_id = sanitize_text_field( $data['purchaseId'] );
		$order_id    = aco_get_order_id_by_purchase_id( $purchase_id );
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
			ACO_Logger::log( 'Aborting notification callback for Purchase ID ' . $purchase_id . '. No WooCommerce order found. Order id retreived from aco_get_order_id_by_purchase_id: ' . $order_id );
			header( 'HTTP/1.1 200 OK' );
			exit;
		}
		// Maybe abort the callback (if the order already has been processed in Woo).
		if ( ! empty( $order->get_date_paid() ) ) {
			ACO_Logger::log( 'Aborting notification callback. Order ' . $order->get_order_number() . ' (order ID ' . $order_id . ') already processed.' );
		} else {
			as_schedule_single_action( time() + 120, 'aco_payment_created_callback', array( $purchase_id, $order_id ) );
			ACO_Logger::log( 'Scheduling notification callback to be handled in 2 minutes for Purchase ID ' . $purchase_id . '. Order ' . $order->get_order_number() . ' (order ID ' . $order_id . ').' );
		}
		header( 'HTTP/1.1 200 OK' );
		exit;
	}

	/**
	 * Handle execution of payment created cronjob.
	 *
	 * @param string $purchase_id Avarda purchase id.
	 * @param string $order_id WC order ID.
	 */
	public function execute_aco_payment_created_callback( $purchase_id, $order_id ) {

		ACO_Logger::log( 'Execute purchase completed API callback. Purchase ID:' . $purchase_id . '. Order ID: ' . $order_id );

		$order = wc_get_order( $order_id );

		// Maybe abort the callback (if the order already has been processed in Woo).
		if ( ! empty( $order->get_date_paid() ) ) {
			ACO_Logger::log( 'Aborting purchase completed API callback. Order ' . $order->get_order_number() . '(order ID ' . $order_id . ') already processed.' );
		} else {
			ACO_Logger::log( 'Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to Processing/Completed in API callback.' );
			// translators: Avarda purchase ID.
			$note = sprintf( __( 'Order status not set correctly during checkout process. Confirming purchase via callback from Avarda.', 'avarda-checkout-for-woocommerce' ), $purchase_id );
			$order->add_order_note( $note );
			aco_confirm_avarda_order( $order_id, $purchase_id );
		}
	}
}
new ACO_Callbacks();
