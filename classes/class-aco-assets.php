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
	 * The checkout flow.
	 *
	 * @var string
	 */
	public $checkout_flow;

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

		add_action( 'aco_wc_before_checkout_form', array( $this, 'localize_and_enqueue_checkout_script' ) );
		add_action( 'aco_wc_before_order_receipt', array( $this, 'localize_and_enqueue_checkout_script' ) );

		// Admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
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

			// Checkout utility (change to Avarda payment method in checkout).
			wp_register_script(
				'aco_utility',
				AVARDA_CHECKOUT_URL . '/assets/js/aco_utility.js',
				array( 'jquery' ),
				AVARDA_CHECKOUT_VERSION,
				true
			);

			$params = array(
				'change_payment_method_url'   => WC_AJAX::get_endpoint( 'aco_wc_change_payment_method' ),
				'change_payment_method_nonce' => wp_create_nonce( 'aco_wc_change_payment_method' ),
			);

			wp_localize_script(
				'aco_utility',
				'aco_utility_params',
				$params
			);
			wp_enqueue_script( 'aco_utility' );

			// Checkout script.
			wp_register_script(
				'aco_wc',
				AVARDA_CHECKOUT_URL . '/assets/js/aco_checkout.js',
				array( 'jquery' ),
				AVARDA_CHECKOUT_VERSION,
				true
			);

			wp_register_script(
				'aco_shipping_widget',
				AVARDA_CHECKOUT_URL . '/assets/js/aco_shipping_widget.js',
				array( 'jquery' ),
				AVARDA_CHECKOUT_VERSION,
				true
			);

			// Checkout style.
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

		$key      = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_id = ! empty( $key ) ? wc_get_order_id_by_order_key( $key ) : 0;

		$this->aco_maybe_initialize_payment( $order_id );

		// Instantiate order after we have run aco_maybe_initialize_payment so we
		// know that we have updated meta data. Needed if this is a redirect flow purchase.
		$order = wc_get_order( $order_id );

		$is_aco_action    = 'no';
		$confirmation_url = '';

		// Confirmation url for order pay.
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$is_aco_action    = 'yes';
			$confirmation_url = add_query_arg(
				array(
					'aco_confirm'     => 'yes',
					'aco_purchase_id' => $order->get_meta( '_wc_avarda_purchase_id', true ),
				),
				$order->get_checkout_order_received_url()
			);
		}

		// Confirmation url for subscription payment change.
		if ( isset( $_GET['aco-action'], $_GET['key'] ) && 'change-subs-payment' === $_GET['aco-action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$is_aco_action    = 'yes';
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
			$avarda_jwt_token = $order->get_meta( '_wc_avarda_jwt', true );
			// Get current url (pay page).
			$redirect_url = $order->get_checkout_payment_url( true );
		}

		$standard_woo_checkout_fields    = apply_filters( 'aco_ignored_checkout_fields', array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_phone', 'billing_email', 'billing_state', 'billing_country', 'billing_company', 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_company', 'terms', 'terms-field', '_wp_http_referer', 'ship_to_different_address', 'calc_shipping_country', 'calc_shipping_state', 'calc_shipping_postcode' ) );
		$avarda_settings                 = get_option( 'woocommerce_aco_settings' );
		$aco_test_mode                   = ( isset( $avarda_settings['testmode'] ) && 'yes' === $avarda_settings['testmode'] ) ? true : false;
		$aco_two_column_checkout         = ( isset( $avarda_settings['two_column_checkout'] ) && 'yes' === $avarda_settings['two_column_checkout'] ) ? array( 'two_column' => true ) : array( 'two_column' => false );
		$styles                          = new stdClass(); // empty object as default value.
		$aco_custom_css_styles           = apply_filters( 'aco_custom_css_styles', $styles );
		$integrated_shipping_woocommerce = ACO_WC()->checkout->is_integrated_wc_shipping_enabled();

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
			'iframe_shipping_option_change_url'    => WC_AJAX::get_endpoint( 'aco_iframe_shipping_option_change' ),
			'iframe_shipping_option_change_nonce'  => wp_create_nonce( 'aco_iframe_shipping_option_change' ),
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
			'integrated_shipping_woocommerce'      => $integrated_shipping_woocommerce ? 'yes' : 'no',
		);

		wp_localize_script(
			'aco_wc',
			'aco_wc_params',
			$params
		);
		wp_enqueue_script( 'aco_wc' );

		$shipping_widget = array(
			'integrated_shipping_woocommerce' => $integrated_shipping_woocommerce ? 'yes' : 'no',
			'cart_needs_shipping'             => WC()->cart->needs_shipping(),
			'spinner_text'                    => apply_filters( 'aco_shipping_widget_spinner_text', __( 'Waiting for shipping options...', 'avarda-checkout-for-woocommerce' ) ),
			'price_format'                    => array(
				'format' => get_woocommerce_price_format(),
				'symbol' => get_woocommerce_currency_symbol(),
			),
			'ajax'                            => array(
				'get_shipping_options' => array(
					'nonce' => wp_create_nonce( 'aco_shipping_widget_get_options' ),
					'url'   => WC_AJAX::get_endpoint( 'aco_shipping_widget_get_options' ),
				),
			),
		);
		wp_localize_script(
			'aco_shipping_widget',
			'aco_wc_shipping_params',
			$shipping_widget
		);
		wp_enqueue_script( 'aco_shipping_widget' );
	}

	/**
	 * Maybe initialize payment.
	 *
	 * @param int $order_id The WooCommerce Order id.
	 *
	 * @return void
	 */
	public function aco_maybe_initialize_payment( $order_id = null ) {
		// Get the order if we have an order id.
		$order = $order_id ? wc_get_order( $order_id ) : null;

		// Get the Avarda payment.
		$avarda_payment = ACO_WC()->session()->get_avarda_payment( $order );

		// If we don't have any errors, and the payment is returned as an array, just continue.
		if ( ! is_wp_error( $avarda_payment ) && false !== $avarda_payment ) {
			return;
		}

		// If we did not get an order, we need to initialize a new payment.
		if ( null !== $order ) {
			// Delete old meta data.
			aco_delete_avarda_meta_data_from_order( $order );

			// Initialize the payment.
			$avarda_payment = ACO_WC()->api->request_initialize_payment( $order_id );
			aco_wc_save_avarda_session_data_to_order( $order_id, $avarda_payment );
		} else {
			$avarda_payment = aco_wc_initialize_payment();
		}

		if ( is_wp_error( $avarda_payment ) ) {
			// If we got an error, log it and return.
			ACO_Logger::log( 'Error when initializing Avarda payment: ' . $avarda_payment->get_error_message(), WC_Log_Levels::ERROR );
			return;
		}
	}

	/**
	 * Force a new session with Avarda Checkout.
	 *
	 * @param WC_Order|null|false $order The WooCommerce Order.
	 *
	 * @return void
	 */
	public function force_new_session( $order = null ) {
		$is_order = is_a( $order, 'WC_Order' );

		if ( $is_order ) {
			aco_delete_avarda_meta_data_from_order( $order );
			$avarda_order = aco_wc_initialize_or_update_order_from_wc_order( $order->get_id() );
		} else {
			$avarda_order = aco_wc_initialize_payment();
		}

		ACO_WC()->session()->set_avarda_payment( $avarda_order );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook The current admin page.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		if ( in_array( $hook, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) || in_array( $screen_id, array( 'shop_order' ), true ) ) {
			$this->enqueue_admin_shop_order_assets();
		}

		if ( 'woocommerce_page_wc-settings' === $hook && isset( $_GET['section'] ) && 'aco' === $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->enqueue_settings_assets();
		}
	}

	/**
	 * Enqueue the settings page assets.
	 *
	 * @return void
	 */
	public function enqueue_settings_assets() {
		wp_register_script( 'aco_settings_page', AVARDA_CHECKOUT_URL . '/assets/js/aco_settings_page.js', array( 'jquery' ), AVARDA_CHECKOUT_VERSION, true );
		wp_enqueue_script( 'aco_settings_page' );
	}

	/**
	 * Enqueue the admin shop order page assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_shop_order_assets() {
		$order_id = ! empty( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) ) ? filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) : get_the_ID();

		wp_register_script( 'aco_admin_js', AVARDA_CHECKOUT_URL . '/assets/js/aco-admin.js', array( 'jquery', 'jquery-blockui' ), AVARDA_CHECKOUT_VERSION, true );

		$params = array(
			'aco_order_sync_toggle_nonce' => wp_create_nonce( 'aco_wc_order_sync_toggle' ),
			'order_id'                    => $order_id,
		);

		wp_localize_script(
			'aco_admin_js',
			'aco_admin_params',
			$params
		);
		wp_enqueue_script( 'aco_admin_js' );

		wp_register_style( 'aco_admin_css', AVARDA_CHECKOUT_URL . '/assets/css/aco-admin.css', array(), AVARDA_CHECKOUT_VERSION );
		wp_enqueue_style( 'aco_admin_css' );
	}
}
new ACO_Assets();
