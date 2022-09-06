<?php
/**
 * Class for managing actions during the checkout process.
 *
 * @package Avarda_Checkout_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing actions during the checkout process.
 */
class ACO_Checkout {
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_avarda_order' ), 9999 );
	}

	/**
	 * Update the Avarda order after calculations from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_avarda_order() {
		$settings      = get_option( 'woocommerce_aco_settings' );
		$checkout_flow = $settings['checkout_flow'] ?? 'embedded';

		if ( ! is_checkout() ) {
			return;
		}

		if ( 'redirect' === $checkout_flow ) {
			return;
		}

		if ( 'aco' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		// Only when its an actual AJAX request to update the order review (this is when update_checkout is triggered).
		$ajax = filter_input( INPUT_GET, 'wc-ajax', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'update_order_review' !== $ajax ) {
			return;
		}

		$avarda_purchase_id = aco_get_purchase_id_from_session();

		// Check if we have a avarda purchase id.
		if ( empty( $avarda_purchase_id ) ) {
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Avarda purchase ID is missing in update Avarda order function. Clearing Avarda session.' );
			return;
		}

		// Check if the cart hash has been changed since last update.
		$cart_hash  = WC()->cart->get_cart_hash();
		$saved_hash = WC()->session->get( 'aco_last_update_hash' );

		// If they are the same, return.
		if ( $cart_hash === $saved_hash ) {
			return;
		}

		// Set empty return array for errors.
		$return = array();

		// Check that the currency and locale is the same as earlier, otherwise create a new session.
		if ( get_woocommerce_currency() !== WC()->session->get( 'aco_currency' ) || ACO_WC()->checkout_setup->get_language() !== WC()->session->get( 'aco_language' ) ) {
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Currency or language changed in update Avarda function. Clearing Avarda session and reloading the cehckout page.' );
			WC()->session->reload_checkout = true;
			return;
		}

		// Get the Avarda order from Avarda.
		$avarda_order = ACO_WC()->api->request_get_payment( $avarda_purchase_id );

		// Check if we got a wp_error.
		if ( is_wp_error( $avarda_order ) ) {
			// Unset sessions.
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Avarda GET request failed in update Avarda function. Clearing Avarda session.' );
			wc_add_notice( 'Avarda GET request failed.', 'error' );
			return;
		}

		// Check if order needs payment. If not, send refreshZeroAmount so checkout page is reloaded.
		if ( apply_filters( 'aco_check_if_needs_payment', true ) ) {
			if ( ! WC()->cart->needs_payment() ) {
				WC()->session->reload_checkout = true;
				return;
			}
		}

		// Get current status of Avarda session.
		if ( 'B2C' === $avarda_order['mode'] ) {
			$aco_state = $avarda_order['b2C']['step']['current'];
		} elseif ( 'B2B' === $avarda_order['mode'] ) {
			$aco_state = $avarda_order['b2B']['step']['current'];
		}

		// check if session TimedOut.
		if ( 'TimedOut' === $aco_state ) {
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Avarda session TimedOut. Clearing Avarda session and reloading the cehckout page.' );
			WC()->session->reload_checkout = true;
			return;
		}

		if ( ! ( 'Completed' === $aco_state ) ) {
			// Update order.
			$avarda_order = ACO_WC()->api->request_update_payment( $avarda_purchase_id );

			// If the update failed - unset sessions and return error.
			if ( is_wp_error( $avarda_order ) ) {
				// Unset sessions.
				aco_wc_unset_sessions();
				ACO_Logger::log( 'Avarda update request failed in update Avarda function. Clearing Avarda session.' );
				wc_add_notice( 'Avarda update request failed.', 'error' );
				WC()->session->reload_checkout = true;
			}

			$saved_hash = WC()->session->set( 'aco_last_update_hash', $cart_hash );
		}

	}
} new ACO_Checkout();
