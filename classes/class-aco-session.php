<?php
/**
 * Class for handling the checkout sessions with Avarda Checkout.
 *
 * @package Avarda_Checkout/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * ACO_Session class.
 */
class ACO_Session {
	/**
	 * Avarda purchase returned from the Avarda API.
	 *
	 * @var array
	 */
	protected $avarda_payment;

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
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 *
	 * @return void
	 */
	private function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Nope', 'avarda-checkout-for-woocommerce' ), '1.0' );
	}
	/**
	 * Public unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Nope', 'avarda-checkout-for-woocommerce' ), '1.0' );
	}

	/**
	 * Get the Avarda payment.
	 *
	 * If it has not already been set, it will try to get it from the order or the session.
	 * If no purchase id is found in either the order or session, it will return false.
	 * If any errors happen during the verification of the payment, it will return a WP_Error object.
	 *
	 * @param int|WC_Order $order WooCommerce order. Default is null.
	 *
	 * @return array|bool|WP_Error
	 */
	public function get_avarda_payment( $order = null ) {
		// Maybe get the order if its not a WC_Order object.
		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order );
		}

		// Ensure the current customer session is still valid, but only if we don't have an order.
		if ( empty( $order ) ) {
			$verify_session = $this->verify_cart_session();

			if ( is_wp_error( $verify_session ) ) {
				return $verify_session;
			}
		}

		// See if we need to get the avarda payment or not.
		if ( empty( $this->avarda_payment ) ) {
			// Get the purchase id from the order or the session.
			$purchase_id = $order ? $order->get_meta( '_wc_avarda_purchase_id' ) : aco_get_purchase_id_from_session();

			// If we did not have any purchase id, it means that we don't have a Avarda payment. So return false to indicate that none exists.
			if ( empty( $purchase_id ) ) {
				ACO_Logger::log( 'No purchase id found in order or session when getting Avarda Payment.', WC_Log_Levels::DEBUG );
				return false;
			}

			ACO_WC()->api->request_get_payment( $purchase_id );
		}

		// If the payment is a WP_Error, return it.
		if ( is_wp_error( $this->avarda_payment ) ) {
			return $this->avarda_payment;
		}

		// Verify the payment.
		$verify_result = $this->verify_payment( $order );

		// If the payment is invalid, return the error.
		if ( is_wp_error( $verify_result ) ) {
			return $verify_result;
		}

		return $this->avarda_payment;
	}

	/**
	 * Set the Avarda payment.
	 *
	 * @param array $avarda_payment Avarda payment.
	 *
	 * @return void
	 */
	public function set_avarda_payment( $avarda_payment ) {
		$this->avarda_payment = $avarda_payment;
	}

	/**
	 * Update the Avarda order items after a update request has been successfully made.
	 *
	 * @param array $items Avarda order.
	 *
	 * @return void
	 */
	public function update_avarda_order_items( $items ) {
		if ( ! $this->avarda_payment ) {
			$this->get_avarda_payment();
		}

		$this->avarda_payment['items'] = $items;
	}

	/**
	 * Verify the current cart session with stores session.
	 *
	 * @return bool|WP_Error
	 */
	public function verify_cart_session() {
		try {
			$this->has_currency_changed()
				->has_language_changed();
		} catch ( Exception $e ) {
			$this->log_verification_exception( $e );
			return new WP_Error( 'avarda_checkout_session_invalid', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Verify the Avarda payment to ensure its still valid to use.
	 *
	 * @param WC_Order|null $order WooCommerce order. Default is null.
	 *
	 * @return bool|WP_Error
	 */
	public function verify_payment( $order = null ) {
		if ( ! $this->avarda_payment ) {
			return false;
		}

		try {
			// Verify the step first, since it might redirect to the thankyou page.
			$this->is_payment_step_valid()
				->has_payment_expired(); // Check if the payment has expired.
		} catch ( Exception $e ) {
			$this->log_verification_exception( $e, $order );
			return new WP_Error( 'avarda_checkout_payment_invalid', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Get the purchase id for the Avarda session.
	 *
	 * @return string
	 */
	public function get_purchase_id() {
		if ( ! $this->avarda_payment ) {
			return '';
		}

		return $this->avarda_payment['purchaseId'];
	}

	/**
	 * Get the payment step for the Avarda session.
	 *
	 * @return string
	 */
	public function get_payment_step() {
		if ( ! $this->avarda_payment ) {
			return 'unknown';
		}

		// Lowercase the mode to match the key in the payment object.
		$mode = lcfirst( $this->avarda_payment['mode'] );
		$step = $this->avarda_payment[ $mode ]['step']['current'] ?? 'unknown';

		return $step;
	}

	/**
	 * Is the step for the payment valid.
	 *
	 * @throws Exception If the payment step is invalid.
	 * @return self
	 */
	public function is_payment_step_valid() {
		if ( ! $this->avarda_payment ) {
			return $this;
		}

		$step = $this->get_payment_step();

		switch ( $step ) {
			case 'TimedOut':
				throw new Exception( __( 'Avarda Checkout payment has timed out.', 'avarda-checkout-for-woocommerce' ) ); // phpcs:ignore
			case 'Completed':
				// Special case, if the payment is completed, then get the WC order and send the customer to the thankyou page for the order.
				$purchase_id = $this->avarda_payment['purchaseId'];
				$order       = aco_get_order_by_purchase_id( $purchase_id );

				// If we could not get an order, just return.
				if ( ! $order ) {
					return $this;
				}

				$confirmation_url = add_query_arg(
					array(
						'aco_confirm'     => 'yes',
						'aco_purchase_id' => $purchase_id,
						'wc_order_id'     => $order->get_id(),
					),
					$order->get_checkout_order_received_url()
				);

				wp_safe_redirect( $confirmation_url );
				exit;
			default:
				return $this; // Default to true to not prevent unknown steps from being valid if Avarda adds new steps.
		}
	}

	/**
	 * Check if the payment has expired or not.
	 *
	 * @throws Exception If the payment has expired.
	 * @return self
	 */
	public function has_payment_expired() {
		if ( ! $this->avarda_payment ) {
			return $this;
		}

		$expired_utc = $this->avarda_payment['expiredUtc'] ?? '0';
		$has_expired = time() > strtotime( $expired_utc );

		if ( $has_expired ) {
			throw new Exception( __( 'Avarda Checkout payment has expired.', 'avarda-checkout-for-woocommerce' ) ); // phpcs:ignore
		}

		return $this;
	}

	/**
	 * Check if the currency has been changed or not for the customer session.
	 *
	 * @throws Exception If the currency has changed.
	 * @return self
	 */
	public function has_currency_changed() {
		$wc_currency      = get_woocommerce_currency();
		$session_currency = WC()->session->get( 'aco_currency', '' );

		// If we don't have a session currency, then we don't need to check.
		if ( empty( $session_currency ) ) {
			return $this;
		}

		$has_changed = $wc_currency !== $session_currency;
		if ( $has_changed ) {
			throw new Exception( __( 'Avarda Checkout currency has changed.', 'avarda-checkout-for-woocommerce' ) ); // phpcs:ignore
		}

		return $this;
	}

	/**
	 * Check if the language has change or not for the customer session.
	 *
	 * @throws Exception If the language has changed.
	 * @return self
	 */
	public function has_language_changed() {
		$current_language = ACO_WC()->checkout_setup->get_language();
		$session_language = WC()->session->get( 'aco_language', '' );

		// If we don't have a session language, then we don't need to check.
		if ( empty( $session_language ) ) {
			return $this;
		}

		$has_changed = $current_language !== $session_language;

		if ( $has_changed ) {
			throw new Exception( __( 'Avarda Checkout language has changed.', 'avarda-checkout-for-woocommerce' ) ); // phpcs:ignore
		}

		return $this;
	}

	/**
	 * Log any verification errors that happen.
	 *
	 * @param Exception     $e Exception object.
	 * @param WC_Order|null $order WooCommerce order.
	 *
	 * @return void
	 */
	private function log_verification_exception( $e, $order = null ) {
		$purchase_id = $this->get_purchase_id();

		if ( empty( $purchase_id ) ) {
			$purchase_id = $order ? $order->get_meta( 'aco_purchase_id' ) : aco_get_purchase_id_from_session();
		}

		$message = sprintf(
			/* translators: 1: error message, 2: order id */
			__( '%1$s Purchase Id: %2$s.%3$s', 'avarda-checkout-for-woocommerce' ),
			$e->getMessage(),
			$purchase_id,
			$order ? '. Order id: ' . $order->get_id() : ''
		);

		ACO_Logger::log( $message );
	}
}
