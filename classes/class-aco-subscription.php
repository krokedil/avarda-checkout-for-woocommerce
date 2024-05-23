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
	 * Test mode.
	 *
	 * @var bool
	 */
	private $testmode;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'aco_wc_api_request_args', array( $this, 'set_recurring' ) );
		add_action( 'aco_wc_payment_complete', array( $this, 'set_recurring_token_for_order' ), 10, 2 );
		add_action( 'woocommerce_scheduled_subscription_payment_aco', array( $this, 'trigger_scheduled_payment' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_recurring_token' ) );
		add_action( 'init', array( $this, 'display_thankyou_message_for_payment_method_change' ) );

		$avarda_settings = get_option( 'woocommerce_aco_settings' );
		$this->testmode  = ( isset( $avarda_settings['testmode'] ) && 'yes' === $avarda_settings['testmode'] ) ? true : false;
	}

	/**
	 * Sets the recurring token for the subscription order
	 *
	 * @param int   $order_id The WooCommerce order id.
	 * @param array $avarda_order The Avarda order.
	 * @return void
	 */
	public function set_recurring_token_for_order( $order_id = null, $avarda_order = null ) {
		$wc_order        = wc_get_order( $order_id );
		$recurring_token = $avarda_order['paymentMethods']['selectedPayment']['recurringPaymentToken'] ?? '';
		$purchase_id     = $avarda_order['purchaseId'] ?? '';

		if ( ! empty( $recurring_token ) ) {
			// translators: %s Avarda recurring token.
			$note = sprintf( __( 'Avarda subscription ID/recurring token %s saved', 'avarda-checkout-for-woocommerce' ), sanitize_key( $recurring_token ) );
			$wc_order->add_order_note( $note );
			$wc_order->update_meta_data( '_aco_recurring_token', $recurring_token );
			$wc_order->save();

			// This function is run after WCS has created the subscription order.
			// Let's add the _aco_recurring_token to the subscription as well.
			if ( class_exists( 'WC_Subscriptions' ) && ( wcs_order_contains_subscription(
				$wc_order,
				array(
					'parent',
					'renewal',
					'resubscribe',
					'switch',
				)
			) || wcs_is_subscription( $wc_order ) ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
				foreach ( $subscriptions as $subscription ) {
					// translators: %s Avarda recurring token.
					$subscription->add_order_note( sprintf( __( 'Avarda subscription ID/recurring token %s saved.', 'avarda-checkout-for-woocommerce' ), $recurring_token ) );
					$subscription->update_meta_data( '_aco_recurring_token', $recurring_token );
					$subscription->update_meta_data( '_wc_avarda_purchase_id', $purchase_id );
					$subscription->update_meta_data( '_wc_avarda_environment', $this->testmode ? 'test' : 'live' );
					$subscription->save();
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
		$recurring_token = $renewal_order->get_meta( '_aco_recurring_token', true );
		$purchase_id     = $renewal_order->get_meta( '_wc_avarda_purchase_id', true );

		// Check that we have a recurring token.
		if ( empty( $recurring_token ) ) {
			// Try getting it from parent order.
			$parent_order_recurring_token = get_post_meta( WC_Subscriptions_Renewal_Order::get_parent_order_id( $order_id ), '_aco_recurring_token', true );

			if ( ! empty( $parent_order_recurring_token ) ) {
				$renewal_order->update_meta_data( '_aco_recurring_token', $parent_order_recurring_token );
				$renewal_order->save();

				foreach ( $subscriptions as $subscription ) {
					$subscription->update_meta_data( '_aco_recurring_token', $parent_order_recurring_token );
					$subscription->save();
				}
			}
		}

		// Check that we have a purchase id. This is also needed in the request to Avarda.
		if ( empty( $purchase_id ) ) {
			// Try getting it from parent order.
			$parent_order_purchase_id = get_post_meta( WC_Subscriptions_Renewal_Order::get_parent_order_id( $order_id ), '_wc_avarda_purchase_id', true );

			if ( ! empty( $parent_order_purchase_id ) ) {
				$renewal_order->update_meta_data( '_wc_avarda_purchase_id', $parent_order_purchase_id );
				$renewal_order->save();

				foreach ( $subscriptions as $subscription ) {
					$subscription->update_meta_data( '_wc_avarda_purchase_id', $parent_order_purchase_id );
					$subscription->save();
				}
			}
		}

		// Create recurring Avarda order.
		$create_order_response = ACO_WC()->api->create_recurring_order( $order_id );

		if ( ! is_wp_error( $create_order_response ) && is_array( $create_order_response ) ) {
			$avarda_purchase_id = $create_order_response['purchaseId'];
			// Translators: Avarda purchase id.
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment made with Avarda. Avarda purchase id: %s', 'avarda-checkout-for-woocommerce' ), $avarda_purchase_id ) );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_complete( $avarda_purchase_id );
			}
		} else {
			/**
			 * An instance of WP_Error
			 *
			 * @var $create_order_response WP_Error
			 */
			$error_message = $create_order_response->get_error_message();
			// Translators: Error message.
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment failed with Avarda. Message: %1$s', 'avarda-checkout-for-woocommerce' ), $error_message ) );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_failed();
			}
		}
	}


	/**
	 * Shows the recurring token for the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function show_recurring_token( $order ) {
		if ( 'shop_subscription' === $order->get_type() && $order->get_meta( '_aco_recurring_token' ) ) {
			?>
			<div class="order_data_column" style="clear:both; float:none; width:100%;">
				<div class="address">
					<p>
						<strong><?php echo esc_html( 'Avarda recurring token' ); ?>:</strong><?php echo esc_html( $order->get_meta( '_aco_recurring_token', true ) ); ?>
					</p>
				</div>
				<div class="edit_address">
					<?php
					woocommerce_wp_text_input(
						array(
							'id'            => '_aco_recurring_token',
							'label'         => __( 'Avarda recurring token', 'avarda-checkout-for-woocommerce' ),
							'wrapper_class' => '_billing_company_field',
						)
					);
					?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Marks the order as a recurring order for ACO
	 *
	 * @param array $request_args The Avarda request arguments.
	 * @return array
	 */
	public function set_recurring( $request_args ) {
		$is_recurring = aco_get_wc_cart_contains_subscription() || $this->is_aco_subs_change_payment_method();
		if ( apply_filters( 'aco_is_subscription', $is_recurring, $request_args ) ) {
			$request_args['checkoutSetup']['recurringPayments']                      = 'checked';
			$request_args['checkoutSetup']['hideUnsupportedRecurringPaymentMethods'] = true;
		}
		if ( $this->is_aco_subs_change_payment_method() ) {
			$key      = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$order_id = wc_get_order_id_by_order_key( $key );
			if ( $order_id ) {
				$wc_order = wc_get_order( $order_id );
				if ( is_object( $wc_order ) && function_exists( 'wcs_order_contains_subscription' ) && function_exists( 'wcs_is_subscription' ) ) {
					if ( wcs_order_contains_subscription( $wc_order, array( 'parent', 'renewal', 'resubscribe', 'switch' ) ) || wcs_is_subscription( $wc_order ) ) {
						$order_lines = array();
						foreach ( $wc_order->get_items() as $item ) {
							$order_lines[] = array(
								'description' => substr( $wc_order->get_shipping_method(), 0, 34 ),
								'notes'       => substr( __( 'Shipping', 'avarda-checkout-for-woocommerce' ), 0, 34 ),
								'amount'      => 0,
								'taxCode'     => 0,
								'taxAmount'   => 0,
								'quantity'    => 0,
							);
						}
						$request_args['items'] = $order_lines;

						$request_args['checkoutSetup']['completedNotificationUrl'] = add_query_arg(
							array(
								'aco-sub-payment-change' => $order_id,
							),
							get_home_url() . '/wc-api/ACO_WC_Notification'
						);
					}
				}
			}
		}

		return $request_args;
	}

	/**
	 * Checks if this is a ACO subscription payment method change.
	 *
	 * @return bool
	 */
	public function is_aco_subs_change_payment_method() {
		$key        = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$aco_action = filter_input( INPUT_GET, 'aco-action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! empty( $key ) && ( ! empty( $aco_action ) && 'change-subs-payment' === $aco_action ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Display thankyou notice when customer is redirected back to the
	 * subscription page in front-end after changing payment method.
	 *
	 * @return void
	 */
	public function display_thankyou_message_for_payment_method_change() {
		$aco_action = filter_input( INPUT_GET, 'aco-action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! empty( $aco_action ) && 'subs-payment-changed' === $aco_action ) {
			wc_add_notice( __( 'Thank you, your subscription payment method is now updated.', 'avarda-checkout-for-woocommerce' ), 'success' );
			aco_wc_unset_sessions();
		}
	}
}

new ACO_Subscription();
