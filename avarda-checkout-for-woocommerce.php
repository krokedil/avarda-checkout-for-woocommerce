<?php // phpcs:ignore
/**
 * Plugin Name:     Avarda Checkout for WooCommerce
 * Plugin URI:      http://krokedil.com/
 * Description:     Provides an Avarda Checkout gateway for WooCommerce.
 * Version:         1.16.4
 * Author:          Krokedil
 * Author URI:      http://krokedil.com/
 * Developer:       Krokedil
 * Developer URI:   http://krokedil.com/
 * Text Domain:     avarda-checkout-for-woocommerce
 * Domain Path:     /languages
 *
 * WC requires at least: 5.6.0
 * WC tested up to: 9.7.0
 *
 * Copyright:       © 2020-2025 Krokedil.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Avarda_Checkout
 */

use KrokedilAvardaDeps\Krokedil\Shipping\PickupPoints;
use KrokedilAvardaDeps\Krokedil\WooCommerce\KrokedilWooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'AVARDA_CHECKOUT_VERSION', '1.16.4' );
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
		 * Pickup points class instance.
		 *
		 * @var PickupPoints $pickup_points
		 */
		public $pickup_points;

		/**
		 * Checkout class instance.
		 *
		 * @var ACO_Checkout $checkout
		 */
		public $checkout;

		/**
		 * Cart page class instance.
		 *
		 * @var ACO_Cart_Page $cart_page
		 */
		public $cart_page;

		/**
		 * Customer helper class instance.
		 *
		 * @var ACO_Helper_Customer $customer
		 */
		public $customer;

		/**
		 * The checkout flow.
		 *
		 * @var string $checkout_flow
		 */
		public $checkout_flow;

		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		protected static $instance;

		/**
		 * The WooCommerce package from Krokedil.
		 *
		 * @var KrokedilWooCommerce
		 */
		public $krokedil = null;

		/**
		 * Rest api registry instance.
		 *
		 * @var ACO_API_Registry
		 */
		protected $rest_api;

		/**
		 * Webshipper compatibility class instance.
		 *
		 * @var ACO_Compatibility_Webshipper
		 */
		protected $webshipper;

		/**
		 * Shipping instance settings class instance.
		 *
		 * @var ACO_Shipping_Instance_Settings
		 */
		protected $shipping_instance_settings;

		/**
		 * Class constructor.
		 */
		public function __construct() {
			// Initiate the plugin.
			$avarda_settings     = get_option( 'woocommerce_aco_settings' );
			$this->checkout_flow = isset( $avarda_settings['checkout_flow'] ) ? $avarda_settings['checkout_flow'] : 'embedded';
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			add_action( 'plugins_loaded', array( $this, 'check_version' ) );
			add_action( 'init', array( $this, 'load_textdomain' ) );
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
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope', 'avarda-checkout-for-woocommerce' ), '1.0' );
		}
		/**
		 * Public unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope', 'avarda-checkout-for-woocommerce' ), '1.0' );
		}

		/**
		 * Load the plugin textdomain.
		 *
		 * @return void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'avarda-checkout-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
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

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

			if ( ! $this->init_composer() ) {
				return;
			}

			$this->include_files();

			// Delete transient when aco settings is saved.
			add_action( 'woocommerce_update_options_checkout_aco', array( $this, 'delete_all_transients' ) );

			// Register the shipping method with WooCommerce.
			add_filter( 'woocommerce_shipping_methods', ACO_Shipping::class . '::register' );

			// Set class variables.
			$this->checkout                   = new ACO_Checkout();
			$this->pickup_points              = new PickupPoints();
			$this->api                        = new ACO_API();
			$this->logger                     = new ACO_Logger();
			$this->cart_items                 = new ACO_Helper_Cart();
			$this->order_items                = new ACO_Helper_Order();
			$this->checkout_setup             = new ACO_Helper_Checkout_Setup();
			$this->customer                   = new ACO_Helper_Customer();
			$this->order_management           = new ACO_Order_Management();
			$this->cart_page                  = new ACO_Cart_Page();
			$this->webshipper                 = new ACO_Compatibility_Webshipper();
			$this->krokedil                   = new KrokedilWooCommerce(
				array(
					'slug'         => 'aco',
					'price_format' => 'major',
				)
			);
			$this->shipping_instance_settings = new ACO_Shipping_Instance_Settings();
			$this->rest_api                   = new ACO_API_Registry();
			$this->rest_api->init();

			// Create initial instance of the session class.
			ACO_Session::get_instance();

			do_action( 'aco_initiated' );

			add_filter( 'init', array( $this, 'migrate_settings' ) );
		}

		/**
		 * Initialize composers autoloader.
		 *
		 * @return bool|mixed
		 */
		public function init_composer() {
			$autoloader = AVARDA_CHECKOUT_PATH . '/dependencies/autoload.php';

			if ( ! is_readable( $autoloader ) ) {
				self::missing_autoloader();
				return false;
			}

			$autoloader_result = require $autoloader;
			if ( ! $autoloader_result ) {
				return false;
			}

			return $autoloader_result;
		}

		/**
		 * Checks if the autoloader is missing and displays an admin notice.
		 *
		 * @return void
		 */
		protected static function missing_autoloader() {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore
					esc_html__( 'Your installation of Avarda Checkout is not complete. If you installed this plugin directly from Github please refer to the README.DEV.md file in the plugin.', 'avarda-checkout-for-woocommerce' )
				);
			}
			add_action(
				'admin_notices',
				function () {
					?>
					<div class="notice notice-error">
						<p>
							<?php echo esc_html__( 'Your installation of Avarda Checkout is not complete. If you installed this plugin directly from Github please refer to the README.DEV.md file in the plugin.', 'avarda-checkout-for-woocommerce' ); ?>
						</p>
					</div>
					<?php
				}
			);
		}


		/**
		 * Delete all possible transients when saving the settings.
		 *
		 * @return void
		 */
		public function delete_all_transients() {
			$currencies = array( 'SEK', 'NOK', 'DKK', 'EUR' );

			foreach ( $currencies as $currency ) {
				$name = aco_get_transient_name( $currency );
				delete_transient( $name );
			}
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
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-modules-helper.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-order-management.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-callbacks.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-cart-page.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-checkout.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-confirmation.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-subscription.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-status.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-meta-box.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-shipping-instance-settings.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-shipping.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-session.php';

			// Compatibility classes.
			include_once AVARDA_CHECKOUT_PATH . '/classes/compatibility/class-aco-compatibility-wc-carrier-agents.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/compatibility/class-aco-compatibility-webshipper.php';

			// Requests.
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/class-aco-request.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/post/class-aco-request-token.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/post/class-aco-request-initialize-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/post/class-aco-request-auth-recurring-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/get/class-aco-request-get-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/put/class-aco-request-update-extra-identifiers.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/put/class-aco-request-update-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/put/class-aco-request-update-order-reference.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-activate-order.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-cancel-order.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-return-order.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/order-management/post/class-aco-request-refund-order.php'; // For aco refund.

			// REST API.
			include_once AVARDA_CHECKOUT_PATH . '/classes/api/class-aco-api-registry.php';

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

		/**
		 * Maybe migrate settings when getting the settings from the database.
		 *
		 * @return void
		 */
		public function migrate_settings() {
			$settings = get_option( 'woocommerce_aco_settings', array() );

			// Bail early if no settings are found.
			if ( empty( $settings ) ) {
				return;
			}

			// Migrate shipping settings from old values to new.
			$integrated_shipping    = $settings['integrated_shipping'] ?? '';
			$integrated_shipping_wc = $settings['integrated_shipping_woocommerce'] ?? '';

			// If nothing needs to be migrated, just return early.
			if ( in_array( $integrated_shipping, array( 'avarda', 'woocommerce', '' ), true ) && in_array( $integrated_shipping_wc, array( 'no', '' ), true ) ) {
				return;
			}

			if ( 'yes' === $integrated_shipping ) {
				$settings['integrated_shipping'] = 'avarda';
			} elseif ( 'no' === $integrated_shipping ) {
				$settings['integrated_shipping'] = '';
			}

			if ( 'yes' === $integrated_shipping_wc ) {
				$settings['integrated_shipping'] = 'woocommerce';
			}

			// Unset the woocommerce specific setting.
			unset( $settings['integrated_shipping_woocommerce'] );

			// Update the settings in the database to ensure they are migrated fully.
			do_action( 'woocommerce_update_option', array( 'id' => 'woocommerce_aco_settings' ) );
			update_option( 'woocommerce_aco_settings', apply_filters( 'woocommerce_settings_api_sanitized_fields_aco', $settings ), 'yes' );
		}

		/**
		 * Return the instance of the Avarda session class.
		 *
		 * @return ACO_Session
		 */
		public function session() {
			return ACO_Session::get_instance();
		}
	}

	// Declare HPOS compatibility.
	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
	);

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
