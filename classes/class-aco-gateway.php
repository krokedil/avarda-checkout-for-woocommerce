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
		$this->enabled                        = $this->get_option( 'enabled' );
		$this->title                          = $this->get_option( 'title' );
		$this->description                    = $this->get_option( 'description' );
		$this->debug                          = $this->get_option( 'debug' );
		$this->testmode                       = 'yes' === $this->get_option( 'testmode' );
		$this->checkout_flow                  = $this->get_option( 'checkout_flow', 'embedded' );
		$this->payment_gateway_icon           = get_option( 'woocommerce_aco_settings', array() )['payment_gateway_icon'] ?? 'default';
		$this->payment_gateway_icon_max_width = $this->get_option( 'payment_gateway_icon_max_width', '75' );

		// Supports.
		$this->supports = array(
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'multiple_subscriptions',
			// 'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
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
		if ( empty( $this->payment_gateway_icon ) ) {
			return;
		}

		if ( 'default' === strtolower( $this->payment_gateway_icon ) ) {
			$icon_src   = AVARDA_CHECKOUT_URL . '/assets/images/avarda.png';
			$icon_width = '75';
		} else {
			$icon_src   = $this->payment_gateway_icon;
			$icon_width = $this->payment_gateway_icon_max_width;
		}

		$icon_html = '<img src="' . $icon_src . '" alt="Avarda" style="max-width:' . $icon_width . 'px"/>';
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
		$change_payment_method = filter_input( INPUT_GET, 'change_payment_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		// Subscription payment method change.
		// 1. Redirect to receipt page.
		// 2. Process the payment by displaying the ACO iframe via woocommerce_receipt_aco hook.
		if ( ! empty( $change_payment_method ) ) {
			ACO_Logger::log( sprintf( 'Processing order %s|%s (Avarda ID: %s) OK. Changing payment method for subscription.', $order_id, $order->get_order_key(), $avarda_purchase_id ) );
			return $this->process_subscription_payment_change_handler( $order );
		}

		// Order pay.
		if ( is_wc_endpoint_url( 'order-pay' ) || 'redirect' === $this->checkout_flow ) {
			ACO_Logger::log( sprintf( 'Processing order %s|%s (Avarda ID: %s) OK. Redirecting to order pay page.', $order_id, $order->get_order_key(), $avarda_purchase_id ) );

			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}
		// Regular purchase.
		// 1. Process the payment.
		// 2. Redirect to confirmation page.
		if ( $this->process_payment_handler( $order_id ) ) {
			ACO_Logger::log( sprintf( 'Processing order %s|%s (Avarda ID: %s) OK. Redirecting to confirmation page.', $order_id, $order->get_order_key(), $avarda_purchase_id ) );

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
			ACO_Logger::log( sprintf( 'Processing order %s|%s (Avarda ID: %s) failed for some reason. Clearing session.', $order_id, $order->get_order_key(), $avarda_purchase_id ) );

			aco_wc_unset_sessions();
			$order->delete_meta_data( '_wc_avarda_purchase_id' );
			$order->set_transaction_id( '' );
			$order->save();
			return array(
				'result' => 'error',
				'reload' => true,
			);
		}
	}

	/**
	 * Handle switching payment method for subscription.
	 *
	 * @param  WC_Order $order Woocommerce order.
	 * @return array.
	 */
	public function process_subscription_payment_change_handler( $order ) {
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
		return ACO_WC()->order_management->refund_payment( $order_id, $amount, $reason );
	}

	/**
	 * Process the payment with information from Avarda and return the result.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 *
	 * @return boolean
	 */
	public function process_payment_handler( $order_id ) {
		// Get the Avarda order ID.
		$order              = wc_get_order( $order_id );
		$avarda_purchase_id = $this->get_avarda_purchase_id( $order );
		$avarda_order       = ACO_WC()->api->request_get_payment( $avarda_purchase_id );
		if ( is_wp_error( $avarda_order ) ) {
			// Unset sessions.
			ACO_Logger::log( 'Avarda GET request failed in process payment handler. Clearing Avarda session and reloading the checkout page. Woo order ID: ' . $order_id . '. Avarda purchase ID: ' . $avarda_purchase_id );
			return false;
		}

		if ( $order_id && $avarda_order ) {

			// Get current status of Avarda session.
			if ( 'B2C' === $avarda_order['mode'] ) {
				$aco_step = $avarda_order['b2C']['step']['current'];
			} elseif ( 'B2B' === $avarda_order['mode'] ) {
				$aco_step = $avarda_order['b2B']['step']['current'];
			}

			// check if session TimedOut.
			if ( 'TimedOut' === $aco_step ) {
				ACO_Logger::log( 'Avarda session TimedOut in process payment handler. Clearing Avarda session and reloading the cehckout page. Woo order ID: ' . $order_id . '. Avarda purchase ID: ' . $avarda_purchase_id );
				return false;
			}

			$purchase_id = sanitize_text_field( $avarda_order['purchaseId'] );
			$order->update_meta_data( '_wc_avarda_purchase_id', $purchase_id );
			$order->set_transaction_id( $purchase_id );
			$order->update_meta_data( '_wc_avarda_environment', $this->testmode ? 'test' : 'live' );
			$order->update_meta_data( '_wc_avarda_country', wc_get_base_location()['country'] );

			$order->save();

			// Let other plugins hook into this sequence.
			do_action( 'aco_wc_process_payment', $order_id, $avarda_order );

			// Check that the transaction id got set correctly.
			if ( strtolower( $order->get_transaction_id() === strtolower( $avarda_purchase_id ) ) ) {
				return true;
			}
		}
		// Return false if we get here. Something went wrong.
		ACO_Logger::log( 'Avarda general error in process payment handler. Clearing Avarda session and reloading the checkout page. Woo order ID ' . $order_id . '. Avarda purchase ID ' . $avarda_purchase_id );
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
		if ( is_object( $order ) && ! empty( $order->get_meta( '_wc_avarda_purchase_id', true ) ) ) {
			$avarda_purchase_id = $order->get_meta( '_wc_avarda_purchase_id', true );
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
	 * @param int $order_id The WooCommerce order ID.
	 * @return void
	 */
	public function receipt_page( $order_id ) {
		$aco_action    = filter_input( INPUT_GET, 'aco-action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$template_name = 'checkout/order-receipt.php';
		if ( ! empty( $aco_action ) && 'change-subs-payment' === $aco_action ) {

			if ( locate_template( 'woocommerce/avarda-change-payment-method.php' ) ) {
				$avarda_change_payment_method_template = locate_template( 'woocommerce/avarda-change-payment-method.php' );
			} else {
				$avarda_change_payment_method_template = apply_filters( 'aco_locate_template', AVARDA_CHECKOUT_PATH . '/templates/avarda-change-payment-method.php', $template_name );
			}
			require $avarda_change_payment_method_template;
		} else {

			if ( locate_template( 'woocommerce/avarda-order-receipt.php' ) ) {
				$avarda_order_receipt_template = locate_template( 'woocommerce/avarda-order-receipt.php' );
			} else {
				$avarda_order_receipt_template = apply_filters( 'aco_locate_template', AVARDA_CHECKOUT_PATH . '/templates/avarda-order-receipt.php', $template_name );
			}
			require $avarda_order_receipt_template;

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
