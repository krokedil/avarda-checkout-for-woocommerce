<?php
/**
 * Subscription handler.
 *
 * @package Avarda_Checkout_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ACO_Subscription class.
 *
 * Class that has functions for the subscription.
 */
class ACO_Subscription {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'aco_wc_payment_complete', array( $this, 'set_recurring_token_for_order' ), 10, 2 );
		add_action( 'woocommerce_scheduled_subscription_payment_aco', array( $this, 'trigger_scheduled_payment' ), 10, 2 );
	}

	/**
	 * Sets the recurring token for the subscription order
	 *
	 * @param int   $order_id The WooCommerce order id.
	 * @param array $avarda_order The Avarda order.
	 * @return void
	 */
	public function set_recurring_token_for_order( $order_id = null, $avarda_order = null ) {
		$wc_order = wc_get_order( $order_id );
		if ( class_exists( 'WC_Subscription' ) && ( wcs_order_contains_subscription( $wc_order, array( 'parent', 'renewal', 'resubscribe', 'switch' ) ) || wcs_is_subscription( $wc_order ) ) ) {
			$subscriptions      = wcs_get_subscriptions_for_order( $order_id );
			$avarda_purchase_id = $wc_order->get_transaction_id();
			$avarda_order       = ACO_WC()->api->request_get_payment( $avarda_purchase_id, true );
			if ( isset( $avarda_order['paymentMethods']['selectedPayment'] ) ) {
				$recurring_token = $avarda_order['paymentMethods']['selectedPayment']['recurringPaymentToken'];
				// translators: %s Avarda recurring token.
				$note = sprintf( __( 'Recurring token for subscription: %s', 'avarda-checkout-for-woocommerce' ), sanitize_key( $recurring_token ) );
				// za order parent nisam ubacio token.
				$wc_order->add_order_note( $note );

				foreach ( $subscriptions as $subscription ) {
					update_post_meta( $subscription->get_id(), '_aco_recurring_token', $recurring_token );
					update_post_meta( $subscription->get_id(), '_wc_avarda_purchase_id', $avarda_purchase_id );
				}
			} else {
				$wc_order->add_order_note( __( 'Recurring token was missing from the Avarda order during the checkout process. Please contact Avarda for help.', 'avarda-checkout-for-woocommerce' ) );
				$wc_order->set_status( 'on-hold' );
				$wc_order->save();
				foreach ( $subscriptions as $subscription ) {
					$subscription->set_status( 'on-hold' );
				}
			}
		}
	}

	/**
	 * Creates an order in Avarda from the recurring token saved.
	 *
	 * @param string $renewal_total The total price for the order.
	 * @param object $renewal_order The WooCommerce order for the renewal.
	 */
	public function trigger_scheduled_payment( $renewal_total, $renewal_order ) {
		$order_id = $renewal_order->get_id();

		$subscriptions   = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
		$recurring_token = get_post_meta( $order_id, '_aco_recurring_token', true );

		if ( empty( $recurring_token ) ) {
			$recurring_token = get_post_meta( $order_id, '_aco_recurring_token', true );
			$purchase_id     = get_post_meta( $order_id, '_wc_avarda_purchase_id', true );
			update_post_meta( $order_id, '_aco_recurring_token', $recurring_token );
			update_post_meta( $order_id, '_wc_avarda_purchase_id', $purchase_id );
		}

		$create_order_response = ACO_WC()->api->create_recurring_order( $order_id, $recurring_token );
		if ( ! is_wp_error( $create_order_response ) ) {
			$avarda_order_id = $create_order_response['order_id'];
			// Translators: Klarna order id.
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment made with Avarda. Avarda order id: %s', 'avarda-checkout-for-woocommerce' ), $avarda_order_id ) );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_complete( $avarda_order_id );
			}
		} else {
			$error_message = $create_order_response->get_error_message();
			// Translators: Error message.
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment failed with Avarda. Message: %1$s', 'avarda-checkout-for-woocommerce' ), $error_message ) );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_failed();
			}
		}
	}
}

new ACO_Subscription();

