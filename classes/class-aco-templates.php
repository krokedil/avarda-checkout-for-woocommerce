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
		add_action( 'aco_wc_after_order_review', array( $this, 'add_extra_checkout_fields' ), 10 );
		add_action( 'aco_wc_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		add_action( 'aco_wc_before_checkout_form', 'woocommerce_checkout_coupon_form', 20 );
		add_action( 'aco_wc_after_order_review', 'aco_wc_show_another_gateway_button', 20 );
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
					$avarda_checkout_template = AVARDA_CHECKOUT_PATH . '/templates/avarda-checkout.php';
				}

				// Avarda checkout page.
				if ( array_key_exists( 'aco', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'aco' === WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! isset( $_GET['confirm'] ) ) {
							$template = $avarda_checkout_template;
						}
					}

					// If chosen payment method does not exist and ACO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );

						if ( 'aco' === key( $available_gateways ) ) {
							if ( ! isset( $_GET['confirm'] ) ) {
								$template = $avarda_checkout_template;
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
			<input id="payment_method_aco" type="radio" class="input-radio" name="payment_method" value="aco" checked="checked" />		</div>
		<?php
	}

	/**
	 * Adds the extra checkout field div to the checkout page.
	 *
	 * @return void
	 */
	public function add_extra_checkout_fields() {
		?>
		<div id="aco-extra-checkout-fields">
		</div>
		<?php
	}
}

ACO_Templates::get_instance();
