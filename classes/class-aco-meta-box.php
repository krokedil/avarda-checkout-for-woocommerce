<?php
/**
 * Metabox class file.
 *
 * @package Avarda_Checkout_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Meta box class.
 */
class ACO_Meta_Box {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}

	/**
	 * Adds meta box to the side of a Avarda Checkout order.
	 *
	 * @param string $post_type The WordPress post type.
	 * @return void
	 */
	public function add_meta_box( $post_type ) {
		if ( 'shop_order' === $post_type ) {
			$order_id = get_the_ID();
			$order    = wc_get_order( $order_id );
			if ( 'aco' === $order->get_payment_method() ) {
				add_meta_box( 'aco_meta_box', __( 'Avarda', 'avarda-checkout-for-woocommerce' ), array( $this, 'meta_box_content' ), 'shop_order', 'side', 'core' );
			}
		}
	}


	/**
	 * Adds content for the meta box.
	 *
	 * @return void
	 */
	public function meta_box_content() {
		$avarda_settings = get_option( 'woocommerce_aco_settings', array() );
		$manage_orders   = $avarda_settings['order_management'] ?? '';
		$order_id        = get_the_ID();
		$order           = wc_get_order( $order_id );

		$payment_method                  = get_post_meta( $order_id, '_avarda_payment_method', true );
		$avarda_purchase_id              = get_post_meta( $order_id, '_transaction_id', true );
		$title_payment_method            = __( 'Payment method', 'avarda-checkout-for-woocommerce' );
		$title_avarda_purchase_id        = __( 'Avarda purchase id', 'avarda-checkout-for-woocommerce' );
		$title_avarda_order_status       = __( 'Avarda order status', 'avarda-checkout-for-woocommerce' );
		$title_avarda_order_total        = __( 'Avarda order total', 'avarda-checkout-for-woocommerce' );
		$title_avarda_customer_balance   = __( 'Avarda customer balance', 'avarda-checkout-for-woocommerce' );
		$title_customer_balance_mismatch = __( 'Customer balance mismatch', 'avarda-checkout-for-woocommerce' );

		if ( false === ( $avarda_order_status_from_transient = get_transient( "avarda_order_status_{$order_id}" ) ) ) {
			$avarda_order = ACO_WC()->api->request_get_payment( $avarda_purchase_id );

			if ( is_wp_error( $avarda_order ) ) {
				$avarda_order_status   = 'unknown';
				$avarda_order_total    = '';
				$avarda_order_currency = '';
				$order_total_mismatch  = '';
			} else {
				$avarda_order_status     = aco_get_payment_step( $avarda_order );
				$avarda_order_total      = $avarda_order['totalPrice'] ?? '';
				$avarda_customer_balance = $avarda_order['customerBalance'] ?? '';
				$avarda_order_currency   = $avarda_order['checkoutSite']['currencyCode'] ?? '';
				// Translators: Woo order total & Avarda order total.
				$order_total_mismatch = ! empty( $avarda_customer_balance ) && floatval( $avarda_customer_balance ) !== floatval( $avarda_order_total ) ? sprintf( __( '<i>Avarda Customer balance differs from Avarda order total (Customer balance: %1$s, Order total: %2$s)</i>', 'avarda-checkout-for-woocommerce' ), $avarda_customer_balance, $avarda_order_total ) : '';
				// Save received data to WP transient.
				/*
				avarda_save_order_data_to_transient(
					array(
						'order_id'     => $order_id,
						'status'       => $avarda_order_status,
						'total_amount' => $avarda_order_total,
						'currency'     => $avarda_order_currency,
					)
				);
				*/
			}
		} else {
			$avarda_order_status   = $avarda_order_status_from_transient['status'] ?? 'unknown';
			$avarda_order_total    = $avarda_order_status_from_transient['total_amount'] ?? '';
			$avarda_order_currency = $avarda_order_status_from_transient['currency'] ?? '';
			// Translators: Woo order total & Avarda order total.
			$order_total_mismatch = floatval( $order->get_total() ) !== floatval( $avarda_order_total ) ? sprintf( __( '<i>Order total differs between systems (WooCommerce: %1$s, Avarda: %2$s)</i>', 'avarda-checkout-for-woocommerce' ), $order->get_total(), $avarda_order_total ) : '';
		}

		$keys_for_meta_box = array(
			array(
				'title' => esc_html( $title_payment_method ),
				'value' => esc_html( $payment_method ),
			),
			array(
				'title' => esc_html( $title_avarda_purchase_id ),
				'value' => esc_html( $avarda_purchase_id ),
			),
			array(
				'title' => esc_html( $title_avarda_order_status ),
				'value' => esc_html( $avarda_order_status ),
			),
			array(
				'title' => esc_html( $title_avarda_order_total ),
				'value' => wp_kses_post( $avarda_order_total . ' ' . $avarda_order_currency ),
			),
			array(
				'title' => esc_html( $title_avarda_customer_balance ),
				'value' => wp_kses_post( $avarda_customer_balance . ' ' . $avarda_order_currency ),
			),
		);

		if ( ! empty( $order_total_mismatch ) && in_array( $avarda_order_status, array( 'Completed' ), true ) ) {
			$keys_for_meta_box[] = array(
				'title' => esc_html( $title_customer_balance_mismatch ),
				'value' => wp_kses_post( $order_total_mismatch ),
			);
		}
		$keys_for_meta_box = apply_filters( 'avarda_checkout_meta_box_keys', $keys_for_meta_box );
		include AVARDA_CHECKOUT_PATH . '/templates/avarda-checkout-meta-box.php';
	}



} new ACO_Meta_Box();
