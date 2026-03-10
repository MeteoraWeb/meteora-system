=== Gestione Coupon & Fidelity ===
Contributors: Meteora Web
Tags: coupons, fidelity, tickets, woocommerce, qr-code, events
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Gestione Coupon & Fidelity is a professional WordPress plugin designed for businesses that need to manage digital coupons, loyalty points, and event tickets with QR codes.
It integrates seamlessly with WooCommerce for online payments, while also supporting offline (on-site) payments.

Main Features:
- Advanced coupon management with custom rules
- Customer fidelity points system
- Event management with ticket generation (PDF, PNG, WhatsApp link)
- WooCommerce integration (optional for online payments)
- Offline / on-site payment support
- Automatic QR code generation
- Multi-language support (WPML/Polylang ready)
- Modern UI/UX for easy use in admin

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/gestione-coupon-fidelity` directory, or install directly from WordPress admin.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure settings under **Coupon & Fidelity** in the admin menu.

== Theme compatibility ==

Some WordPress themes apply aggressive CSS resets to `<select>` elements (for example `appearance: none`, `overflow: hidden`, `z-index` limits or hiding the native control). These rules can prevent the "Tipologia di ticket" and "Seleziona PR" dropdowns from opening. The frontend stylesheet now ships dedicated classes (`.ucg-select`, `.ucg-select--ticket`, `.ucg-select--pr`) that restore the browser defaults and keep the dropdown list visible. If you still notice conflicts, override the theme CSS by targeting these classes and ensure the parent containers allow `overflow: visible`.

== License ==

- The PHP code of this plugin is licensed under the GNU General Public License v2 or later.
- Non-PHP assets (CSS, JS, images) are licensed under the Envato Market License (Regular/Extended).

For more details: https://codecanyon.net/licenses/standard

== Changelog ==

= 1.0.0 =
* Initial release with coupon management, fidelity system, event ticketing and WooCommerce integration.
