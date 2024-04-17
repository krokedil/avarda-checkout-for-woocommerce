<?php
/**
 * Class for managing actions during the checkout process.
 *
 * @package Avarda_Checkout_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing actions during the checkout process.
 */
class ACO_Checkout {
	/**
	 * Checkout flow.
	 *
	 * @var string
	 */
	private $checkout_flow;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$settings            = get_option( 'woocommerce_aco_settings' );
		$this->checkout_flow = $settings['checkout_flow'] ?? 'embedded';
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_avarda_order' ), 999999 );

		if ( 'embedded' === $this->checkout_flow ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'add_hidden_jwt_token_field' ), 30 );
		}

		add_action( 'woocommerce_after_shipping_rate', array( $this, 'print_extra_shipping_info' ), 10 );
	}

	/**
	 * Update the Avarda order after calculations from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_avarda_order() {

		if ( ! is_checkout() ) {
			return;
		}

		if ( 'redirect' === $this->checkout_flow ) {
			return;
		}

		if ( 'aco' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		// Only when its an actual AJAX request to update the order review (this is when update_checkout is triggered).
		$ajax = filter_input( INPUT_GET, 'wc-ajax', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'update_order_review' !== $ajax ) {
			return;
		}

		$avarda_purchase_id = aco_get_purchase_id_from_session();

		// Check if we have a avarda purchase id.
		if ( empty( $avarda_purchase_id ) ) {
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Avarda purchase ID is missing in update Avarda order function. Clearing Avarda session.' );
			return;
		}

		// Check if JWT token in checkout is the same as the one stored in session.
		$avarda_jwt_token = aco_get_jwt_token_from_session();
		$raw_post_data    = filter_input( INPUT_POST, 'post_data', FILTER_SANITIZE_URL );
		parse_str( $raw_post_data, $post_data );
		$checkout_jwt_token = $post_data['aco_jwt_token'] ?? '';

		if ( $avarda_jwt_token !== $checkout_jwt_token ) {
			aco_wc_unset_sessions();
			ACO_Logger::log( sprintf( 'JWT token used in checkout (%s) not the same as the one stored in WC session (%s). Clearing Avarda session.', $checkout_jwt_token, $avarda_jwt_token ) );
			wc_add_notice( 'Avarda JWT token issue. Please reload the page and try again.', 'error' );
			return;
		}

		// Check that the currency and locale is the same as earlier, otherwise create a new session.
		if ( get_woocommerce_currency() !== WC()->session->get( 'aco_currency' ) || ACO_WC()->checkout_setup->get_language() !== WC()->session->get( 'aco_language' ) ) {
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Currency or language changed in update Avarda function. Clearing Avarda session and reloading the cehckout page.' );
			WC()->session->reload_checkout = true;
			return;
		}

		// Check that no subscription product have been added or removed, otherwise create a new session.
		if ( isset( WC()->session ) && method_exists( WC()->session, 'get' ) ) {
			if ( WC()->session->get( 'aco_wc_cart_contains_subscription' ) !== aco_get_wc_cart_contains_subscription() ) {
				aco_wc_unset_sessions();
				ACO_Logger::log( 'Subscription product changed in update Avarda function. Clearing Avarda session and reloading the checkout page.' );
				if ( wp_doing_ajax() ) {
					WC()->session->reload_checkout = true;
				} else {
					wp_safe_redirect( wc_get_checkout_url() );
				}
				return;
			}
		}

		// Check if the cart hash has been changed since last update.
		$cart_hash  = WC()->cart->get_cart_hash();
		$saved_hash = WC()->session->get( 'aco_last_update_hash' );

		// If they are the same, return.
		if ( $cart_hash === $saved_hash ) {
			return;
		}

		// Get the Avarda order from Avarda.
		$avarda_order = ACO_WC()->session()->get_avarda_order();

		// Check if we got a wp_error.
		if ( is_wp_error( $avarda_order ) ) {
			// Unset sessions.
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Avarda GET request failed in update Avarda function. Clearing Avarda session.' );
			wc_add_notice( 'Avarda GET request failed.', 'error' );
			return;
		}

		// Check if order needs payment. If not, send refreshZeroAmount so checkout page is reloaded.
		if ( apply_filters( 'aco_check_if_needs_payment', true ) ) {
			if ( ! WC()->cart->needs_payment() ) {
				WC()->session->reload_checkout = true;
				return;
			}
		}

		// Get current status of Avarda session.
		$aco_step = aco_get_payment_step( $avarda_order );

		// check if session TimedOut.
		if ( 'TimedOut' === $aco_step ) {
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Avarda session TimedOut. Clearing Avarda session and reloading the checkout page.' );
			WC()->session->reload_checkout = true;
			return;
		}

		// Make sure that payment session step is ok.
		if ( ! in_array( $aco_step, aco_payment_steps_approved_for_update_request(), true ) ) {
			ACO_Logger::log( sprintf( 'Aborting Avarda update function since Avarda payment session %s in step %s.', $avarda_purchase_id, $aco_step ) );
			return;
		}

		// Update order.
		$avarda_order = ACO_WC()->api->request_update_payment( $avarda_purchase_id );

		// If the update failed - unset sessions and return error.
		if ( is_wp_error( $avarda_order ) ) {
			// Unset sessions.
			aco_wc_unset_sessions();
			ACO_Logger::log( 'Avarda update request failed in update Avarda function. Clearing Avarda session.' );
			wc_add_notice( 'Avarda update request failed.', 'error' );
			WC()->session->reload_checkout = true;
		}

		WC()->session->set( 'aco_last_update_hash', $cart_hash );
	}

	/**
	 * Adds a hidden aco_jwt_token checkout form field.
	 * Used to confirm that the token used for the Avarda Checkout widget in frontend is
	 * the same one currently saved in WC session aco_wc_payment_data.
	 * We do this to prevent issues if stores have session problems.
	 *
	 * @param array $fields WooCommerce checkout form fields.
	 * @return array
	 */
	public function add_hidden_jwt_token_field( $fields ) {
		$avarda_jwt_token = aco_get_jwt_token_from_session();

		$fields['billing']['aco_jwt_token'] = array(
			'type'    => 'hidden',
			'class'   => array( 'aco_jwt_token' ),
			'default' => $avarda_jwt_token,
		);

		return $fields;
	}

	/**
	 * Print the extra shipping info.
	 *
	 * @param WC_Shipping_Rate $rate The shipping rate.
	 *
	 * @return void
	 */
	public function print_extra_shipping_info( $rate ) {
		if ( false === strpos( $rate->get_id(), 'aco_shipping' ) ) { // Explicitly check for false, as strpos can return 0.
			return;
		}

		$selected_pickup_point = ACO_WC()->pickup_points->get_selected_pickup_point_from_rate( $rate );
		if ( empty( $selected_pickup_point ) ) {
			return;
		}

		$name        = $selected_pickup_point->get_name();
		$description = $selected_pickup_point->get_description();
		$address     = $selected_pickup_point->get_address();

		?>
		<div class="avarda-shipping-info">
			<small>
				<p>
				<?php if ( ! empty( $name ) ) : ?>
					<?php echo esc_html( $name ); ?>
				<?php elseif ( ! empty( $description ) ) : ?>
					<?php echo esc_html( $description ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $address ) ) : ?>
					<?php echo esc_html( ' - ' . $address->get_street() ); ?>
				<?php endif; ?>
			</p>
			</small>
		</div>
		<?php
	}
}
