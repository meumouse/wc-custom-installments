# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [6.0.0] - Unreleased

First release of the rebuilt plugin. The version continues the numbering of "Parcelas Customizadas para WooCommerce" 5.x, which this plugin replaces.

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
- Settings screen in the design system MeuMouse shares with Joinotify: a header carrying the plugin logo, one segmented tab per section with its icon, a single panel with the label beside each control, and a save bar that stays inside the panel instead of covering the WordPress menu. Dependency-driven visibility, the Pro lock treatment and its upsell, toasts, and import/export/reset with a confirmation dialog.
- Component library: button and icon button, input and input group, searchable select, textarea, switch, checkbox, colour input, file input, label, badge, notice, modal and confirmation dialog, tabs, progress bar, stepper, code and form. Every other screen is assembled from these; `npm run lint:ui` fails the build if one renders a native control of its own.
- Admin styles scoped to the plugin's screens: Tailwind's reset no longer reaches the WordPress menu, the admin bar or other plugins' notices.
- Settings tabs an add-on renders: a section with a `component` gets a mount point that the add-on fills with its own app, registered through `window.WCCustomInstallmentsSections`. The Pro add-on's licence lives in one. `?section=<id>` opens a tab directly, and the tabs tighten when there are more than six.
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
- Setup wizard: seven steps, each saving as you leave it, with a live preview of the price the choices produce. It wears the same header, logo and components as the settings screen.
- A one-time redirect on activation and a dismissible reminder afterwards; the wizard stays reachable by URL.
- An upgrading store skips straight to the summary rather than being walked through choices it already made.
- Optional trial step that requests a seven-day Pro key, showing the exact request body before anything is sent.

- Translation pipeline: 410 strings extracted from PHP and Vue into `wc-custom-installments.pot`, the plugin header included.
- Every string the store owner reads is written in Brazilian Portuguese, as in 5.x, with the plugin under the name its stores already know. English ships with the plugin as an en_US catalogue, since WordPress.org has no English (United States) locale to deliver it; every other language comes from translate.wordpress.org.
- The plugin is published as "Parcelas Customizadas para WooCommerce" ("Custom Installments for WooCommerce" in English), with the slug and text domain `wc-custom-installments`. WordPress.org no longer accepts new slugs beginning with "woo".
- Admin notices rendered through `wp_admin_notice()`.
- `AGENTS.md`, `DEVELOPERS.md` and `docs/free-vs-pro.md`, the last two generated from the source so they cannot drift.
- The `wcci_*` extension helpers, and a runnable example plugin using every extension point.
- `Compatibility\Conflict_Guard`: while the 5.x plugin is still active, this one renders nothing on the storefront, explains why, and offers to switch the old one off.

### Fixed

- Seven settings had a default but no field in the settings schema, so they could never be written and sat at that default forever. Four of them are the "rewrite the displayed price" group, which now has a card of its own; two govern quantity discounts; one was dead and has been removed.
