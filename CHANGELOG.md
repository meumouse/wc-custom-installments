# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Plugin scaffolding: bootstrap, constants, WooCommerce and PHP requirement checks, HPOS and Cart/Checkout Blocks compatibility.
- Automatic class instantiation from the Composer classmap, extensible through `WC_Custom_Installments/Init/Instance_Classes`.
- `Core\Features`, the single gate deciding whether a Pro feature may run.
- `Compatibility\Hook_Aliases`, which fires every hook under its current name and its 5.x name.
- Settings schema, repository and REST endpoints under `wc-custom-installments/v1`.
- Vue 3 + Vite + Tailwind admin app with the settings screen and the setup wizard shell.
- Build pipeline: `npm run build`, version gate and PHP 8.1 lint.
- Complete settings schema: 7 sections, 103 fields, covering installments, discounts, interest, payment methods, texts, appearance and advanced options.
- `Core\Migrator`, which imports a 5.x installation - the main option plus the three sidecar options holding gateway rates and the interest table - without deleting anything it read.
- `Admin\Settings\Transfer` and the reset, export and import endpoints.
- Sanitization per declared field type, including clamped numbers, per-gateway rate maps and the per-installment interest table.
- Settings screen: section tabs, cards, dependency-driven visibility, the Pro lock treatment and its upsell, a save bar that appears only while there are unsaved changes, toasts, and import/export/reset.
- Component library: switch, input, textarea, select, button, colour input, section card and toast stack.
- Field components for measurements, tokenised text, per-gateway rates and the interest table, resolved through a registry an add-on can extend.
- `preview.html` and `tests/dump-bootstrap.php`, which render the real admin bundle against a generated fixture with no WordPress and no database.
- Instalment and discount engine ported from the 5.x plugin, with its rounding and both interest formulas intact.
- Storefront rendering: the price group, the payment methods block as a panel or a dialog, the instalment table, and the discount and savings badges.
- `Frontend\Element_Registry`, which moves the price group's elements out of the settings default and into code.
- Generated stylesheet driven by that registry, replacing four hundred lines of hand-written blocks.
- Theme-overridable `single-product/price.php`.
- Storefront runtime rewritten as vanilla ES modules: the instalment panel and dialog, live price updates on variation and quantity changes, and the checkout refresh when the payment method changes.
- Client-side instalment calculation mirroring the server's, so a price that moves in the browser lands where the server would have put it.
- `preview-storefront.html`, which exercises the runtime against the real markup with no WordPress.
- Per-product overrides in the product data panel, plus quick edit and bulk edit, driven by a field registry an add-on can extend.
- All fifteen shortcodes, free, under both the 5.x and the current tag prefix, and usable outside a product template through a `product` attribute.
- Theme corrections for Astra, Ricky, Machic and Shoptimizer as a registry rather than a class each.
- Integrations with Woodmart, Tiered Pricing Table and Dynamic Pricing & Discounts.
- Elementor widgets for the price group and the instalment table, keeping their 5.x names.
