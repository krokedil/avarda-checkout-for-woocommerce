<?php
/**
 * Constant declarations for PHPStan static analysis only.
 *
 * Not loaded at runtime — the real values are defined in avarda-checkout-for-woocommerce.php.
 * These declarations exist so PHPStan knows the constants exist when analyzing files
 * that are pulled in via dynamic include_once and therefore can't be traced statically.
 *
 * @package Avarda_Checkout
 *
 * @phpcs:disable
 */

define( 'AVARDA_CHECKOUT_VERSION', '0.0.0' );
define( 'AVARDA_CHECKOUT_URL', 'https://example.com' );
define( 'AVARDA_CHECKOUT_PATH', __DIR__ );
define( 'AVARDA_CHECKOUT_LIVE_ENV', 'https://example.com' );
define( 'AVARDA_CHECKOUT_TEST_ENV', 'https://example.com' );

// Missing constants that are set in WordPress but not in their stubs.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
