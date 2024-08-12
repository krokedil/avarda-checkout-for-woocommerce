<?php
/**
 * Templates class for Avarda checkout.
 *
 * @package  Avarda_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * ACO_Templates class.
 */
class ACO_Templates {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

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
	 * Plugin actions.
	 */
	public function __construct() {
		// Override template if Avarda Checkout page.
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 999, 2 );

		// Template hooks.
		add_action( 'aco_wc_after_wrapper', array( $this, 'add_wc_form' ), 10 );
		add_action( 'aco_wc_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		add_action( 'aco_wc_after_order_review', 'aco_wc_add_extra_checkout_fields', 10 );
		add_action( 'aco_wc_after_order_review', 'aco_wc_show_another_gateway_button', 20 );

		// Body class modifications. For checkout layout setting.
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Override checkout form template if Avarda Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name ) {
		if ( is_checkout() ) {
			// Don't display ACO template if we have a cart that doesn't needs payment.
			if ( ! WC()->cart->needs_payment() ) {
				return $template;
			}

			// Avarda Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

				if ( locate_template( 'woocommerce/avarda-checkout.php' ) ) {
					$avarda_checkout_template = locate_template( 'woocommerce/avarda-checkout.php' );
				} else {
					$avarda_checkout_template = apply_filters( 'aco_locate_template', AVARDA_CHECKOUT_PATH . '/templates/avarda-checkout.php', $template_name );
				}

				// Avarda checkout page.
				if ( array_key_exists( 'aco', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'aco' === WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! isset( $_GET['confirm'] ) ) {
							$template = $avarda_checkout_template;
							ACO_Logger::log( "Loading checkout template for Avarda checkout because ACO is the chosen method: $template", WC_Log_Levels::DEBUG );
						}
					}

					// If chosen payment method does not exist and ACO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );

						if ( 'aco' === key( $available_gateways ) ) {
							if ( ! isset( $_GET['confirm'] ) ) {
								$template = $avarda_checkout_template;
								ACO_Logger::log( "Loading checkout template for Avarda checkout because no method is chosen and ACO is the first option: $template", WC_Log_Levels::DEBUG );
							}
						}
					}

					// If another gateway is saved in session, but has since become unavailable.
					if ( WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! array_key_exists( WC()->session->get( 'chosen_payment_method' ), $available_gateways ) ) {
							reset( $available_gateways );

							if ( 'aco' === key( $available_gateways ) ) {
								if ( ! isset( $_GET['confirm'] ) ) {
									$template = $avarda_checkout_template;
									ACO_Logger::log( "Loading checkout template for Avarda checkout the chosen method is no longer available, and ACO is the first option: $template", WC_Log_Levels::DEBUG );
								}
							}
						}
					}
				}
			}
		}

		return $template;
	}

	/**
	 * Adds the WC form and other fields to the checkout page.
	 *
	 * @return void
	 */
	public function add_wc_form() {
		?>
		<div aria-hidden="true" id="aco-wc-form" style="position:absolute; top:-99999px; left:-99999px;">
			<?php do_action( 'woocommerce_checkout_billing' ); ?>
			<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			<div id="aco-nonce-wrapper">
				<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
			</div>
			<input id="payment_method_aco" type="radio" class="input-radio" name="payment_method" value="aco" checked="checked" />
		</div>
		<?php
	}

	/**
	 * Add checkout page body class, depending on checkout page layout settings.
	 *
	 * @param array $body_class CSS classes used in body tag.
	 *
	 * @return array
	 */
	public function add_body_class( $body_class ) {
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {

			// Don't display Collector body classes if we have a cart that doesn't needs payment.
			if ( method_exists( WC()->cart, 'needs_payment' ) && ! WC()->cart->needs_payment() ) {
				return $body_class;
			}

			$settings = get_option( 'woocommerce_aco_settings' );

			// Logic for checkout layout. Checks old and new settings.
			if ( isset( $settings['checkout_layout'] ) ) {
				$checkout_layout = $settings['checkout_layout'];
			} elseif ( isset( $settings['two_column_checkout'] ) && 'yes' === $settings['two_column_checkout'] ) {
					$checkout_layout = 'two_column_left';
			} else {
				$checkout_layout = 'one_column';
			}

			$first_gateway = '';
			if ( WC()->session->get( 'chosen_payment_method' ) ) {
				$first_gateway = WC()->session->get( 'chosen_payment_method' );
			} else {
				$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
				reset( $available_payment_gateways );
				$first_gateway = key( $available_payment_gateways );
			}

			if ( 'aco' === $first_gateway && 'two_column_left' === $checkout_layout ) {
				$body_class[] = 'aco-selected';
				$body_class[] = 'aco-two-column-checkout-left';
			}
			if ( 'aco' === $first_gateway && 'two_column_left_sf' === $checkout_layout ) {
				$body_class[] = 'aco-selected';
				$body_class[] = 'aco-two-column-checkout-left-sf';
			}

			if ( 'aco' === $first_gateway && 'two_column_right' === $checkout_layout ) {
				$body_class[] = 'aco-selected';
				$body_class[] = 'aco-two-column-checkout-right';
			}

			if ( 'aco' === $first_gateway && 'one_column_checkout' === $checkout_layout ) {
				$body_class[] = 'aco-selected';
			}

			// If the setting for shipping in iframe is yes, then add the class.
			if ( 'aco' === $first_gateway && 'yes' === $settings['integrated_shipping_woocommerce'] ) {
				$body_class[] = 'aco-integrated-woo-shipping-display';
			}
		}
		return $body_class;
	}
}

ACO_Templates::get_instance();
