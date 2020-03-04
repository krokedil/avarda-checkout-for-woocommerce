<?php // phpcs:ignore
/**
 * Plugin Name:     Avarda Checkout for WooCommerce
 * Plugin URI:      http://krokedil.com/
 * Description:     Provides a Avarda Checkout gateway for WooCommerce.
 * Version:         0.0.1
 * Author:          Krokedil
 * Author URI:      http://krokedil.com/
 * Developer:       Krokedil
 * Developer URI:   http://krokedil.com/
 * Text Domain:     avarda-checkout-for-woocommerce
 * Domain Path:     /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.9.2
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
define( 'AVARDA_CHECKOUT_VERSION', '0.0.1' );
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

			// Set class variables.
			$this->api    = new ACO_API();
			$this->logger = new ACO_Logger();

			do_action( 'aco_initiated' );
		}

		/**
		 * Includes the files for the plugin
		 *
		 * @return void
		 */
		public function include_files() {
			// Classes.
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-avarda-checkout-for-woocommerce-ajax.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-avarda-checkout-for-woocommerce-api.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-avarda-checkout-for-woocommerce-callbacks.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-avarda-checkout-for-woocommerce-gateway.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-avarda-checkout-for-woocommerce-logger.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-avarda-checkout-for-woocommerce-order-management.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-avarda-checkout-for-woocommerce-sessions.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-avarda-checkout-for-woocommerce-templates.php';

			// Requests.
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/class-avarda-checkout-for-woocommerce-request.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/class-avarda-checkout-for-woocommerce-request-token.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/class-avarda-checkout-for-woocommerce-request-initialize-payment.php';

			// Includes.
			include_once AVARDA_CHECKOUT_PATH . '/includes/avarda-checkout-for-woocommerce-functions.php';

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
