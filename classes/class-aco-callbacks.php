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
		add_action( 'woocommerce_api_aco_wc_validation', array( $this, 'validate_cb' ) );
		add_action( 'aco_check_for_order', array( $this, 'aco_check_for_order_callback' ), 10, 2 );
		$this->needs_login = 'no' === get_option( 'woocommerce_enable_guest_checkout' ) ? true : false; // Needs to be logged in order to checkout.
	}

	/**
	 * Handles validation callbacks.
	 *
	 * @return void
	 */
	public function validate_cb() {

	}

	public function aco_check_for_order_callback( $payment_id ) {
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'Avarda_Checkout',
				'date_created'   => '>' . ( time() - MONTH_IN_SECONDS ),
			)
		);
		$orders         = $query->get_orders();
		$order_id_match = '';

		foreach ( $orders as $order_id ) {
			$order_payment_id = get_post_meta( $order_id, '_payson_checkout_id', true );

			if ( $order_payment_id === $payment_id ) {
				$order_id_match = $order_id;
				break;
			}
		}

		// Did we get a match?
		if ( $order_id_match ) {
			// If we have an order, do your thing here.
		} else {
			// If we dont have an order, do your thing here.
		}
	}


	/**
	 * Checks if the email exists as a user and if they are logged in.
	 *
	 * @return void
	 */
	public function check_if_user_exists_and_logged_in() {
		// Set customer email here.
		// $customer_email = ;

		// Check if the email exists as a user.
		$user = email_exists( $customer_email );

		// If not false, user exists. Check if the session id matches the User id.
		if ( false !== $user ) {
			if ( $user != $_GET['aco_session_id'] ) {
				$this->order_is_valid                    = false;
				$this->validation_messages['user_login'] = __( 'An account already exists with this email. Please login to complete the purchase.', 'avarda-checkout-for-woocommerce' );
			}
		}
	}
}
new ACO_Callbacks();
