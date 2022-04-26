<?php // phpcs:ignore
/**
 * Gateway class file.
 *
 * @package Avarda_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gateway class.
 */
class ACO_Gateway extends WC_Payment_Gateway {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = 'aco';
		$this->method_title       = __( 'Avarda Checkout', 'avarda-checkout-for-woocommerce' );
		$this->icon               = '';
		$this->method_description = __( 'Allows payments through ' . $this->method_title . '.', 'avarda-checkout-for-woocommerce' ); // phpcs:ignore

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->enabled     = $this->get_option( 'enabled' );
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->debug       = $this->get_option( 'debug' );
		$this->testmode    = 'yes' === $this->get_option( 'testmode' );

		// Supports.
		$this->supports = array(
			'products',
			'refunds',
		);

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'avarda_thank_you' ) );
		add_action( 'woocommerce_receipt_aco', array( $this, 'receipt_page' ) );
	}

	/**
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {

		$icon_src   = AVARDA_CHECKOUT_URL . '/assets/images/avarda.png';
		$icon_width = '75';
		$icon_html  = '<img src="' . $icon_src . '" alt="Avarda" style="max-width:' . $icon_width . 'px"/>';
		return apply_filters( 'aco_icon_html', $icon_html );
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 *
	 * @return boolean
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			// Do checks here.

			// Avarda doesn't support 0 value subscriptions.
			if ( class_exists( 'WC_Subscriptions_Cart' ) && ( WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal() ) ) {
				if ( 0 == round( WC()->cart->total, 2 ) ) { // phpcs:ignore
					return false;
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * Processes the WooCommerce Payment
	 *
	 * @param string $order_id The WooCommerce order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order                 = wc_get_order( $order_id );
		$avarda_purchase_id    = $this->get_avarda_purchase_id( $order );
		$change_payment_method = filter_input( INPUT_GET, 'change_payment_method', FILTER_SANITIZE_STRING );
		// Order-pay purchase (or subscription payment method change)
		// 1. Redirect to receipt page.
		// 2. Process the payment by displaying the ACO iframe via woocommerce_receipt_aco hook.
		if ( ! empty( $change_payment_method ) ) {
			$pay_url = add_query_arg(
				array(
					'aco-action' => 'change-subs-payment',
				),
				$order->get_checkout_payment_url( true )
			);

			return array(
				'result'   => 'success',
				'redirect' => $pay_url,
			);
		}
		// Regular purchase.
		// 1. Process the payment.
		// 2. Redirect to confirmation page.
		if ( $this->process_payment_handler( $order_id ) ) {
			$confirmation_url = add_query_arg(
				array(
					'aco_confirm'     => 'yes',
					'aco_purchase_id' => $avarda_purchase_id,
					'wc_order_id'     => $order_id,
				),
				wc_get_checkout_url()
			);
			return array(
				'result'       => 'success',
				'redirect_url' => $confirmation_url,
			);
		} else {
			// Something went wrong. Unset sessions and remove previos purchase id from order.
			aco_wc_unset_sessions();
			delete_post_meta( $order_id, '_wc_avarda_purchase_id' );
			delete_post_meta( $order_id, '_transaction_id' );
			return array(
				'result' => 'error',
				'reload' => true,
			);
		}
	}

	/**
	 * Process refund request.
	 *
	 * @param string $order_id The WooCommerce order ID.
	 * @param float  $amount The amount to be refunded.
	 * @param string $reason The reason given for the refund.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		// Refund.
		return ACO_WC()->order_management->refund_payment( $order_id, $amount = null, $reason = '' );
	}

	/**
	 * Process the payment with information from Avarda and return the result.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 *
	 * @return mixed
	 */
	public function process_payment_handler( $order_id ) {
		// Get the Avarda order ID.
		$order = wc_get_order( $order_id );

		$avarda_purchase_id = $this->get_avarda_purchase_id( $order );

		$avarda_order = ACO_WC()->api->request_get_payment( $avarda_purchase_id );
		if ( ! $avarda_order ) {
			// Unset sessions.
			ACO_Logger::log( 'Avarda GET request failed in process payment handler. Clearing Avarda session and reloading the checkout page. Woo order ID: ' . $order_id . '. Avarda purchase ID: ' . $avarda_purchase_id );
			return false;
		}

		if ( $order_id && $avarda_order ) {

			// Get current status of Avarda session.
			if ( 'B2C' === $avarda_order['mode'] ) {
				$aco_state = $avarda_order['b2C']['step']['current'];
			} elseif ( 'B2B' === $avarda_order['mode'] ) {
				$aco_state = $avarda_order['b2B']['step']['current'];
			}

			// check if session TimedOut.
			if ( 'TimedOut' === $aco_state ) {
				ACO_Logger::log( 'Avarda session TimedOut in process payment handler. Clearing Avarda session and reloading the cehckout page. Woo order ID: ' . $order_id . '. Avarda purchase ID: ' . $avarda_purchase_id );
				return false;
			}

			// Set WC order transaction ID.
			update_post_meta( $order_id, '_wc_avarda_purchase_id', sanitize_text_field( $avarda_order['purchaseId'] ) );

			update_post_meta( $order_id, '_transaction_id', sanitize_text_field( $avarda_order['purchaseId'] ) );

			$environment = $this->testmode ? 'test' : 'live';
			update_post_meta( $order_id, '_wc_avarda_environment', $environment );

			$avarda_country = wc_get_base_location()['country'];
			update_post_meta( $order_id, '_wc_avarda_country', $avarda_country );

			$order->save();
			// Let other plugins hook into this sequence.
			do_action( 'aco_wc_process_payment', $order_id, $avarda_order );

			// Check that the transaction id got set correctly.
			if ( strtolower( get_post_meta( $order_id, '_transaction_id', true ) ) === strtolower( $avarda_purchase_id ) ) {
				return true;
			}
		}
		// Return false if we get here. Something went wrong.
		ACO_Logger::log( 'Avarda general error in process payment handler. Clearing Avarda session and reloading the cehckout page. Woo order ID ' . $order_id . '. Avarda purchase ID ' . $avarda_purchase_id );
		return false;
	}


	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = include AVARDA_CHECKOUT_PATH . '/includes/aco-form-fields.php';
	}

	/**
	 * Shows the avarda thankyou on the wc thankyou page.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return void
	 */
	public function avarda_thank_you( $order_id ) {
		if ( $order_id ) {

			// Clear sessionStorage.
			echo '<script>sessionStorage.removeItem("acoRequiredFields")</script>';
			echo '<script>sessionStorage.removeItem("acoFieldData")</script>';

			// Unset sessions.
			aco_wc_unset_sessions();
		}
	}

	/**
	 * Get Avarda purchase id.
	 *
	 * @param WC_Order $order The WC order.
	 * @return string $avarda_purchase_id The Avarda purchase id.
	 */
	public function get_avarda_purchase_id( $order ) {
		$avarda_purchase_id = '';
		if ( is_object( $order ) && ! empty( get_post_meta( $order->get_id(), '_wc_avarda_purchase_id', true ) ) ) {
			$avarda_purchase_id = get_post_meta( $order->get_id(), '_wc_avarda_purchase_id', true );
			ACO_Logger::log( 'Get Avarda purchase ID from order. Order ID' . $order->get_id() . '. Avarda purchase ID: ' . $avarda_purchase_id );
		} else {
			$avarda_purchase_id = aco_get_purchase_id_from_session();
			ACO_Logger::log( 'Get Avarda purchase ID from session. Order ID' . $order->get_id() . '. Avarda purchase ID: ' . $avarda_purchase_id );
		}
		return $avarda_purchase_id;
	}

	/**
	 * Receipt page. Used to display the ACO iframe during subscription payment method change.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function receipt_page( $order ) {
		$aco_action = filter_input( INPUT_GET, 'aco-action', FILTER_SANITIZE_STRING );
		if ( ! empty( $aco_action ) && 'change-subs-payment' === $aco_action ) {
			require AVARDA_CHECKOUT_PATH . '/templates/avarda-change-payment-method.php';
		}
	}

}

/**
 * Add Avarda_Checkout 2.0 payment gateway
 *
 * @wp_hook woocommerce_payment_gateways
 * @param  array $methods All registered payment methods.
 * @return array $methods All registered payment methods.
 */
function add_aco_method( $methods ) {
	$methods[] = 'ACO_Gateway';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_aco_method' );
