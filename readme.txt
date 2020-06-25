=== Avarda Checkout for WooCommerce ===
Contributors: krokedil, niklashogefjord
Tags: ecommerce, e-commerce, woocommerce, avarda
Requires at least: 4.5
Tested up to: 5.4.2
WC requires at least: 3.5.0
WC tested up to: 4.2.2
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: trunk

Avarda Checkout for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via Avarda.


== DESCRIPTION ==
Avarda Checkout is an e-commerce checkout solution that gives you more than the individual purchase. The checkout is built to provide more repeat customers and an increased average order value.

To get started with Avarda Checkout you need to [sign up](https://www.avarda.com/se/foretag/) for an account.

More information on how to get started can be found in the [plugin documentation](https://docs.krokedil.com/collection/337-avarda-checkout).


== INSTALLATION	 ==
1. Download and unzip the latest release zip file.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your Avarda Checkout settings.
6. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/collection/337-avarda-checkout).


== CHANGELOG ==
= 2020.06.25        - version 0.1.7 =
* Fix               - Load correct javascript checkout file depending on if plugin is in testmode or not.
* Fix               - Delete request token transient when credentials is changed.

= 2020.06.17        - version 0.1.6 =
* Tweak             - Initialize payment request is using the Avarda legacy endpoint.
* Enhancement       - Trigger event when Avarda Checkout form is loaded on checkout page.

= 2020.06.03        - version 0.1.5 =
* Enhancement       - Support for Swedish, Finnish and English language in the checkout.

= 2020.06.01        - version 0.1.4 = 
* Enhancement       - Prevent doing update request when payment has state Completed or TimedOut.

= 2020.05.28        - version 0.1.3 =
* Fix               - Fix for extra checkout fields not showing up on checkout page.

= 2020.05.13        - version 0.1.2 =
* Feature           - Added support for customizing the Avarda Checkout through a filter.
* Fix               - Fix for endless spinning wheel at checkout if something went wrong in Avarda.
* Tweak             - Change the way plugin is fetching data from payment status request.
* Enhancement       - Initialize new payment at checkout if current payment timed out.

= 2020.05.08        - version 0.1.1 =
* Tweak             - Updated readme file.

= 2020.05.08        - version 0.1.0 =
* Initial release.