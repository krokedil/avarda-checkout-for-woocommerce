<?php
/**
 * Settings form fields for the gateway.
 *
 * @package Avarda_Checkout/Includes
 */

$settings = array(
	'enabled'                    => array(
		'title'   => __( 'Enable/Disable', 'avarda-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable ' . $this->method_title, 'avarda-checkout-for-woocommerce' ), // phpcs:ignore
		'default' => 'yes',
	),
	'title'                      => array(
		'title'       => __( 'Title', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'avarda-checkout-for-woocommerce' ),
		'default'     => __( $this->method_title, 'avarda-checkout-for-woocommerce' ), // phpcs:ignore
		'desc_tip'    => true,
	),
	'description'                => array(
		'title'       => __( 'Description', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'textarea',
		'default'     => __( 'Pay with Avarda via invoice, card and direct bank payments.', 'avarda-checkout-for-woocommerce' ),
		'desc_tip'    => true,
		'description' => __( 'This controls the description which the user sees during checkout.', 'avarda-checkout-for-woocommerce' ),
	),
	'select_another_method_text' => array(
		'title'       => __( 'Other payment method button text', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Customize the <em>Select another payment method</em> button text that is displayed in checkout if using other payment methods than Avarda Checkout. Leave blank to use the default (and translatable) text.', 'avarda-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'checkout_flow'              => array(
		'title'       => __( 'Checkout flow', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'select',
		'options'     => array(
			'embedded' => __( 'Embedded', 'avarda-checkout-for-woocommerce' ),
			'redirect' => __( 'Redirect', 'avarda-checkout-for-woocommerce' ),
		),
		'description' => __( 'Select how Avarda Checkout should be integrated in WooCommerce. <strong>Embedded</strong> – the checkout is embedded in the WooCommerce checkout page and partially replaces the checkout form. <strong>Redirect</strong> – the customer is redirected to WooCommerce order pay page where the Avarda Checkout is displayed.', 'avarda-checkout-for-woocommerce' ),
		'default'     => 'embedded',
		'desc_tip'    => false,
	),
	'testmode'                   => array(
		'title'   => __( 'Testmode', 'avarda-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Avarda Checkout testmode', 'avarda-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'order_management'           => array(
		'title'   => __( 'Enable Order Management', 'avarda-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Avarda order capture on WooCommerce order completion and Avarda order cancellation on WooCommerce order cancellation', 'avarda-checkout-for-woocommerce' ),
		'default' => 'yes',
	),
	'debug'                      => array(
		'title'       => __( 'Debug Log', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable logging', 'avarda-checkout-for-woocommerce' ),
		'default'     => 'no',
		'description' => sprintf( __( 'Log ' . $this->method_title . ' events in <code>%s</code>', 'avarda-checkout-for-woocommerce' ), wc_get_log_file_path( 'avarda_checkout' ) ), // phpcs:ignore
	),
	'payment_gateway_icon'       => array(
		'title'       => __( 'Payment gateway icon', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter an URL to the icon you want to display for the payment method. Use <i>default</i> to display the Avarda logo. Leave blank to not show an icon at all.', 'avarda-checkout-for-woocommerce' ),
		'default'     => 'default',
		'desc_tip'    => false,
	),
	'payment_gateway_icon_width' => array(
		'title'       => __( 'Payment gateway icon width', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'number',
		'description' => __( 'Specify the max width (in px) of the payment gateway icon.', 'avarda-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'two_column_checkout'        => array(
		'title'   => __( 'Two column checkout layout', 'avarda-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Two column checkout layout', 'avarda-checkout-for-woocommerce' ),
		'default' => 'yes',
	),
	// SE.
	'credentials_se'             => array(
		'title' => 'API Credentials Sweden',
		'type'  => 'title',
	),
	'merchant_id_se'             => array(
		'title'    => __( 'Client ID', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	'api_key_se'                 => array(
		'title'    => __( 'Client Secret', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	// NO.
	'credentials_no'             => array(
		'title' => 'API Credentials Norway',
		'type'  => 'title',
	),
	'merchant_id_no'             => array(
		'title'    => __( 'Client ID', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	'api_key_no'                 => array(
		'title'    => __( 'Client Secret', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	// DK.
	'credentials_dk'             => array(
		'title' => 'API Credentials Denmark',
		'type'  => 'title',
	),
	'merchant_id_dk'             => array(
		'title'    => __( 'Client ID', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	'api_key_dk'                 => array(
		'title'    => __( 'Client Secret', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	// FI.
	'credentials_fi'             => array(
		'title' => 'API Credentials Finland',
		'type'  => 'title',
	),
	'merchant_id_fi'             => array(
		'title'    => __( 'Client ID', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	'api_key_fi'                 => array(
		'title'    => __( 'Client Secret', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
);
return apply_filters( 'aco_settings', $settings );
