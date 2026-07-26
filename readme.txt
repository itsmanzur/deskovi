=== Deskovi ===
Contributors: getwpkit
Tags: woocommerce, helpdesk, support, tickets, customer-service
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight WooCommerce helpdesk: tickets, order context for agents, and an optional customer chat widget — all stored locally on your site.

== Description ==

Deskovi adds a self-contained support ticket system to your WooCommerce store. Customers can open tickets (logged in or as a verified guest), agents reply from a dashboard inside wp-admin, and every ticket can be linked to the relevant order automatically.

**Features**

* Ticket system with replies, status (open/pending/resolved/closed), and file attachments
* WooCommerce order context for agents (HPOS-compatible)
* Optional customer chat widget on the storefront
* Guest email OTP verification for visitors who are not logged in
* Privacy export and erase hooks for WordPress Tools → Export / Erase Personal Data
* Admin diagnostics and activity log

All data — tickets, messages, and attachments — is stored in your own WordPress database. Deskovi does not require an external account or send data to any third-party service.

**Requires** [WooCommerce](https://wordpress.org/plugins/woocommerce/).

== Installation ==

1. Install and activate [WooCommerce](https://wordpress.org/plugins/woocommerce/) if it is not already active.
2. Upload the `deskovi` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload Plugin.
3. Activate **Deskovi** through the Plugins menu.
4. Open **Deskovi** in the WordPress admin to configure the widget and privacy settings.

== Frequently Asked Questions ==

= Does this plugin require an account? =

No. Deskovi runs entirely on your own site and does not require signing up for any external service.

= Does it work without WooCommerce? =

No. WooCommerce must be installed and active. Deskovi will not load its features until WooCommerce is available.

= Where is customer data stored? =

All ticket, message, and attachment data is stored in your own WordPress database (custom tables). Nothing is sent off-site.

= How do I remove the chat widget? =

Disable the widget from the Deskovi admin settings. When the widget is off, storefront assets are not loaded.

= How do privacy requests work? =

Deskovi hooks into WordPress personal data export and erase tools so ticket-related personal data can be included when a user requests export or erasure.

= Are file attachments secure? =

Yes. Uploaded files are stored outside direct web access and are only ever served through an authenticated request that checks the requester actually owns the related ticket.

== Source Code ==

This plugin's admin dashboard and customer widget are built from TypeScript/React source using @wordpress/scripts (webpack). The compiled, minified files shipped in assets/admin and assets/widget are generated from this source.

Full source, build tooling, and build instructions (npm install && npm run build) are publicly available at:
https://github.com/itsmanzur/deskovi

== Screenshots ==

1. Tickets — manage tickets and conversation history from wp-admin
2. Customer widget — optional storefront chat for shoppers

== Changelog ==

= 1.0.0 =
* Initial public release
* Local ticket system with order context (HPOS-aware)
* File attachments
* Optional customer chat widget and guest OTP
* Privacy export / erase integration
* Admin diagnostics

== Upgrade Notice ==

= 1.0.0 =
Initial public release.
