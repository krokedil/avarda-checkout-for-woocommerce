<?php // phpcs:ignore
/**
 * Plugin Name:     Avarda Checkout for WooCommerce
 * Plugin URI:      http://krokedil.com/
 * Description:     Provides an Avarda Checkout gateway for WooCommerce.
 * Version:         0.1.9
 * Author:          Krokedil
 * Author URI:      http://krokedil.com/
 * Developer:       Krokedil
 * Developer URI:   http://krokedil.com/
 * Text Domain:     avarda-checkout-for-woocommerce
 * Domain Path:     /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 4.3.0
 *
 * Copyright:       © 2016-2020 Krokedil.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Avarda_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'AVARDA_CHECKOUT_VERSION', '0.1.9' );
define( 'AVARDA_CHECKOUT_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
define( 'AVARDA_CHECKOUT_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'AVARDA_CHECKOUT_LIVE_ENV', 'https://avdonl-p-checkout.avarda.org' );
define( 'AVARDA_CHECKOUT_TEST_ENV', 'https://avdonl-s-checkout.avarda.org' );

if ( ! class_exists( 'Avarda_Checkout_For_WooCommerce' ) ) {

	/**
	 * Main class for the plugin.
	 */
	class Avarda_Checkout_For_WooCommerce {
		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		protected static $instance;

		/**
		 * Class constructor.
		 */
		public function __construct() {
			// Initiate the plugin.
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			add_action( 'wp_head', array( $this, 'redirect_to_thankyou' ) );
			add_action( 'plugins_loaded', array( $this, 'check_version' ) );
		}

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self::$instance The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}
		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Initiates the plugin.
		 *
		 * @return void
		 */
		public function init() {
			load_plugin_textdomain( 'avarda-checkout-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

			$this->include_files();

			// Load scripts.
			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
			// Delete transient when aco settings is saved.
			add_action( 'woocommerce_update_options_checkout_aco', array( $this, 'aco_delete_transients' ) );

			add_action( 'aco_before_load_scripts', array( $this, 'aco_maybe_initialize_payment' ) );

			// Set class variables.
			$this->api              = new ACO_API();
			$this->logger           = new ACO_Logger();
			$this->cart_items       = new ACO_Helper_Cart();
			$this->order_items      = new ACO_Helper_Order();
			$this->order_management = new ACO_Order_Management();

			do_action( 'aco_initiated' );
		}


		/**
		 * Delete transients when ACO settings is saved.
		 *
		 * @return void
		 */
		public function aco_delete_transients() {
			// Need to clear transients if credentials is changed.
			delete_transient( 'aco_auth_token' );
			delete_transient( 'aco_currency' );
		}

		/**
		 * Mayne initialize payment.
		 *
		 * @return void
		 */
		public function aco_maybe_initialize_payment() {
			// Creates jwt token if we do not have session var set with jwt token.
			if ( null === WC()->session->get( 'aco_wc_jwt' ) ) {
				aco_wc_initialize_payment();
			}
		}

		/**
		 * Includes the files for the plugin
		 *
		 * @return void
		 */
		public function include_files() {
			// Classes.
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-ajax.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-api.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-gateway.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-logger.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-order-management.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-templates.php';

			// Requests.
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/class-aco-request.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/post/class-aco-request-token.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/post/class-aco-request-initialize-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/get/class-aco-request-get-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/put/class-aco-request-update-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/put/class-aco-request-update-order-reference.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-activate-order.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-cancel-order.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-return-order.php';
			// include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-refund-order.php'; For aco refund.

			// Request Helpers.
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/helpers/class-aco-helper-cart.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/helpers/class-aco-helper-order.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/helpers/class-aco-helper-create-refund-data.php';

			// Includes.
			include_once AVARDA_CHECKOUT_PATH . '/includes/aco-functions.php';

		}

		/**
		 * Adds plugin action links
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();
			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'avarda-checkout-for-woocommerce' ) . '</a>',
				'<a href="http://krokedil.se/">' . __( 'Support', 'avarda-checkout-for-woocommerce' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$section_slug = 'aco';
			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Loads the needed scripts for Avarda_Checkout.
		 */
		public function load_scripts() {
			if ( is_checkout() ) {
					do_action( 'aco_before_load_scripts' );

					// Checkout script.
					wp_register_script(
						'aco_wc',
						AVARDA_CHECKOUT_URL . '/assets/js/aco_checkout.js',
						array( 'jquery' ),
						AVARDA_CHECKOUT_VERSION,
						true
					);

					$standard_woo_checkout_fields = array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_phone', 'billing_email', 'billing_state', 'billing_country', 'billing_company', 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_company', 'terms', 'terms-field', 'account_username', 'account_password', '_wp_http_referer' );
					$avarda_settings              = get_option( 'woocommerce_aco_settings' );
					$aco_test_mode                = ( isset( $avarda_settings['testmode'] ) && 'yes' === $avarda_settings['testmode'] ) ? true : false;
					$aco_two_column_checkout      = ( isset( $avarda_settings['two_column_checkout'] ) && 'yes' === $avarda_settings['two_column_checkout'] ) ? array( 'two_column' => true ) : array( 'two_column' => false );
					$styles                       = new stdClass(); // empty object as default value.
					$aco_custom_css_styles        = apply_filters( 'aco_custom_css_styles', $styles );

					$params = array(
						'ajax_url'                     => admin_url( 'admin-ajax.php' ),
						'select_another_method_text'   => __( 'Select another payment method', 'avarda-checkout-for-woocommerce' ),
						'standard_woo_checkout_fields' => $standard_woo_checkout_fields,
						'address_changed_url'          => WC_AJAX::get_endpoint( 'aco_wc_address_changed' ),
						'address_changed_nonce'        => wp_create_nonce( 'aco_wc_address_changed' ),
						'update_payment_url'           => WC_AJAX::get_endpoint( 'aco_wc_update_checkout' ),
						'update_payment_nonce'         => wp_create_nonce( 'aco_wc_update_checkout' ),
						'change_payment_method_url'    => WC_AJAX::get_endpoint( 'aco_wc_change_payment_method' ),
						'change_payment_method_nonce'  => wp_create_nonce( 'aco_wc_change_payment_method' ),
						'get_avarda_payment_url'       => WC_AJAX::get_endpoint( 'aco_wc_get_avarda_payment' ),
						'get_avarda_payment_nonce'     => wp_create_nonce( 'aco_wc_get_avarda_payment' ),
						'iframe_shipping_address_change_url' => WC_AJAX::get_endpoint( 'aco_wc_iframe_shipping_address_change' ),
						'iframe_shipping_address_change_nonce' => wp_create_nonce( 'aco_wc_iframe_shipping_address_change' ),
						'required_fields_text'         => __( 'Please fill in all required checkout fields.', 'avarda-checkout-for-woocommerce' ),
						'aco_jwt_token'                => WC()->session->get( 'aco_wc_jwt' ),
						'aco_redirect_url'             => wc_get_checkout_url(),
						'aco_test_mode'                => $aco_test_mode,
						'aco_checkout_layout'          => $aco_two_column_checkout,
						'aco_checkout_style'           => $aco_custom_css_styles,
					);

					wp_localize_script(
						'aco_wc',
						'aco_wc_params',
						$params
					);
					wp_enqueue_script( 'aco_wc' );

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
		 * Redirects the customer to the proper thank you page.
		 *
		 * @return void
		 */
		public function redirect_to_thankyou() {
			if ( isset( $_GET['aco_confirm'] ) && isset( $_GET['aco_purchase_id'] ) ) {
				$avarda_purchase_id = isset( $_GET['aco_purchase_id'] ) ? sanitize_text_field( wp_unslash( $_GET['aco_purchase_id'] ) ) : '';

				// Find relevant order in Woo.
				$query_args = array(
					'fields'      => 'ids',
					'post_type'   => wc_get_order_types(),
					'post_status' => array_keys( wc_get_order_statuses() ),
					'meta_key'    => '_wc_avarda_purchase_id',
					'meta_value'  => $avarda_purchase_id,
				);

				$orders = get_posts( $query_args );
				if ( ! $orders ) {
					// If no order is found, bail. @TODO Add a fallback order creation here?
					wc_add_notice( __( 'Something went wrong in the checkout process. Please contact the store.', 'error' ) );
					return;
				}
				$order_id = $orders[0];
				$order    = wc_get_order( $order_id );
				// Populate wc order address.
				$this->populate_wc_order( $order, $avarda_purchase_id );

				// Redirect and exit.
				header( 'Location:' . $order->get_checkout_order_received_url() );
				exit;
			}
		}

		/**
		 * Populates the wc order address.
		 *
		 * @param WC_Order $order The WC Order.
		 * @param string   $avarda_purchase_id The Avarda purchase id.
		 * @return void
		 */
		public function populate_wc_order( $order, $avarda_purchase_id ) {
			// Get the Avarda order from Avarda.
			$avarda_order = ACO_WC()->api->request_get_payment( $avarda_purchase_id );
			$order_id     = $order->get_id();
			// update_post_meta( $order_id, '_avarda_payment_amount', sanitize_text_field( $avarda_order['price'] ) ); For aco refund.

			$user_inputs       = array();
			$invoicing_address = array();
			$delivery_address  = array();
			if ( 'B2C' === $avarda_order['mode'] ) {
				$user_inputs       = $avarda_order['b2C']['userInputs'];
				$invoicing_address = $avarda_order['b2C']['invoicingAddress'];
				$delivery_address  = $avarda_order['b2C']['deliveryAddress'];
			} elseif ( 'B2B' === $avarda_order['mode'] ) {
				$user_inputs       = $avarda_order['b2B']['userInputs'];
				$invoicing_address = $avarda_order['b2B']['invoicingAddress'];
				$delivery_address  = $avarda_order['b2B']['deliveryAddress'];
			}

			$shipping_data = array(
				'first_name' => isset( $delivery_address['firstName'] ) ? $delivery_address['firstName'] : $invoicing_address['firstName'],
				'last_name'  => isset( $delivery_address['lastName'] ) ? $delivery_address['lastName'] : $invoicing_address['lastName'],
				'country'    => isset( $delivery_address['country'] ) ? $delivery_address['country'] : $invoicing_address['country'],
				'address1'   => isset( $delivery_address['address1'] ) ? $delivery_address['address1'] : $invoicing_address['address1'],
				'address2'   => isset( $delivery_address['address2'] ) ? $delivery_address['address2'] : $invoicing_address['address2'],
				'city'       => isset( $delivery_address['city'] ) ? $delivery_address['city'] : $invoicing_address['city'],
				'zip'        => isset( $delivery_address['zip'] ) ? $delivery_address['zip'] : $invoicing_address['zip'],
			);

			// Set Avarda payment method title.
			$method_title = $this->aco_get_payment_method_title( $order_id, $avarda_order );
			$order->set_payment_method_title( $method_title );

			// First name.
			$order->set_billing_first_name( sanitize_text_field( $invoicing_address['firstName'] ) );
			$order->set_shipping_first_name( sanitize_text_field( $shipping_data['first_name'] ) );
			// Last name.
			$order->set_billing_last_name( sanitize_text_field( $invoicing_address['lastName'] ) );
			$order->set_shipping_last_name( sanitize_text_field( $shipping_data['last_name'] ) );
			// Country.
			$order->set_billing_country( strtoupper( sanitize_text_field( $invoicing_address['country'] ) ) );
			$order->set_shipping_country( strtoupper( sanitize_text_field( $shipping_data['country'] ) ) );
			// Street address1.
			$order->set_billing_address_1( sanitize_text_field( $invoicing_address['address1'] ) );
			$order->set_shipping_address_1( sanitize_text_field( $shipping_data['address1'] ) );
			// Street address2.
			$order->set_billing_address_2( sanitize_text_field( $invoicing_address['address2'] ) );
			$order->set_shipping_address_2( sanitize_text_field( $shipping_data['address2'] ) );
			// City.
			$order->set_billing_city( sanitize_text_field( $invoicing_address['city'] ) );
			$order->set_shipping_city( sanitize_text_field( $shipping_data['city'] ) );
			// Postcode.
			$order->set_billing_postcode( sanitize_text_field( $invoicing_address['zip'] ) );
			$order->set_shipping_postcode( sanitize_text_field( $shipping_data['zip'] ) );
			// Phone.
			$order->set_billing_phone( sanitize_text_field( $user_inputs['phone'] ) );
			// Email.
			$order->set_billing_email( sanitize_text_field( $user_inputs['email'] ) );

			// Save order.
			$order->save();

		}

		/**
		 * Get Avarda Checkout order payment method title.
		 *
		 * @param string $order_id The WooCommerce order id.
		 * @param array  $avarda_order The Avarda order.
		 * @return string $method_title The payment method title.
		 */
		public function aco_get_payment_method_title( $order_id, $avarda_order ) {
			$aco_payment_method = '';
			if ( isset( $avarda_order['paymentMethods']['selectedPayment']['type'] ) ) {
				$aco_payment_method = sanitize_text_field( $avarda_order['paymentMethods']['selectedPayment']['type'] );
				update_post_meta( $order_id, '_avarda_payment_method', $aco_payment_method );
			}

			switch ( $aco_payment_method ) {
				case 'Invoice':
					$method_title = __( 'Avarda Invoice', 'avarda-checkout-for-woocommerce' );
					break;
				case 'Loan':
					$method_title = __( 'Avarda Loan', 'avarda-checkout-for-woocommerce' );
					break;
				case 'Card':
					$method_title = __( 'Avarda Card', 'avarda-checkout-for-woocommerce' );
					break;
				case 'DirectPayment':
					$method_title = __( 'Avarda Direct Payment', 'avarda-checkout-for-woocommerce' );
					break;
				case 'PartPayment':
					$method_title = __( 'Avarda Part Payment', 'avarda-checkout-for-woocommerce' );
					break;
				case 'Swish':
					$method_title = __( 'Avarda Swish', 'avarda-checkout-for-woocommerce' );
					break;
				case 'HighAmountLoan':
					$method_title = __( 'Avarda High Amount Loan', 'avarda-checkout-for-woocommerce' );
					break;
				case 'PayPal':
					$method_title = __( 'Avarda PayPal', 'avarda-checkout-for-woocommerce' );
					break;
				case 'PayOnDelivery':
					$method_title = __( 'Avarda Pay On Delivery', 'avarda-checkout-for-woocommerce' );
					break;
				case 'B2BInvoice':
					$method_title = __( 'Avarda B2B Invoice', 'avarda-checkout-for-woocommerce' );
					break;
				case 'DirectInvoice':
					$method_title = __( 'Avarda Direct Invoice', 'avarda-checkout-for-woocommerce' );
					break;
				case 'Masterpass':
					$method_title = __( 'Avarda Masterpass', 'avarda-checkout-for-woocommerce' );
					break;
				case 'MobilePay':
					$method_title = __( 'Avarda MobilePay', 'avarda-checkout-for-woocommerce' );
					break;
				case 'Vipps':
					$method_title = __( 'Avarda Vipps', 'avarda-checkout-for-woocommerce' );
					break;
				case 'ZeroAmount':
					$method_title = __( 'Avarda Zero Amount', 'avarda-checkout-for-woocommerce' );
					break;
				default:
					$method_title = __( 'Avarda Checkout', 'avarda-checkout-for-woocommerce' );
			}
			return $method_title;
		}

		/**
		 * Checks the plugin version.
		 *
		 * @return void
		 */
		public function check_version() {
			require AVARDA_CHECKOUT_PATH . '/includes/plugin_update_check.php';
			$KernlUpdater = new PluginUpdateChecker_2_0( // phpcs:ignore
				'https://kernl.us/api/v1/updates/5eb54681c57f8861e5314e4e/',
				__FILE__,
				'avarda-checkout-for-woocommerce',
				1
			);
		}
	}
	Avarda_Checkout_For_WooCommerce::get_instance();

	/**
	 * Main instance Avarda_Checkout_For_WooCommerce.
	 *
	 * Returns the main instance of Avarda_Checkout_For_WooCommerce.
	 *
	 * @return Avarda_Checkout_For_WooCommerce
	 */
	function ACO_WC() { // phpcs:ignore
		return Avarda_Checkout_For_WooCommerce::get_instance();
	}
}
