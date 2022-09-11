<?php
/**
 * Main assets file.
 *
 * @package Avarda_Checkout/Classes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACO_Assets class.
 */
class ACO_Assets {

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	public $settings;

	/**
	 * ACO_Assets constructor.
	 */
	public function __construct() {
		$avarda_settings     = get_option( 'woocommerce_aco_settings' );
		$this->checkout_flow = isset( $avarda_settings['checkout_flow'] ) ? $avarda_settings['checkout_flow'] : 'embedded';

		// Load scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'aco_localize_and_enqueue_checkout_script', array( $this, 'localize_and_enqueue_checkout_script' ) );

	}

	/**
	 * Loads the needed scripts for Avarda_Checkout.
	 */
	public function load_scripts() {
		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( 'redirect' === $this->checkout_flow && is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {

			// Checkout script.
			wp_register_script(
				'aco_wc',
				AVARDA_CHECKOUT_URL . '/assets/js/aco_checkout.js',
				array( 'jquery' ),
				AVARDA_CHECKOUT_VERSION,
				true
			);

			wp_register_style(
				'aco',
				AVARDA_CHECKOUT_URL . '/assets/css/aco_style.css',
				array(),
				AVARDA_CHECKOUT_VERSION
			);
			wp_enqueue_style( 'aco' );
		}
	}


	/**
	 * Loads the needed scripts for Avarda_Checkout.
	 */
	public function localize_and_enqueue_checkout_script() {

		$key      = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
		$order_id = ! empty( $key ) ? wc_get_order_id_by_order_key( $key ) : 0;

		$this->aco_maybe_initialize_payment( $order_id );

		$is_aco_action    = 'no';
		$confirmation_url = '';

		// Confirmation url for order pay.
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$is_aco_action    = 'yes';
			$order            = wc_get_order( $order_id );
			$confirmation_url = add_query_arg(
				array(
					'aco_confirm'     => 'yes',
					'aco_purchase_id' => get_post_meta( $order_id, '_wc_avarda_purchase_id', true ),
				),
				$order->get_checkout_order_received_url()
			);
		}

		// Confirmation url for subscription payment change.
		if ( isset( $_GET['aco-action'], $_GET['key'] ) && 'change-subs-payment' === $_GET['aco-action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$is_aco_action    = 'yes';
			$order            = wc_get_order( $order_id );
			$confirmation_url = add_query_arg(
				array(
					'aco-action'   => 'subs-payment-changed',
					'aco-order-id' => $order_id,
				),
				$order->get_view_order_url()
			);
		}

		if ( empty( $order_id ) ) {
			// If we don't have an order - get JWT from session.
			$avarda_payment_data = WC()->session->get( 'aco_wc_payment_data' );
			$avarda_jwt_token    = ( is_array( $avarda_payment_data ) && isset( $avarda_payment_data['jwt'] ) ) ? $avarda_payment_data['jwt'] : '';
			$redirect_url        = wc_get_checkout_url();
		} else {
			// We have a WC order - get info from that.
			$avarda_jwt_token = get_post_meta( $order_id, '_wc_avarda_jwt', true );
			// Get current url (pay page).
			$redirect_url = $order->get_checkout_payment_url( true );
		}

		$standard_woo_checkout_fields = apply_filters( 'aco_ignored_checkout_fields', array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_phone', 'billing_email', 'billing_state', 'billing_country', 'billing_company', 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_company', 'terms', 'terms-field', '_wp_http_referer', 'ship_to_different_address', 'calc_shipping_country', 'calc_shipping_state', 'calc_shipping_postcode' ) );
		$avarda_settings              = get_option( 'woocommerce_aco_settings' );
		$aco_test_mode                = ( isset( $avarda_settings['testmode'] ) && 'yes' === $avarda_settings['testmode'] ) ? true : false;
		$aco_two_column_checkout      = ( isset( $avarda_settings['two_column_checkout'] ) && 'yes' === $avarda_settings['two_column_checkout'] ) ? array( 'two_column' => true ) : array( 'two_column' => false );
		$styles                       = new stdClass(); // empty object as default value.
		$aco_custom_css_styles        = apply_filters( 'aco_custom_css_styles', $styles );

		$params = array(
			'ajax_url'                             => admin_url( 'admin-ajax.php' ),
			'select_another_method_text'           => __( 'Select another payment method', 'avarda-checkout-for-woocommerce' ),
			'standard_woo_checkout_fields'         => $standard_woo_checkout_fields,
			'address_changed_url'                  => WC_AJAX::get_endpoint( 'aco_wc_address_changed' ),
			'address_changed_nonce'                => wp_create_nonce( 'aco_wc_address_changed' ),
			'update_payment_url'                   => WC_AJAX::get_endpoint( 'aco_wc_update_checkout' ),
			'update_payment_nonce'                 => wp_create_nonce( 'aco_wc_update_checkout' ),
			'change_payment_method_url'            => WC_AJAX::get_endpoint( 'aco_wc_change_payment_method' ),
			'change_payment_method_nonce'          => wp_create_nonce( 'aco_wc_change_payment_method' ),
			'get_avarda_payment_url'               => WC_AJAX::get_endpoint( 'aco_wc_get_avarda_payment' ),
			'get_avarda_payment_nonce'             => wp_create_nonce( 'aco_wc_get_avarda_payment' ),
			'iframe_shipping_address_change_url'   => WC_AJAX::get_endpoint( 'aco_wc_iframe_shipping_address_change' ),
			'iframe_shipping_address_change_nonce' => wp_create_nonce( 'aco_wc_iframe_shipping_address_change' ),
			'log_to_file_url'                      => WC_AJAX::get_endpoint( 'aco_wc_log_js' ),
			'log_to_file_nonce'                    => wp_create_nonce( 'aco_wc_log_js' ),
			'submit_order'                         => WC_AJAX::get_endpoint( 'checkout' ),
			'required_fields_text'                 => __( 'Please fill in all required checkout fields.', 'avarda-checkout-for-woocommerce' ),
			'aco_jwt_token'                        => $avarda_jwt_token,
			'aco_redirect_url'                     => $redirect_url,
			'aco_test_mode'                        => $aco_test_mode,
			'aco_checkout_layout'                  => $aco_two_column_checkout,
			'aco_checkout_style'                   => $aco_custom_css_styles,
			'is_aco_action'                        => $is_aco_action,
			'aco_order_id'                         => $order_id,
			'confirmation_url'                     => $confirmation_url,

		);

		wp_localize_script(
			'aco_wc',
			'aco_wc_params',
			$params
		);
		wp_enqueue_script( 'aco_wc' );
	}

	/**
	 * Maybe initialize payment.
	 *
	 * @param int $order_id The WooCommerce Order id.
	 *
	 * @return void
	 */
	public function aco_maybe_initialize_payment( $order_id = null ) {

		if ( ! empty( $order_id ) ) {
			// Creates a session and store it to order if we don't have a previous one or if it has expired.
			$avarda_jwt_expired_time = get_post_meta( $order_id, '_wc_avarda_expiredUtc', true );
			if ( empty( $avarda_jwt_expired_time ) || strtotime( $avarda_jwt_expired_time ) < time() ) {
				delete_post_meta( $order_id, '_wc_avarda_purchase_id' );
				delete_post_meta( $order_id, '_wc_avarda_jwt' );
				delete_post_meta( $order_id, '_wc_avarda_expiredUtc' );
				aco_wc_initialize_or_update_order_from_wc_order( $order_id );
			}
		} else {
			// Creates jwt token if we do not have session var set with jwt token or if it have expired.
			$avarda_payment_data     = WC()->session->get( 'aco_wc_payment_data' );
			$avarda_jwt_expired_time = ( is_array( $avarda_payment_data ) && isset( $avarda_payment_data['expiredUtc'] ) ) ? $avarda_payment_data['expiredUtc'] : '';
			$token                   = ( time() < strtotime( $avarda_jwt_expired_time ) ) ? 'session' : 'new_token_required';
			if ( 'new_token_required' === $token || null === $avarda_payment_data['jwt'] || get_woocommerce_currency() !== WC()->session->get( 'aco_currency' ) || ACO_WC()->checkout_setup->get_language() !== WC()->session->get( 'aco_language' ) ) {
				aco_wc_initialize_payment();
			}
		}

	}
}
new ACO_Assets();