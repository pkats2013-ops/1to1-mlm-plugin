# 1to1 MLM

A basic three-level unilevel referral plugin for WordPress, with optional WooCommerce commission recording.

## Features

- Members can share a referral URL using the [one_to_one_mlm_referral_link] shortcode.
- New accounts can be assigned to the member who referred them.
- Admins can configure a percentage for each of three levels. All rates start at 0%, and the combined rate is capped at 100%.
- When WooCommerce is active, completed orders can create commission records in an administrator ledger.

## Limits

This plugin records commissions; it does not issue payments. It uses the WooCommerce order total, does not attribute guest orders, and does not automatically reverse entries after refunds. Review the plan and ledger before making any payment decisions.

## Installation

1. Upload the plugin folder to wp-content/plugins/ or install its ZIP in WordPress.
2. Activate 1to1 MLM.
3. Configure rates under Settings > 1to1 MLM Plan.
4. Add [one_to_one_mlm_referral_link] to a page members can access.
5. Install WooCommerce if order-based commission records are needed.

See [readme.txt](readme.txt) for the WordPress.org-style plugin documentation. The plugin is GPL-2.0-or-later.
