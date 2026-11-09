=== 1to1 MLM ===
Tags: referrals, multi-level, woocommerce
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track three levels of member referrals and record configurable commissions for completed WooCommerce orders.

== Description ==

1to1 MLM provides a basic unilevel referral structure with three sponsor levels. Members can display their referral link with a shortcode. New accounts created after following a valid referral link are connected to that sponsor. When WooCommerce is active, the plugin records a commission entry for each configured sponsor level when a referred member's order is completed.

Commission percentages start at zero and must be configured by a site administrator. Commission entries are records only: the plugin does not issue, transfer, or guarantee payments. Before activating commissions, configure the plan to match your business and legal requirements.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install the ZIP through the WordPress Plugins screen.
2. Activate 1to1 MLM.
3. Visit Settings > 1to1 MLM Plan to set the three commission percentages.
4. Add [one_to_one_mlm_referral_link] to a page where logged-in members can access their referral link.
5. Install and activate WooCommerce if order-based commission records are required.

== Frequently Asked Questions ==

= Does this plugin pay commissions? =

No. It records commission amounts in the site's database. It does not process payments.

= What is the default commission rate? =

All levels default to zero percent. An administrator must explicitly configure the rates.

= What order total is used? =

The starter uses the WooCommerce order total. Guest orders are not attributed. The plugin does not automatically reverse entries after refunds, so review the ledger against refunds before making any manual payment decision.

== Privacy ==

The plugin stores sponsor relationships as user metadata. Referral links may set a first-party cookie named one_to_one_mlm_ref for up to 30 days. If WooCommerce is active and a commission rate is non-zero, the plugin stores order IDs, member IDs, level, amount, and record time in its commission ledger. No data is sent to an external service. Site operators should include this processing in their privacy policy and set their own data retention practices.

== Changelog ==

= 0.1.0 =
* Initial release.
