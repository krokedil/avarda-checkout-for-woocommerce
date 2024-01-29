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

		// Order actions - manually trigger activate & cancel order requests.
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_actions' ), 10, 2 );
		add_action( 'woocommerce_order_action_aco_cancel_order', array( $this, 'cancel_reservation' ) );
		add_action( 'woocommerce_order_action_aco_activate_order', array( $this, 'activate_reservation' ) );
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
		if ( 'aco' !== $order->get_payment_method() ) {
			return;
		}

		// Check Avarda settings to see if we have the order management enabled.
		$avarda_settings  = get_option( 'woocommerce_aco_settings' );
		$order_management = 'yes' === $avarda_settings['order_management'] ? true : false;
		if ( ! $order_management ) {
			return;
		}

		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		$subscription = $this->check_if_subscription( $order );

		// Check if we have a purchase id.
		$purchase_id = $order->get_meta( '_wc_avarda_purchase_id', true ) ?? $order->get_transaction_id() ?? '';
		if ( empty( $purchase_id ) ) {
			$note = __( 'Avarda Checkout reservation could not be cancelled. Missing Avarda purchase id.', 'avarda-checkout-for-woocommerce' );
			$order->update_status( 'on-hold', $note );
			do_action( 'aco_om_failed', 'cancel', $note, $order );
			return;
		}

		// If this reservation was already cancelled, do nothing.
		if ( $order->get_meta( '_avarda_reservation_cancelled', true ) ) {
			$order->add_order_note( __( 'Could not cancel Avarda Checkout reservation, Avarda Checkout reservation is already cancelled.', 'avarda-checkout-for-woocommerce' ) );
			return;
		}

		// TODO: Should we do different request if order is subscription?
		// Cancel order.
		$avarda_order = ( $subscription ) ? ACO_WC()->api->request_cancel_order( $order_id ) : ACO_WC()->api->request_cancel_order( $order_id );

		// Check if we were successful.
		if ( is_wp_error( $avarda_order ) ) {
			// If error save error message.
			$code    = $avarda_order->get_error_code();
			$message = $avarda_order->get_error_message();
			$text    = __( 'Avarda API Error on Avarda cancel order: ', 'avarda-checkout-for-woocommerce' ) . '%s %s';
			$note    = sprintf( $text, $code, $message );
			$order->update_status( 'on-hold', $note );
			do_action( 'aco_om_failed', 'cancel', $note, $order );
		} else {
			// Add time stamp, used to prevent duplicate activations for the same order.
			$order->update_meta_data( '_avarda_reservation_cancelled', current_time( 'mysql' ) );
			$order->save();
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
		if ( 'aco' !== $order->get_payment_method() ) {
			return;
		}

		// Check Avarda settings to see if we have the ordermanagement enabled.
		$avarda_settings  = get_option( 'woocommerce_aco_settings' );
		$order_management = 'yes' === $avarda_settings['order_management'] ? true : false;
		if ( ! $order_management ) {
			return;
		}

		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		$subscription = $this->check_if_subscription( $order );
		// If this is a free subscription then stop here.
		if ( $subscription && 0 >= $order->get_total() ) {
			return;
		}

		// Check if we have a purchase id.
		$purchase_id = $order->get_meta( '_wc_avarda_purchase_id', true ) ?? $order->get_transaction_id() ?? '';
		if ( empty( $purchase_id ) ) {
			$note = __( 'Avarda Checkout reservation could not be activated. Missing Avarda purchase id.', 'avarda-checkout-for-woocommerce' );
			$order->update_status( 'on-hold', $note );
			do_action( 'aco_om_failed', 'activate', $note, $order );
			return;
		}

		// If this reservation was already activated, do nothing.
		if ( $order->get_meta( '_avarda_reservation_activated', true ) ) {
			$order->add_order_note( __( 'Could not activate Avarda Checkout reservation, Avarda Checkout reservation is already activated.', 'avarda-checkout-for-woocommerce' ) );
			return;
		}

		// TODO: Should we do different request if order is subscription?
		// Activate order.
		$avarda_order = ( $subscription ) ? ACO_WC()->api->request_activate_order( $order_id ) : ACO_WC()->api->request_activate_order( $order_id );

		// Check if we were successful.
		if ( is_wp_error( $avarda_order ) ) {
			// If error save error message.
			$code    = $avarda_order->get_error_code();
			$message = $avarda_order->get_error_message();
			$text    = __( 'Avarda API Error on Avarda activate order: ', 'avarda-checkout-for-woocommerce' ) . '%s %s';
			$note    = sprintf( $text, $code, $message );
			$order->update_status( 'on-hold', $note );
			do_action( 'aco_om_failed', 'activate', $note, $order );
		} else {
			// Add time stamp, used to prevent duplicate activations for the same order.
			$order->update_meta_data( '_avarda_reservation_activated', current_time( 'mysql' ) );
			$order->save();
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
		if ( 'aco' !== $order->get_payment_method() ) {
			return;
		}

		// Check Avarda settings to see if we have the ordermanagement enabled.
		$avarda_settings  = get_option( 'woocommerce_aco_settings' );
		$order_management = 'yes' === $avarda_settings['order_management'] ? true : false;
		if ( ! $order_management ) {
			return;
		}

		// Check if we have a purchase id.
		$purchase_id = $order->get_meta( '_wc_avarda_purchase_id', true );
		if ( empty( $purchase_id ) ) {
			$order->add_order_note( __( 'Avarda Checkout order could not be refunded. Missing Avarda purchase id.', 'avarda-checkout-for-woocommerce' ) );
			return false;
		}

		// If activation (Delivery) has not yet been done, use Avardas refund endpoint.
		// Refund in this case means to release funds from the current reservation.
		if ( empty( $order->get_meta( '_avarda_reservation_activated', true ) ) ) {
			$avarda_order = ACO_WC()->api->request_refund_order( $order_id, $amount, $reason );
			if ( is_wp_error( $avarda_order ) ) {
				// If error save error message and return false.
				$code          = $avarda_order->get_error_code();
				$message       = $avarda_order->get_error_message();
				$text          = __( 'Avarda API Error on Avarda refund (refund endpoint): ', 'avarda-checkout-for-woocommerce' ) . '%s %s';
				$formated_text = sprintf( $text, $code, $message );
				$order->add_order_note( $formated_text );
				return false;
			}

			$formatted_amount = wc_price( $amount, array( 'currency' => $order->get_currency() ) );
			// Translators: Refunded amount.
			$order->add_order_note( sprintf( __( '%s successfully refunded via Avarda.', 'avarda-checkout-for-woocommerce' ), $formatted_amount ) );
			return true;
		}

		$subscription = $this->check_if_subscription( $order );

		// Get the Avarda order.
		// TODO: Should we do different request if order is subscription?
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

		// Check if B2C or B2B.
		$aco_step = '';
		if ( 'B2C' === $avarda_order_tmp['mode'] ) {
			$aco_step = $avarda_order_tmp['b2C']['step']['current'];
		} elseif ( 'B2B' === $avarda_order_tmp['mode'] ) {
			$aco_step = $avarda_order_tmp['b2B']['step']['current'];
		}

		if ( 'Completed' === $aco_step ) {
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
	 * Add custom actions to order actions select box on edit order page
	 * Only added for paid orders that haven't fired this action yet.
	 *
	 * @param array  $actions order actions array to display.
	 * @param object $order WooCommerce order.
	 * @return array - updated actions
	 */
	public function add_order_actions( $actions, $order ) {

		// If this order wasn't created using aco payment method, bail.
		if ( ! in_array( $order->get_payment_method(), array( 'aco' ), true ) ) {
			return $actions;
		}

		// If the order has not been paid for, bail.
		if ( empty( $order->get_date_paid() ) ) {
			return $actions;
		}

		// If order hasn't already been cancelled or activated - add cancel and activate action.
		if ( empty( $order->get_meta( '_avarda_reservation_cancelled', true ) ) && empty( $order->get_meta( '_avarda_reservation_activated', true ) ) ) {
			$actions['aco_activate_order'] = __( 'Activate Avarda order', 'avarda-checkout-for-woocommerce' );
			$actions['aco_cancel_order']   = __( 'Cancel Avarda order', 'avarda-checkout-for-woocommerce' );
		}

		return $actions;
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
