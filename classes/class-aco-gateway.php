<?php
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
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'avarda_thank_you' ) );
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 *
	 * @return boolean
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			// Do checks here.
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
		$order = wc_get_order( $order_id );

		// Regular purchase.
		// 1. Process the payment.
		// 2. Redirect to order received page.
		if ( $this->process_payment_handler( $order_id ) ) {
			$response = array(
				'return_url' => $this->get_return_url( $order ),
				'time'       => microtime(),
			);
			return array(
				'result'   => 'success',
				'redirect' => '#avarda-success=' . base64_encode( wp_json_encode( $response ) ),
			);
		} else {
			return array(
				'result' => 'error',
			);
		}
	}

	/**
	 * Process refund request.
	 *
	 * @param string $order_id The WooCommerce order ID.
	 * @param float  $amount The amount to be refunded.
	 * @param string $reasson The reasson given for the refund.
	 */
	public function process_refund( $order_id, $amount = null, $reasson = '' ) {
		$order = wc_get_order( $order_id );
		// Refund full amount.

		// Run logic here.
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
		if ( is_object( $order ) && $order->get_transaction_id() ) {
			$avarda_purchase_id = $order->get_transaction_id();
		} else {
			$avarda_purchase_id = WC()->session->get( 'aco_wc_purchase_id' );
		}

		$avarda_order = ACO_WC()->api->request_get_payment( $avarda_purchase_id );
		if ( ! $avarda_order ) {
			return false;
		}

		if ( $order_id && $avarda_order ) {

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
		// Check if order is subscription.
		$order = wc_get_order( $order_id );

		// Show avarda checkout form.
		aco_wc_show_checkout_form();

		// Clear sessionStorage.
		echo '<script>sessionStorage.removeItem("acoRequiredFields")</script>';
		echo '<script>sessionStorage.removeItem("acoFieldData")</script>';

		// Unset sessions.
		aco_wc_unset_sessions();
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
