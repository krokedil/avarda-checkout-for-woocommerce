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

			add_action( 'aco_before_load_scripts', array( $this, 'aco_maybe_initialize_payment' ) );

			// Set class variables.
			$this->api        = new ACO_API();
			$this->logger     = new ACO_Logger();
			$this->cart_items = new ACO_Helper_Cart();

			do_action( 'aco_initiated' );
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
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-callbacks.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-gateway.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-logger.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-order-management.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-sessions.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/class-aco-templates.php';

			// Requests.
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/class-aco-request.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/post/class-aco-request-token.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/post/class-aco-request-initialize-payment.php';
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/checkout/get/class-aco-request-get-payment.php';

			// Request Helpers.
			include_once AVARDA_CHECKOUT_PATH . '/classes/requests/helpers/class-aco-helper-cart.php';

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

					$standard_woo_checkout_fields = array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_phone', 'billing_email', 'billing_state', 'billing_country', 'billing_company', 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_company', 'terms', 'account_username', 'account_password' );

					$params = array(
						'ajax_url'                     => admin_url( 'admin-ajax.php' ),
						'select_another_method_text'   => __( 'Select another payment method', 'avarda-checkout-for-woocommerce' ),
						'standard_woo_checkout_fields' => $standard_woo_checkout_fields,
						'address_changed_url'          => WC_AJAX::get_endpoint( 'aco_wc_address_changed' ),
						'address_changed_nonce'        => wp_create_nonce( 'aco_wc_address_changed' ),
						'update_order_url'             => WC_AJAX::get_endpoint( 'aco_wc_update_checkout' ),
						'update_order_nonce'           => wp_create_nonce( 'aco_wc_update_checkout' ),
						'change_payment_method_url'    => WC_AJAX::get_endpoint( 'aco_wc_change_payment_method' ),
						'change_payment_method_nonce'  => wp_create_nonce( 'aco_wc_change_payment_method' ),
						'get_avarda_payment_url'       => WC_AJAX::get_endpoint( 'aco_wc_get_avarda_payment' ),
						'get_avarda_payment_nonce'     => wp_create_nonce( 'aco_wc_get_avarda_payment' ),
						'required_fields_text'         => __( 'Please fill in all required checkout fields.', 'avarda-checkout-for-woocommerce' ),
						'aco_jwt_token'                => WC()->session->get( 'aco_wc_jwt' ),
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
