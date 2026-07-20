=== Deskovi ===
Contributors: deskovi
Tags: woocommerce, helpdesk, support, tickets, customer service
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WooCommerce store to Deskovi for helpdesk tickets, order context, and a customer chat widget.

== Description ==

Deskovi is a WooCommerce connector that links your store to the Deskovi helpdesk cloud so you can manage customer support from one place.

**Features**

* One-click style connection with a short-lived connect code from your Deskovi workspace
* Ticket bridge between WordPress and Deskovi (create, reply, status updates)
* WooCommerce order context for agents (HPOS-compatible)
* Optional customer chat widget on the storefront
* Guest email OTP verification for visitors who are not logged in
* Privacy export and erase hooks for WordPress Tools → Export / Erase Personal Data
* Diagnostics screen for connection health

By default the plugin runs in a **local mock** connection mode so you can explore the admin UI without contacting any remote service. Connecting to Deskovi cloud is optional and merchant-initiated.

**Requires** [WooCommerce](https://wordpress.org/plugins/woocommerce/).

== Installation ==

1. Install and activate [WooCommerce](https://wordpress.org/plugins/woocommerce/) if it is not already active.
2. Upload the `deskovi` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload Plugin.
3. Activate **Deskovi** through the Plugins menu.
4. Open **Deskovi** in the WordPress admin.
5. (Optional) In your Deskovi workspace, generate a connect code and paste it on the Connection screen to link this store to Deskovi cloud.

== Frequently Asked Questions ==

= Does this plugin require an account? =

You can activate the plugin and use mock/local tooling without a Deskovi account. Connecting to Deskovi cloud requires a Deskovi workspace and is optional.

= Does it work without WooCommerce? =

No. WooCommerce must be installed and active. Deskovi will not load its features until WooCommerce is available.

= Where is customer data stored? =

Ticket and connection data used by the connector are stored in your WordPress database. When you connect to Deskovi cloud, selected ticket and order context is also sent to Deskovi (see External services).

= How do I remove the chat widget? =

Disable the widget from the Deskovi admin settings. When the widget is off, storefront assets are not loaded.

= How do privacy requests work? =

Deskovi hooks into WordPress personal data export and erase tools so ticket-related personal data can be included when a user requests export or erasure.

== External services ==

This plugin can connect your store to the **Deskovi** cloud service at `https://app.deskovi.com`.

**When connected (merchant-initiated):**

* Your site URL, a public connection identifier, and signed ticket / order context may be sent to Deskovi so agents can support customers.
* Deskovi may deliver agent replies and status updates back to your site over HTTPS using a delivery secret established at connect time.
* The connection is started only when you enter a connect code from your Deskovi workspace. Mock mode does not call Deskovi.

**When not connected:**

* The plugin does not contact `https://app.deskovi.com`.

Service homepage: [https://deskovi.com](https://deskovi.com)
Terms and privacy for the cloud product are published on the Deskovi website. For WordPress site privacy, use **Settings → Privacy** and the export/erase tools under **Tools**.

== Screenshots ==

1. Connection screen — link your store with a Deskovi connect code
2. Tickets — bridge tickets and conversation history
3. Customer widget — optional storefront chat for shoppers

== Changelog ==

= 1.0.0 =
* Initial public release of the Deskovi WooCommerce connector
* Connection flow (mock by default; optional Deskovi cloud connect)
* Ticket bridge with order context (HPOS-aware)
* Optional customer chat widget and guest OTP
* Privacy export / erase integration
* Admin diagnostics

== Upgrade Notice ==

= 1.0.0 =
Initial public release.
