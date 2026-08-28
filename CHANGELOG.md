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
