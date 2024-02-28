<?php
/**
 * Metabox class file.
 *
 * @package ACO/Classes
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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
	}

	/**
	 * Add the shipping metabox to the edit order page.
	 *
	 * @param string            $post_type The post type to add the metabox to.
	 * @param \WP_Post|WC_Order $post_or_order_object      The WordPress post or WooCommerce order, depending on HPOS is active or not.
	 *
	 * @return void
	 */
	public function add_meta_box( $post_type, $post_or_order_object ) {

		if ( 'shop_order' !== $post_type ) {
			return;
		}

		$order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( 'aco' !== $order->get_payment_method() ) {
			return;
		}

		add_meta_box(
			'aco_meta_box',
			__( 'Avarda', 'avarda-checkout-for-woocommerce' ),
			array( $this, 'render_aco_metabox' ),
			'shop_order',
			'side',
			'core'
		);
	}


	/**
	 * Render the shipping metabox.
	 *
	 * @param \WP_Post|WC_Order $post_or_order_object      The WordPress post or WooCommerce order, depending on HPOS is active or not.
	 * @param array             $args  The metabox arguments.
	 *
	 * @return void
	 */
	public function render_aco_metabox( $post_or_order_object, $args ) {

		$order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$purchase_id = $order->get_meta( '_wc_avarda_purchase_id', true );
		if ( empty( $purchase_id ) ) {
			$purchase_id = $order->get_transaction_id();
		}
		// Get the Avarda order.
		$avarda_order     = ACO_WC()->api->request_get_payment( $purchase_id );
		$display_aco_json = filter_input( INPUT_GET, 'display-aco-json', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		$payment_method                  = $order->get_meta( '_avarda_payment_method', true );
		$title_payment_method            = __( 'Payment method', 'avarda-checkout-for-woocommerce' );
		$title_avarda_purchase_id        = __( 'Purchase id', 'avarda-checkout-for-woocommerce' );
		$title_avarda_order_status       = __( 'Avarda order status', 'avarda-checkout-for-woocommerce' );
		$title_avarda_order_total        = __( 'Avarda order total', 'avarda-checkout-for-woocommerce' );
		$title_order_total_mismatch      = __( 'Order total mismatch', 'avarda-checkout-for-woocommerce' );
		$title_avarda_customer_balance   = __( 'Avarda customer balance', 'avarda-checkout-for-woocommerce' );
		$title_customer_balance_mismatch = __( 'Customer balance mismatch', 'avarda-checkout-for-woocommerce' );
		$title_order_synchronization     = __( 'Order synchronization', 'avarda-checkout-for-woocommerce' );
		$aco_order_sync_status           = ! empty( $order->get_meta( '_wc_avarda_order_sync_status', true ) ) ? $order->get_meta( '_wc_avarda_order_sync_status', true ) : 'enabled';

		if ( is_wp_error( $avarda_order ) ) {
			$avarda_order_status     = 'unknown';
			$avarda_order_total      = '';
			$avarda_order_currency   = '';
			$order_total_mismatch    = '';
			$avarda_customer_balance = '';
		} else {
			$avarda_order_status     = aco_get_payment_step( $avarda_order );
			$avarda_order_total      = $avarda_order['totalPrice'] ?? '';
			$avarda_order_currency   = $avarda_order['checkoutSite']['currencyCode'] ?? '';
			$avarda_customer_balance = $avarda_order['customerBalance'] ?? '';
		}

		$keys_for_meta_box = array(
			array(
				'title' => esc_html( $title_payment_method ),
				'value' => esc_html( $payment_method ),
			),
			array(
				'title' => esc_html( $title_avarda_purchase_id ),
				'value' => esc_html( $purchase_id ),
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
			array(
				'title' => '',
				'value' => '<hr>',
			),
		);

		$keys_for_meta_box[] = array(
			'before' => '<div class="aco_order_sync--toggle">',
			'title'  => esc_html( $title_order_synchronization ) . wc_help_tip( __( 'Disable this to turn off the automatic synchronization with the Avarda Merchant Portal. When disabled, any changes in either system have to be done manually.', 'avarda-checkout-for-woocommerce' ) ),
			'value'  => '<span data-order-sync-status="' . $aco_order_sync_status . '" class="woocommerce-input-toggle woocommerce-input-toggle--' . $aco_order_sync_status . '"></span>',
			'after'  => '</div>',
		);

		// Avarda order Json.
		$keys_for_meta_box[] = array(
			'title' => '',
			'value' => '<span class="button open-avarda-order-data dashicons dashicons-editor-code" title="' . __( 'View Avarda order in JSON format', 'avarda-checkout-for-woocommerce' ) . '"></span>' .
			'<div class="avarda-order-data" style="display:none;">' .
						'<div class="avarda-order-data-modal-content">' .
						'<h3>' . __( 'Payment fetched from Avarda', 'avarda-checkout-for-woocommerce' ) . '</h3>' .
						'<span class="close-avarda-order-data dashicons dashicons-dismiss"></span>' .
						'<pre>' . wp_json_encode( $avarda_order, JSON_PRETTY_PRINT ) . '</pre>' .
						'</div></div>',
		);

		$keys_for_meta_box = apply_filters( 'avarda_checkout_meta_box_keys', $keys_for_meta_box );
		include AVARDA_CHECKOUT_PATH . '/templates/avarda-meta-box.php';
	}
} new ACO_Meta_Box();
