<?php
/**
 * Register the Avarda Checkout API controllers.
 *
 * @package Avarda_Checkout/Classes/API/Controllers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ACO_API_Registry
 */
class ACO_API_Registry {
	/**
	 * The list of controllers.
	 *
	 * @var ACO_API_Controller_Base[]
	 */
	protected $controllers = array();

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_controllers' ) );
	}

	/**
	 * Initialize the API controllers and models.
	 *
	 * @return void
	 */
	public function init() {
		// Include the controllers.
		include_once __DIR__ . '/controllers/class-aco-api-controller-base.php';
		include_once __DIR__ . '/controllers/class-aco-api-shipping-session-controller.php';

		// Include the models.
		include_once __DIR__ . '/models/shipping/class-aco-shipping-response-model.php';
		include_once __DIR__ . '/models/shipping/class-aco-shipping-error-model.php';
		include_once __DIR__ . '/models/shipping/class-aco-shipping-option-model.php';
		include_once __DIR__ . '/models/shipping/class-aco-shipping-session-model.php';

		// Register the controllers.
		$this->register_controller( new ACO_API_Shipping_Session_Controller() );
	}

	/**
	 * Register the controllers.
	 *
	 * @return void
	 */
	public function register_controllers() {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Register a controller.
	 *
	 * @param ACO_API_Controller_Base $controller The controller to register.
	 *
	 * @return void
	 */
	public function register_controller( $controller ) {
		$this->controllers[ get_class( $controller ) ] = $controller;
	}

	/**
	 * Get the auth token for the API.
	 * Uses the WordPress wp_hash function to generate a hash.
	 *
	 * @return string
	 */
	public static function get_auth_token() {
		return apply_filters( 'aco_rest_api_token', wp_hash( 'avarda-checkout-for-woocommerce' ) );
	}
}
