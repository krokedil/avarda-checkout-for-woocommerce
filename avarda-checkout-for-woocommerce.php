<?php // phpcs:ignore
/**
 * Plugin Name:     Avarda Checkout for WooCommerce
 * Plugin URI:      http://krokedil.com/
 * Description:     Provides an Avarda Checkout gateway for WooCommerce.
 * Version:         1.10.0
 * Author:          Krokedil
 * Author URI:      http://krokedil.com/
 * Developer:       Krokedil
 * Developer URI:   http://krokedil.com/
 * Text Domain:     avarda-checkout-for-woocommerce
 * Domain Path:     /languages
 *
 * WC requires at least: 5.0.0
 * WC tested up to: 7.8.0
 *
 * Copyright:       © 2020-2023 Krokedil.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Avarda_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'AVARDA_CHECKOUT_VERSION', '1.10.0' );
define( 'AVARDA_CHECKOUT_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
define( 'AVARDA_CHECKOUT_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'AVARDA_CHECKOUT_LIVE_ENV', 'https://checkout-api.avarda.com' );
define( 'AVARDA_CHECKOUT_TEST_ENV', 'https://stage.checkout-api.avarda.com' );

if ( ! class_exists( 'Avarda_Checkout_For_WooCommerce' ) ) {

	/**
	 * Main class for the plugin.
	 */
	class Avarda_Checkout_For_WooCommerce {
		/**
		 * Checkout Setup helper class.
		 *
		 * @var ACO_Helper_Checkout_Setup $checkout_setup
		 */
		public $checkout_setup;

		/**
		 * Helper API class for Avarda API
		 *
		 * @var ACO_API $api
		 */
		public $api;

		/**
		 * Helper class for cart management.
		 *
		 * @var ACO_Helper_Cart $cart_items
		 */
		public $cart_items;

		/**
		 * Helper class for logging requests.
		 *
		 * @var ACO_Logger $logger
		 */
		public $logger;

		/**
		 * Helper class for order management.
		 *
		 * @var ACO_Helper_Order $order_items
		 */
		public $order_items;


		/**
		 * Helper class for order reservation.
		 *
		 * @var $order_management ACO_Order_Management
		 */
		public $order_management;
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
			$avarda_settings     = get_option( 'woocommerce_aco_settings' );
			$this->checkout_flow = isset( $avarda_settings['checkout_flow'] ) ? $avarda_settings['checkout_flow'] : 'embedded';
			add_action( 'plugins_loaded', array( $this, 'init' ) );
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
		 * Public unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Initiates the plugin.
		 *
		 * @return void
		 */
		public function init() {

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			load_plugin_textdomain( 'avarda-checkout-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

			$this->include_files();

			// Delete transient when aco settings is saved.
			add_action( 'woocommerce_update_options_checkout_aco', array( $this, 'aco_delete_transients' ) );

			// Set class variables.
			$this->api              = new ACO_API();
			$this->logger           = new ACO_Logger();
			$this->cart_items       = new ACO_Helper_Cart();
			$this->order_items      = new ACO_Helper_Order();
			$this->checkout_setup   = new ACO_Helper_Checkout_Setup();
			$this->customer         = new ACO_Helper_Customer();
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
		 * Includes the files for the plugin
		 *
		 * @return void
		 */
		public function include_files() {
			// Classes.

			if ( 'embedded' === $this->checkout_flow ) {
				include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-templates.php';
			}

			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-assets.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-ajax.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-api.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-gateway.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-logger.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-order-management.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-callbacks.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-checkout.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-confirmation.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-subscription.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-status.php';

			// Compatibility classes.
			include_once AVARDA_CHECKOUT_PATH . '/classes/compatibility/class-aco-compatibility-wc-carrier-agents.php';

			// Requests.
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/class-aco-request.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/post/class-aco-request-token.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/post/class-aco-request-initialize-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/post/class-aco-request-auth-recurring-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/get/class-aco-request-get-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/put/class-aco-request-update-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/put/class-aco-request-update-order-reference.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-activate-order.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-cancel-order.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-return-order.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-refund-order.php'; // For aco refund.

			// Request Helpers.
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/helpers/class-aco-helper-cart.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/helpers/class-aco-helper-order.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/helpers/class-aco-helper-create-refund-data.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/helpers/class-aco-helper-checkout-setup.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/helpers/class-aco-helper-customer.php';

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
		 * Checks the plugin version.
		 *
		 * @return void
		 */
		public function check_version() {
			require AVARDA_CHECKOUT_PATH . '/kernl-update-checker/kernl-update-checker.php';

			$update_checker = Puc_v4_Factory::buildUpdateChecker(
				'https://kernl.us/api/v1/updates/5eb54681c57f8861e5314e4e/',
				__FILE__,
				'avarda-checkout-for-woocommerce'
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
	function ACO_WC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return Avarda_Checkout_For_WooCommerce::get_instance();
	}
}
