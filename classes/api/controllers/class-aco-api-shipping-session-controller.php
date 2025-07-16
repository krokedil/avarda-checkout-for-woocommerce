<?php
/**
 * The shipping session controller class for the Avarda Checkout API.
 *
 * @package Avarda_Checkout/Classes/API/Controllers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ACO_API_Shipping_Session_Controller
 */
class ACO_API_Shipping_Session_Controller extends ACO_API_Controller_Base {
	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path = 'shipping';
	/**
	 * Register the routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Register the create session path.
		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'create-session' ),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_session' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);

		// Register the update session path.
		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'update-session/(?P<id>[a-zA-Z0-9-]+)' ),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_session' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);

		// Register the complete session path.
		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'complete-session/(?P<id>[a-zA-Z0-9-]+)' ),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'complete_session' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);

		// Register the get session path.
		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'get-session/(?P<id>[a-zA-Z0-9-]+)' ),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_session' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);
	}

	/**
	 * Get the customer session from the customer unique id.
	 *
	 * @param string $customer_id The customer unique id.
	 *
	 * @return array
	 */
	private function get_customer_session( $customer_id ) {
		$this->setup_customer_session( $customer_id );

		$shipping                = WC()->session->get( 'shipping_for_package_0' ) ?? array();
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' ) ?? array();

		return array(
			'shipping'               => $shipping,
			'chosen_shipping_method' => is_array( $chosen_shipping_methods ) ? reset( $chosen_shipping_methods ) : '',
		);
	}

	/**
	 * Setup the session data for the customer.
	 *
	 * @param string $customer_id The customer unique id.
	 *
	 * @return void
	 */
	private function setup_customer_session( $customer_id ) {
		if ( null !== WC()->session && method_exists( WC()->session, 'get_session' ) ) {
			$session = WC()->session->get_session( $customer_id );
		} else {
			WC()->session = new WC_Session_Handler();
			$session      = WC()->session->get_session( $customer_id );
		}

		// Loop the session and set it as the current user session data.
		foreach ( $session as $key => $value ) {
			$value = maybe_unserialize( $value );
			WC()->session->set( $key, $value );
		}
	}

	/**
	 * Create or update a shipping session.
	 *
	 * @param array $avarda_order The order from Avarda.
	 *
	 * @return ACO_Shipping_Session_Model|null
	 */
	private function create_or_update_session( $avarda_order ) {
		if ( empty( $avarda_order ) ) {
			return null;
		}

		$purchase_id         = $avarda_order['purchaseId'] ?? '';
		$attachments         = json_decode( $avarda_order['extraIdentifiers']['attachment'], true ) ?? array();
		$shipping_attachment = $attachments['shipping'] ?? array();

		if ( empty( $purchase_id ) || empty( $attachments ) || empty( $shipping_attachment ) || ! isset( $shipping_attachment['customerId'] ) ) {
			return null;
		}

		$wc_session = $this->get_customer_session( $attachments['shipping']['customerId'] );
		$session    = ACO_Shipping_Session_Model::from_shipping_rates( $wc_session['shipping']['rates'] ?? array(), $wc_session['chosen_shipping_method'], $purchase_id, $attachments['shipping']['customerId'] );

		return $session;
	}

	/**
	 * Get the shipping session for the customer.
	 *
	 * @param string $customer_id The customer unique id.
	 * @param string $purchase_id The purchase id.
	 *
	 * @return ACO_Shipping_Session_Model|null
	 */
	private function get_shipping_session_for_customer( $customer_id, $purchase_id ) {
		$this->setup_customer_session( $customer_id );

		// Get the shipping rates from the session.
		$shipping_rates          = WC()->session->get( 'shipping_for_package_0' ) ?? array();
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' ) ?? array();

		$session = ACO_Shipping_Session_Model::from_shipping_rates( $shipping_rates['rates'], is_array( $chosen_shipping_methods ) ? reset( $chosen_shipping_methods ) : '', $purchase_id );

		return $session;
	}

	/**
	 * Create a shipping session.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return void
	 */
	public function create_session( $request ) {
		try {
			$body = $request->get_json_params();

			// Get the customerId from the attachment data in the extraIdentifiers.
			$purchase_id = $body['purchaseId'];
			$attachments = json_decode( $body['extraIdentifiers']['attachment'], true );
			$customer_id = $attachments['shipping']['customerId'];

			$session = $this->get_shipping_session_for_customer( $customer_id, $purchase_id );

			if ( ! $session ) {
				$this->send_response( ACO_Shipping_Session_Model::get_fallback_shipping_session( $purchase_id ), 201 );
			}

			$this->send_response( $session, 201 );
		} catch ( Exception $e ) {
			$body        = $request->get_json_params();
			$purchase_id = $body['purchaseId'];
			$this->send_response( ACO_Shipping_Session_Model::get_fallback_shipping_session( $purchase_id ), 201 );
		}
	}

	/**
	 * Update a shipping session.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return void
	 */
	public function update_session( $request ) {
		try {
			$body = $request->get_json_params();

			// Get the customerId from the attachment data in the extraIdentifiers.
			$purchase_id = $body['purchaseId'];
			$attachments = json_decode( $body['extraIdentifiers']['attachment'], true );
			$customer_id = $attachments['shipping']['customerId'];

			$session = $this->get_shipping_session_for_customer( $customer_id, $purchase_id );

			if ( ! $session ) {
				$this->send_response( new WP_Error( 400, 'Bad request' ) );
			}

			$this->send_response( $session, 200 );
		} catch ( Exception $e ) {
			$this->send_response( new WP_Error( 500, 'Server error' ) );
		}
	}

	/**
	 * Complete a shipping session.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return void
	 */
	public function complete_session( $request ) {
		$purchase_id = $request->get_param( 'id' );

		// Get the WooCommerce order for the purchase id.
		$order = aco_get_order_by_purchase_id( $purchase_id );

		if ( ! $order ) {
			$this->send_response( new WP_Error( 404, 'Not found' ) );
		}

		$session = ACO_Shipping_Session_Model::completed_session( $purchase_id );

		$this->send_response( $session );
	}

	/**
	 * Get a shipping session.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return void
	 */
	public function get_session( $request ) {
		try {
			// Get the purchase id from the request url.
			$purchase_id = $request->get_param( 'id' );
			// Get the avarda order.
			$avarda_order = ACO_WC()->api->request_get_payment( $purchase_id );

			if ( is_wp_error( $avarda_order ) ) {
				$this->send_response( new WP_Error( 404, 'Not found' ) );
			}

			$purchase_id = $avarda_order['purchaseId'] ?? '';
			$attachments = json_decode( $avarda_order['extraIdentifiers']['attachment'], true ) ?? array();
			$customer_id = $attachments['shipping']['customerId'] ?? '';

			// Get the shipping session from the order.
			$session = $this->get_shipping_session_for_customer( $customer_id, $purchase_id );

			if ( ! $session ) {
				$this->send_response( new WP_Error( 404, 'Not found' ) );
			}

			$this->send_response( $session );
		} catch ( Exception $e ) {
			$this->send_response( new WP_Error( 500, 'Server error' ) );
		}
	}

	/**
	 * Process the customer address data and set the correct customer data.
	 *
	 * @param array $value The customer data.
	 * @param array $address The address data.
	 *
	 * @return array
	 */
	private static function process_customer_address( $value, $address ) {
		// Set the billing address data.
		foreach ( $address['billing'] as $address_key => $address_value ) {
			// If the key is date_of_birth, continue.
			if ( 'date_of_birth' === $address_key ) {
				continue;
			}

			// Change some keys to match WooCommerce names.
			if ( 'address1' === $address_key ) {
				$address_key = 'address_1';
			} elseif ( 'address2' === $address_key ) {
				$address_key = 'address_2';
			} elseif ( 'zip' === $address_key ) {
				$address_key = 'postcode';
			}

			$value[ $address_key ] = $address_value;
		}

		// Set the shipping address data.
		foreach ( $address['shipping'] as $address_key => $address_value ) {
			// If the key is date_of_birth, continue.
			if ( 'date_of_birth' === $address_key ) {
				continue;
			}

			// If the key is address1, change to address_1.
			if ( 'address1' === $address_key ) {
				$address_key = 'address_1';
			} elseif ( 'address2' === $address_key ) {
				$address_key = 'address_2';
			} elseif ( 'zip' === $address_key ) {
				$address_key = 'postcode';
			}

			$value[ 'shipping_' . $address_key ] = $address_value;
		}

		return $value;
	}
}
