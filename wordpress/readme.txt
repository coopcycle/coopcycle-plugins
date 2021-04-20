=== Plugin Name ===
Contributors: alexmex
Donate link: https://opencollective.com/coopcycle
Tags: ecommerce, woocommerce, shipping
Requires at least: 5.0
Tested up to: 5.4.2
Requires PHP: 7.0
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

CoopCycle plugin for WordPress.

== Description ==

This plugin will add a new shipping method to WooCommerce.
Once installed and configured, it communicates with a CoopCycle server via an HTTP API.

== Installation ==

1. Upload `coopcycle.zip` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the plugin settings page, and configure it with the credentials you have been given
 3.1 Base URL : URL of your Coopcycle instance
 3.2 API Key and Secret is provide by your Coopcycle instance : admin/api/apps (create an app and link it to a store)
4. Go to the settings of woocommerce and add a shipping zone.
 4.1 Name the Zone
 4.2 Add your country
 4.3 Add postal codes you accept.
 4.4 Add the shipping method "CoopCycle" and set the price for the delivery (flat rate)
 4.5 (Optional) You can also configure a free shipping method
== Frequently Asked Questions ==

* Shipping date droplit is not visible on checkout page
Check your credentials and the base url
Check your postal codes configuration.

* Order is complete ; visible in woocomerce backend but the tasks is not visible in my coopcycle instance
Check wordpress mail sender is correctly configured.

== Screenshots ==

== Changelog ==

= 0.11.3 =
* Add Spanish translations.

= 0.11.2 =
* Avoid creating the delivery twice.

= 0.11.1 =
* Retrieve phone number when using guest checkout.

= 0.11.0 =
* Show dropdown to choose shipping date after shipping options.

= 0.10.1 =
* Fix potential syntax error.

= 0.10.0 =
* Stop calculating price via API.
* Add more info in task comments.

= 0.9.2 =
* Add order number in task comments.

= 0.9.1 =
* Implement prior notice for time slots.

= 0.9.0 =
* Fix time slot dropdown not appearing on free shipping.

= 0.8.4 =
* Update translations.

== Upgrade Notice ==

