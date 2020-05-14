<?php
/**
 * Order management class file.
 *
 * @package Avarda_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Order management class.
 */
class ACO_Order_Management {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_reservation' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'activate_reservation' ) );
		// Update an order.
		// add_action( 'woocommerce_saved_order_items', array( $this, 'update_order' ), 10, 2 ); For aco refund.
	}

	/**
	 * Cancels the order with the payment provider.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return void
	 */
	public function cancel_reservation( $order_id ) {
		$order = wc_get_order( $order_id );
		// If this order wasn't created using aco payment method, bail.
		if ( 'aco' != $order->get_payment_method() ) {
			return;
		}

		// Check Avarda settings to see if we have the ordermanagement enabled.
		$avarda_settings  = get_option( 'woocommerce_aco_settings' );
		$order_management = 'yes' === $avarda_settings['order_management'] ? true : false;
		if ( ! $order_management ) {
			return;
		}

		$subscription = $this->check_if_subscription( $order );

		// Check if we have a purchase id.
		$purchase_id = get_post_meta( $order_id, '_wc_avarda_purchase_id', true );
		if ( empty( $purchase_id ) ) {
			$order->add_order_note( __( 'Avarda Checkout reservation could not be cancelled. Missing Avarda purchase id.', 'avarda-checkout-for-woocommerce' ) );
			$order->set_status( 'on-hold' );
			return;
		}

		// If this reservation was already cancelled, do nothing.
		if ( get_post_meta( $order_id, '_avarda_reservation_cancelled', true ) ) {
			$order->add_order_note( __( 'Could not cancel Avarda Checkout reservation, Avarda Checkout reservation is already cancelled.', 'avarda-checkout-for-woocommerce' ) );
			return;
		}

		// TODO: Should we do different request if order is subcription?
		// Cancel order.
		$avarda_order = ( $subscription ) ? ACO_WC()->api->request_cancel_order( $order_id ) : ACO_WC()->api->request_cancel_order( $order_id );

		// Check if we were successful.
		if ( is_wp_error( $avarda_order ) ) {
			// If error save error message.
			$code          = $avarda_order->get_error_code();
			$message       = $avarda_order->get_error_message();
			$text          = __( 'Avarda API Error on Avarda cancel order: ', 'avarda-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			$order->set_status( 'on-hold' );
		} else {
			// Add time stamp, used to prevent duplicate activations for the same order.
			update_post_meta( $order_id, '_avarda_reservation_cancelled', current_time( 'mysql' ) );
			$order->add_order_note( __( 'Avarda reservation was successfully cancelled.', 'avarda-checkout-for-woocommerce' ) );
		}
	}

	/**
	 * Activate the order with the payment provider.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return void
	 */
	public function activate_reservation( $order_id ) {
		$order = wc_get_order( $order_id );
		// If this order wasn't created using aco payment method, bail.
		if ( 'aco' != $order->get_payment_method() ) {
			return;
		}

		// Check Avarda settings to see if we have the ordermanagement enabled.
		$avarda_settings  = get_option( 'woocommerce_aco_settings' );
		$order_management = 'yes' === $avarda_settings['order_management'] ? true : false;
		if ( ! $order_management ) {
			return;
		}

		$subscription = $this->check_if_subscription( $order );
		// If this is a free subscription then stop here.
		if ( $subscription && 0 >= $order->get_total() ) {
			return;
		}

		// Check if we have a purchase id.
		$purchase_id = get_post_meta( $order_id, '_wc_avarda_purchase_id', true );
		if ( empty( $purchase_id ) ) {
			$order->add_order_note( __( 'Avarda Checkout reservation could not be activated. Missing Avarda purchase id.', 'avarda-checkout-for-woocommerce' ) );
			$order->set_status( 'on-hold' );
			return;
		}

		// If this reservation was already activated, do nothing.
		if ( get_post_meta( $order_id, '_avarda_reservation_activated', true ) ) {
			$order->add_order_note( __( 'Could not activate Avarda Checkout reservation, Avarda Checkout reservation is already activated.', 'avarda-checkout-for-woocommerce' ) );
			$order->set_status( 'on-hold' );
			return;
		}

		// TODO: Should we do different request if order is subcription?
		// Activate order.
		$avarda_order = ( $subscription ) ? ACO_WC()->api->request_activate_order( $order_id ) : ACO_WC()->api->request_activate_order( $order_id );

		// Check if we were successful.
		if ( is_wp_error( $avarda_order ) ) {
			// If error save error message.
			$code          = $avarda_order->get_error_code();
			$message       = $avarda_order->get_error_message();
			$text          = __( 'Avarda API Error on Avarda activate order: ', 'avarda-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			$order->set_status( 'on-hold' );
		} else {
			// Add time stamp, used to prevent duplicate activations for the same order.
			update_post_meta( $order_id, '_avarda_reservation_activated', current_time( 'mysql' ) );
			$order->add_order_note( __( 'Avarda reservation was successfully activated.', 'avarda-checkout-for-woocommerce' ) );
		}

	}

	/**
	 * WooCommerce Refund.
	 *
	 * @param string $order_id The WooCommerce order ID.
	 * @param float  $amount The amount to be refunded.
	 * @param string $reason The reason given for the refund.
	 * @return boolean
	 */
	public function refund_payment( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		// If this order wasn't created using aco payment method, bail.
		if ( 'aco' != $order->get_payment_method() ) {
			return;
		}

		// Check Avarda settings to see if we have the ordermanagement enabled.
		$avarda_settings  = get_option( 'woocommerce_aco_settings' );
		$order_management = 'yes' === $avarda_settings['order_management'] ? true : false;
		if ( ! $order_management ) {
			return;
		}

		// Check if we have a purchase id.
		$purchase_id = get_post_meta( $order_id, '_wc_avarda_purchase_id', true );
		if ( empty( $purchase_id ) ) {
			$order->add_order_note( __( 'Avarda Checkout order could not be refunded. Missing Avarda purchase id.', 'avarda-checkout-for-woocommerce' ) );
			$order->set_status( 'on-hold' );
			return;
		}

		$subscription = $this->check_if_subscription( $order );

		// Get the Avarda order.
		// TODO: Should we do different request if order is subcription?
		$avarda_order_tmp = ( $subscription ) ? ACO_WC()->api->request_get_payment( $purchase_id, true ) : ACO_WC()->api->request_get_payment( $purchase_id, true );
		if ( is_wp_error( $avarda_order_tmp ) ) {
			// If error save error message.
			$code          = $avarda_order_tmp->get_error_code();
			$message       = $avarda_order_tmp->get_error_message();
			$text          = __( 'Avarda API Error on get avarda order before refund: ', 'avarda-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			return false;
		}

		if ( 'Completed' === $avarda_order_tmp['state'] ) {
			$refund_order_id = ACO_Helper_Create_Refund_Data::get_refunded_order( $order_id );
			$refunded_items  = ACO_Helper_Create_Refund_Data::create_refund_data( $order_id, $refund_order_id, $amount, $reason );
			$avarda_order    = ACO_WC()->api->request_return_order( $order_id, $refunded_items );
			if ( is_wp_error( $avarda_order ) ) {
				// If error save error message and return false.
				$code          = $avarda_order->get_error_code();
				$message       = $avarda_order->get_error_message();
				$text          = __( 'Avarda API Error on Avarda refund: ', 'avarda-checkout-for-woocommerce' ) . '%s %s';
				$formated_text = sprintf( $text, $code, $message );
				$order->add_order_note( $formated_text );
				return false;
			}
			$order->add_order_note( __( 'Avarda Checkout order was successfully refunded.', 'avarda-checkout-for-woocommerce' ) );
			return true;
		}
		$order->add_order_note( __( 'Avarda Checkout order could not be refunded.', 'avarda-checkout-for-woocommerce' ) );
		return false;

	}

	/**
	 * Update order.
	 *
	 * @param int     $order_id Order id.
	 * @param array   $items Items.
	 * @param boolean $action Action.
	 * @return void
	 */
	public function update_order( $order_id, $items, $action = false ) {
		$order = wc_get_order( $order_id );

		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		// If this order wasn't created using Avarda Checkout payment method, bail.
		if ( 'aco' != $order->get_payment_method() ) {
			return;
		}

		// Changes only possible if order is set to On Hold.
		if ( 'on-hold' !== $order->get_status() ) {
			return;
		}

		// TODO: include swish and direct payments.
		if ( 'Card' !== get_post_meta( $order_id, 'avarda_payment_method', true ) ) {
			return;
		}

		// Check if we have a purchase id.
		$purchase_id = get_post_meta( $order_id, '_wc_avarda_purchase_id', true );
		if ( empty( $purchase_id ) ) {
			$order->add_order_note( __( 'Avarda Checkout order could not be updated. Missing Avarda purchase id.', 'avarda-checkout-for-woocommerce' ) );
			return;
		}

		$subscription = $this->check_if_subscription( $order );

		// TODO: Should we do different request if order is subcription?
		$avarda_order_tmp = ( $subscription ) ? ACO_WC()->api->request_get_payment( $purchase_id, true ) : ACO_WC()->api->request_get_payment( $purchase_id, true );
		if ( is_wp_error( $avarda_order_tmp ) ) {
			// If error save error message.
			$code          = $avarda_order_tmp->get_error_code();
			$message       = $avarda_order_tmp->get_error_message();
			$text          = __( 'Avarda API Error on get avarda order before update: ', 'avarda-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			return;
		}

		if ( 'Completed' === $avarda_order_tmp['state'] ) {
			$avarda_order = ACO_WC()->api->request_refund_order( $order_id );
			if ( is_wp_error( $avarda_order ) ) {
				// If error save error message and return false.
				$code          = $avarda_order->get_error_code();
				$message       = $avarda_order->get_error_message();
				$text          = __( 'Avarda API Error on Avarda update: ', 'avarda-checkout-for-woocommerce' ) . '%s %s';
				$formated_text = sprintf( $text, $code, $message );
				$order->add_order_note( $formated_text );
				return;
			}
			$order->add_order_note( __( 'Avarda Checkout order was successfully updated.', 'avarda-checkout-for-woocommerce' ) );
			update_post_meta( $order_id, '_avarda_payment_amount', $order->get_total() );
			return;
		}
		$order->add_order_note( __( 'Avarda Checkout order could not be updated.', 'avarda-checkout-for-woocommerce' ) );
	}


	/**
	 * Checks if the order is a subscription order or not
	 *
	 * @param object $order WC_Order object.
	 * @return boolean
	 */
	public function check_if_subscription( $order ) {
		if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_renewal( $order ) ) {
			return true;
		}
		if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order ) ) {
			return true;
		}
		return false;
	}
}
