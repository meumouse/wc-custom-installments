# WC Custom Installments

Free WooCommerce plugin that shows installment plans, upfront-payment discounts and accepted payment
methods on the storefront. Distributed on WordPress.org under the GPLv2 or later.

The commercial capabilities live in a separate add-on,
[WC Custom Installments Pro](../wc-custom-installments-pro), which this plugin knows nothing about
beyond one filter.

## Architecture

Backend and frontend are strictly separated. PHP is an API and a schema provider; the admin screens
are Vue applications that consume it over REST. There is no server-rendered settings markup and no
jQuery in the admin.

```
wc-custom-installments.php   Bootstrap: autoloader, Core\Init
admin/                       All backend PHP, Composer included
  composer.json              PSR-4 MeuMouse\WC_Custom_Installments\ -> src/
  src/Admin/                 Menu, Default_Options, Settings\{Registry,Repository}
  src/Assets/                Vite manifest resolution and enqueueing
  src/Compatibility/         Hook_Aliases and the 5.x bridges
  src/Core/                  Init, Features, Scripts, Helpers, Logger, Onboarding
  src/Engine/                Installment and discount calculation
  src/Frontend/              Storefront rendering, styles, shortcodes
  src/Integrations/          Themes, Elementor, pricing plugins
  src/Rest/                  Abstract_Route and one class per endpoint
  src/Views/                 The two page shells the Vue apps mount into
  vendor/                    Composer output (generated, not versioned)
app/                         All frontend: Vue 3 + Vite + Tailwind, one entry per screen
  src/entries/               settings.js, onboarding.js, storefront.js
  src/storefront/            Vanilla runtime for the shop: no framework, no jQuery of our own
  dist/                      Vite output (generated, not versioned)
assets/ templates/ languages/ scripts/ tests/ docs/ examples/
```

### Automatic class instantiation

`Core\Init` is the only class the plugin file constructs. Everything else is read from the Composer
classmap and instantiated on the hook that suits its namespace, so a new class is wired up simply by
living under `admin/src` with the right namespace and a constructor that takes no required
arguments. Add-ons inject their own classes through
`WC_Custom_Installments/Init/Instance_Classes`.

After adding, renaming or moving a class, regenerate the classmap:

```bash
cd admin && composer dump-autoload -o
```

### The feature gate

`Core\Features` is the single place that decides whether a capability may run. Each schema field
names the feature that gates it, and three behaviours follow from that one key: the field ships to
the browser marked `locked`, `Settings\Repository` refuses to persist it, and the engine skips the
behaviour. The Pro add-on unlocks everything by answering one filter,
`WC_Custom_Installments/Features/Is_Available`.

The browser is told what is locked; the browser is not what enforces it.

### Backward compatibility

The predecessor plugin published 54 hooks under `Woo_Custom_Installments/...`, and store owners have
snippets bound to them. `Compatibility\Hook_Aliases` fires every hook under both its current name and
its 5.x name, with no deprecation notice — the aliases are supported, not tolerated. Shortcodes,
Elementor widget slugs, product meta keys, storefront CSS classes and the public JS API are
preserved for the same reason.

## Development

```bash
cd admin && composer install     # PHP dependencies and the autoloader
cd app && npm install            # frontend dependencies
npm install                      # build tooling, from the repository root

npm run build                    # full pipeline -> release/wc-custom-installments-<version>.zip
npm run build:app                # frontend only
npm run dev                      # Vite dev server
npm run lint:php                 # parses every source file with a PHP 8.1 binary
```

`npm run lint:php` needs a real PHP **8.1** binary, because linting against a newer runtime would
accept syntax the oldest supported host rejects. Point at one with `PHP81_BIN` in a Git-ignored
`.env` (see `.env.example`) or with `--php=<path>`.

### Tests

The harnesses in `tests/` are standalone: no PhpUnit, no WordPress bootstrap, no database. They run
with any PHP 8.1+ binary.

```bash
php tests/feature-gate-test.php
```

```bash
php tests/migration-test.php
```

```bash
php tests/engine-test.php
```

### Previews

Two static harnesses render the real built bundles with no WordPress and no database. Build first,
then serve the plugin directory and open either page.

```bash
php tests/dump-bootstrap.php && python -m http.server 8791 --bind 127.0.0.1
```

`preview.html` renders the settings screen against a generated fixture; `preview-storefront.html`
renders the storefront runtime against the markup the PHP produces. Neither ships in the release
package.

## Documentation

- [`docs/rebuild-plan.md`](docs/rebuild-plan.md) — the plan this codebase is being built against
- `CHANGELOG.md` — version history
- `readme.txt` — the WordPress.org listing, including the external services declaration
