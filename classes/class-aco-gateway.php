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
	 * Testmode.
	 *
	 * @var boolean
	 */
	public $testmode;

	/**
	 * Payment gateway icon.
	 *
	 * @var string
	 */
	public $payment_gateway_icon;

	/**
	 * Payment gateway icon max width.
	 *
	 * @var string
	 */
	public $payment_gateway_icon_max_width;

	/**
	 * Checkout flow.
	 *
	 * @var string
	 */
	public $checkout_flow;

	/**
	 * Debug mode.
	 *
	 * @var boolean
	 */
	public $debug;

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
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'upsell',
		);

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'avarda_thank_you' ) );
		add_action( 'woocommerce_receipt_aco', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_totals' ), 10, 2 );
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
		// Ensure that the Avarda Checkout gateway is enabled.
		if ( 'yes' !== $this->enabled ) {
			ACO_Logger::log( 'Avarda Checkout payment gateway is not enabled.', WC_Log_Levels::DEBUG );
			return false;
		}

		// If we are on an admin page, rest request, or doing cron, just return true.
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		// If we have an avarda session, ensure the currency matches the current currency for the store.
		$avarda_payment = ACO_WC()->session()->get_avarda_payment();
		if ( ! is_wp_error( $avarda_payment ) && ! empty( $avarda_payment ) ) {
			// Ensure the currency matches the current currency for the store.
			$payment_currency = $avarda_payment['checkoutSite']['currencyCode'] ?? '';
			$wc_currency      = get_woocommerce_currency();
			if ( strtoupper( $wc_currency ) !== strtoupper( $payment_currency ) ) {
				ACO_Logger::log( 'Currency mismatch. The Avarda Checkout payment gateway is not available.', WC_Log_Levels::DEBUG );
				return false;
			}
		}

		// Avarda doesn't support 0 value subscriptions.
		if ( class_exists( 'WC_Subscriptions_Cart' ) && ( WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal() ) ) {
			if ( 0 == round( WC()->cart->total, 2 ) ) { // phpcs:ignore
				ACO_Logger::log( 'Subscription total is 0. The Avarda Checkout payment gateway is not available.', WC_Log_Levels::DEBUG );
				return false;
			}
		}

		return true;
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
			// Something went wrong. Unset sessions and remove previous purchase id from order.
			ACO_Logger::log( sprintf( 'Processing order %s|%s (Avarda ID: %s) failed for some reason. Clearing session.', $order_id, $order->get_order_key(), $avarda_purchase_id ) );

			aco_wc_unset_sessions();
			aco_delete_avarda_meta_data_from_order( $order );

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
		$avarda_order       = ACO_WC()->session()->get_avarda_payment();

		if ( is_wp_error( $avarda_order ) || empty( $avarda_order ) ) {
			$this->log_process_payment_get_error( $avarda_order, $order_id, $avarda_purchase_id );
			return false;
		}

		if ( $order_id && $avarda_order ) {
			$purchase_id = sanitize_text_field( ACO_WC()->session()->get_purchase_id() );
			$order->update_meta_data( '_wc_avarda_purchase_id', $purchase_id );
			$order->set_transaction_id( $purchase_id );
			$order->update_meta_data( '_wc_avarda_environment', $this->testmode ? 'test' : 'live' );
			$order->update_meta_data( '_wc_avarda_country', wc_get_base_location()['country'] );

			$order->save();

			// Let other plugins hook into this sequence.
			do_action( 'aco_wc_process_payment', $order_id, $avarda_order );

			// Check that the transaction id got set correctly.
			if ( strtolower( $order->get_transaction_id() ) === strtolower( $avarda_purchase_id ) ) {
				return true;
			}
		}
		// Return false if we get here. Something went wrong.
		ACO_Logger::log( 'Avarda general error in process payment handler. Clearing Avarda session and reloading the checkout page. Woo order ID ' . $order_id . '. Avarda purchase ID ' . $avarda_purchase_id );
		return false;
	}

	/**
	 * Handle process payment error.
	 *
	 * @param WP_Error|bool $error The error object.
	 * @param int           $order_id The WooCommerce order ID.
	 * @param string        $purchase_id The Avarda purchase ID.
	 *
	 * @return void
	 */
	public function log_process_payment_get_error( $error, $order_id, $purchase_id ) {
		$message = "Avarda GET request failed in process payment handler. Clearing Avarda session and reloading the checkout page. Woo order ID: $order_id. Avarda purchase ID: $purchase_id";
		if ( ! is_wp_error( $error ) ) {
			ACO_Logger::log( $message );
			return;
		}

		$code = $error->get_error_code();
		if ( 'avarda_checkout_payment_invalid' === $code || 'avarda_checkout_session_invalid' === $code ) {
			$error_message = $error->get_error_message();
			$message       = "Avarda session failed to validate $error_message. Clearing Avarda session and reloading the checkout page. Woo order ID: $order_id. Avarda purchase ID: $purchase_id";
		}

		// Unset sessions.
		ACO_Logger::log( $message );
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

			ACO_Logger::log( "Loading change payment template for Avarda checkout: {$avarda_change_payment_method_template}", WC_Log_Levels::DEBUG );
			require $avarda_change_payment_method_template;
		} else {

			if ( locate_template( 'woocommerce/avarda-order-receipt.php' ) ) {
				$avarda_order_receipt_template = locate_template( 'woocommerce/avarda-order-receipt.php' );
			} else {
				$avarda_order_receipt_template = apply_filters( 'aco_locate_template', AVARDA_CHECKOUT_PATH . '/templates/avarda-order-receipt.php', $template_name );
			}
			ACO_Logger::log( "Loading order receipt template for Avarda checkout: {$avarda_order_receipt_template}", WC_Log_Levels::DEBUG );
			require $avarda_order_receipt_template;
		}
	}

	/**
	 * Validate the totals for the cart and the Avarda order before processing the payment.
	 *
	 * @param array    $data An array of posted data.
	 * @param WP_Error $errors Validation errors.
	 *
	 * @return void
	 */
	public function validate_totals( $data, $errors ) {
		// Only if the chosen payment method is Avarda Checkout.
		if ( $this->id !== $data['payment_method'] ) {
			return;
		}

		// If we are using the checkout flow "redirect", we don't need to validate the totals.
		if ( 'redirect' === $this->checkout_flow ) {
			return;
		}

		$avarda_order = ACO_WC()->session()->get_avarda_payment();

		if ( is_wp_error( $avarda_order ) || empty( $avarda_order ) ) {
			$errors->add( 'avarda_checkout_error', __( 'The order could not be verified, please try again.', 'avarda-checkout-for-woocommerce' ) );
			return;
		}

		// Get the cart totals for the entire WooCommerce cart.
		$cart_totals     = WC()->cart->get_totals();
		$wc_total        = intval( round( $cart_totals['total'] * 100, 2 ) );
		$aco_order_total = intval( round( $avarda_order['totalPrice'] * 100, 2 ) );

		// Get the difference between the WooCommerce cart total and the Avarda order total.
		$diff = abs( $wc_total - $aco_order_total );

		// If the diff is greater than 3, add an error.
		if ( $diff > 3 ) {
			$errors->add( 'avarda_checkout_error', __( 'The order could not be verified, please try again.', 'avarda-checkout-for-woocommerce' ) );
		}
	}

	/**
	 * Check if the order is available for upsell.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return bool
	 */
	public function upsell_available( $order_id ) {
		$order       = wc_get_order( $order_id );
		$purchase_id = $order->get_transaction_id();

		if ( empty( $purchase_id ) ) {
			return false;
		}

		// Maybe get the customer balance from the order meta itself.
		$customer_balance = $order->get_meta( '_avarda_customer_balance' );

		// If we did not have a customer balance in the order meta, we need to get it from the Avarda API.
		if ( empty( $customer_balance ) ) {
			$avarda_order = ACO_WC()->api->request_get_payment( $purchase_id, true );

			if ( is_wp_error( $avarda_order ) ) {
				return false;
			}

			// Get the customer balance from the avarda order and compare to the orders total amount.
			$customer_balance = $avarda_order['customerBalance'] ?? 0;
		}

		// Compare, if the customer_balance is less than the order total, we can't do an upsell.
		$order_total = $order->get_total();
		if ( $customer_balance < $order_total ) {
			return false;
		}

		return true;
	}

	/**
	 * Handles a upsell request.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $upsell_uuid The UUID for the Upsell request.
	 * @return bool|WP_Error
	 */
	public function upsell( $order_id, $upsell_uuid ) {
		// If the upsell is available, then we can just return true. The order lines will be captured in the activation request anyway.
		return true;
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
