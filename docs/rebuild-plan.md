# Custom Installments for WooCommerce — Rebuild Plan

Plan to rebuild `woo-custom-installments` as a **free WordPress.org plugin** (`wc-custom-installments`)
plus a **commercial add-on** (`wc-custom-installments-pro`), with an API-first backend and a
Vue 3 + Tailwind admin, reusing the Joinotify architecture and design system and the MDS PHP SDK.

> Status: proposal. Section 2 (feature matrix) and section 12 (compatibility contract) contain
> decisions that must be confirmed before Phase 1 starts.

---

## 1. Objectives and constraints

| # | Objective |
|---|-----------|
| 1 | Ship a genuinely useful **free** plugin that passes the WordPress.org review. |
| 2 | Show **Pro features in the settings UI**, visibly locked, so the free user knows what exists. |
| 3 | Unlock those features **only** when the Pro add-on is installed and licensed. |
| 4 | **API-first**: PHP serves REST + schemas; no server-rendered settings markup, no jQuery in admin. |
| 5 | **All backend PHP under `/admin`, Composer included; all frontend under `/app`** — the Joinotify layout, verbatim. |
| 6 | Reuse the **Joinotify design system** and component library (Vue 3, Tailwind, Pinia, Vite). |
| 7 | **Every string translatable through wp-i18n, authored in English (en_US)** — PHP and Vue alike. |
| 8 | The Pro add-on uses the **MDS PHP SDK** against `https://cloud.meumouse.com` for licensing, updates and rollback. |
| 9 | Provide an **onboarding wizard** on install, ending with a 7-day Pro trial. |
| 10 | Reuse the current **domain code** (installment maths, discounts, interests, rendering). |
| 11 | **No data loss and no broken hook, filter, shortcode, widget or CSS class** for existing installs. |

### Hard constraints from the WordPress.org guidelines

These shape the architecture more than anything else:

- **No code executed from a remote server** and **no self-updating mechanism** in the free plugin.
  The MDS SDK therefore lives **exclusively in the Pro add-on**.
- **No external request before consent.** The free plugin must contact nothing on activation. The
  trial form is an explicit, user-initiated action.
- **Every external service declared** in `readme.txt`.
- **No minified asset without its source.** The Vite/Tailwind sources ship in the repository.
- **GPLv2 or later** for the free plugin.
- **Requires Plugins: wc-custom-installments** header on the Pro add-on (WP 6.5+), so WordPress
  itself enforces the dependency.

### Naming

| Item | Free | Pro |
|---|---|---|
| Folder / slug | `wc-custom-installments` | `wc-custom-installments-pro` |
| Text domain | `wc-custom-installments` | `wc-custom-installments-pro` |
| Main file | `wc-custom-installments.php` | `wc-custom-installments-pro.php` |
| PHP namespace | `MeuMouse\WC_Custom_Installments\` → `admin/src/` | `MeuMouse\WC_Custom_Installments\Pro\` → `admin/src/` |
| Constant prefix | `WC_CUSTOM_INSTALLMENTS_` | `WC_CUSTOM_INSTALLMENTS_PRO_` |
| Hook namespace | `WC_Custom_Installments/…` | `WC_Custom_Installments/Pro/…` |
| REST namespace | `wc-custom-installments/v1` | same namespace, routes registered by the add-on |
| Option key | `wc_custom_installments_settings` | writes into the same option |
| MDS product slug | — (not distributed by MDS) | `wc-custom-installments-pro` |
| Storefront CSS/JS prefix | `wci-` (unchanged, for backward compatibility) | `wci-` |

---

## 2. Feature matrix — free vs Pro

Derived from the current `Default_Options::set_default_data_options()` and from the
`$switchs_without_license` / `$switchs_with_license` split in `Core\Ajax`.

> **Note on the current split.** Today several toggles *save* without a license but their runtime is
> still gated (`Discounts.php` and `Interests.php` check `License::is_valid()` **and** the toggle).
> The free tier is effectively empty. The matrix below deliberately moves real value into the free
> tier — otherwise the WordPress.org listing has nothing to offer and the review team will say so.

### 2.1 Free tier

| Area | Feature | Settings keys |
|---|---|---|
| Installments | Global interest rate, max installments, max without fee, min installment value | `fee_installments_global`, `max_qtd_installments`, `max_qtd_installments_without_fee`, `min_value_installments` |
| Installments | Best-installment display on loop and single product | `enable_installments_all_products`, `get_type_best_installments`, `hook_display_best_installments` |
| Installments | Installments table / popup / inline | `display_installment_type`, `text_button_installments`, `text_table_installments` |
| Installments | Show installments in the cart | `display_installments_cart` |
| Discounts | Single discount on the main price (percentage or fixed) | `enable_all_discount_options`, `product_price_discount_method`, `discount_main_price` |
| Discounts | Discount tag on cart/checkout, shipping inclusion | `display_tag_discount_price_checkout`, `include_shipping_value_in_discounts` |
| Payment methods | Pix / boleto / credit / debit containers and all bundled brand badges | `enable_*_method_payment_form`, `enable_*_flag_credit`, `enable_*_flag_debit`, `enable_instant_approval_badge` |
| Payment methods | Placement hook for the payment-methods block | `hook_payment_form_single_product`, `set_custom_hook_payment_form` |
| Texts | Every `text_*` string except the three placeholder templates | all `text_*` |
| Styles | Element colors, font size/weight, order, popup button color and size | subset of `elements_design`, `button_popup_*`, `icon_format_elements` |
| Styles | Center the price group in the loop | `center_group_elements_loop`, `selectors_group_for_center_elements` |
| Product | Per-product "disable installments" and "disable main discount" | `__disable_installments`, `__disable_discount_main_price` |
| Integrations | Theme compatibility (Astra, Woodmart, Xstore, Machic, Ricky, Shoptimizer), Tiered Pricing, Dynamic Pricing | — |
| Elementor | `wci_single_product_price`, `wci_installments_table` | `enable_elementor_widgets` |
| Shortcodes | All 15 shortcodes | — |
| Platform | Onboarding wizard, settings import/export, system status, debug log | — |

### 2.2 Pro tier

| Area | Feature | Settings keys |
|---|---|---|
| Installments | Per-installment custom fee table | `set_fee_per_installment`, `custom_fee_installments[]` |
| Installments | Custom placeholder templates for the installment strings | `text_display_installments_loop`, `text_display_installments_single_product`, `text_display_installments_payment_forms` |
| Discounts | Per-gateway discounts | `woo_custom_installments_discounts[]` |
| Interests | Per-gateway interests | `woo_custom_installments_interests[]` |
| Discounts | Discount per quantity / per unit (tiered) | `enable_functions_discount_per_quantity`, `enable_discount_per_quantity_method`, `set_quantity_enable_discount`, `discount_per_quantity_method`, `value_for_discount_per_quantity`, `enable_discount_per_unit_discount_per_quantity`, `message_discount_per_quantity`, `discount_per_qtd_message_method`, `text_discount_per_quantity_message` |
| Discounts | Boleto discount and boleto badge | `enable_ticket_discount_main_price`, `discount_method_ticket`, `discount_ticket`, `display_discount_ticket_hook`, `text_*_discount_ticket` |
| Discounts | Pix economy badge | `enable_economy_pix_badge`, `text_economy_pix_badge`, `display_economy_pix_hook` |
| Discounts | Sale badge (percentage off) | `enable_sale_badge` |
| Discounts | Custom product price discount | `add_discount_custom_product_price`, `discount_value_custom_product_price` |
| Variable products | Remove price range, live price update with quantity/variation | `remove_price_range`, `update_price_with_quantity`, `update_range_price_triggers` |
| SEO | Discounted price in schema.org, XML feed meta, Rank Math integration | `display_discount_price_schema`, `enable_post_meta_feed_xml_price` |
| Styles | Full design controls: per-breakpoint spacing, padding, radius, background, icons | full `elements_design` |
| Product | Per-product gateway discounts, per-product tiered discounts, per-product Pix price | `discount_gateway`, `enable_discount_per_quantity`, `enable_discount_per_unit`, `_product_price_on_pix` |
| Elementor | `wci_credit_card_badges`, `wci_debit_card_badges`, `wci_popup_payment_methods`, `wci_accordion_payment_methods`, `wci_price_info_box`, `wci_sale_badge`, `wci_discount_per_quantity_message` | `enable_price_grid_in_widgets` |
| Platform | License management, automatic updates, version rollback, priority support | `enable_auto_updates`, `enable_update_notices` |

### 2.3 Free/Pro decision rules

1. **Anything that talks to a remote server is Pro.** That keeps the free plugin's external-service
   surface at zero, apart from the explicitly submitted trial form.
2. **One of a kind is free, many are Pro.** One global interest rate free, per-installment table Pro.
   One discount free, per-gateway and per-quantity Pro.
3. **Compatibility is always free.** Theme and page-builder integrations belong to the free tier;
   a store whose theme breaks will simply uninstall.
4. **Presentation basics free, fine-grained design Pro.**

---

## 3. Repository layout

### 3.1 Free plugin

Backend PHP — including Composer — lives entirely under `admin/`. Frontend lives entirely under
`app/`. The plugin root holds only the bootstrap file, the documentation, the build scripts and the
runtime folders WordPress expects (`languages/`, `templates/`, `assets/`). This is the Joinotify
layout, adopted without deviation.

```
wc-custom-installments/
├── wc-custom-installments.php      # ONLY: headers, autoloader require, new Core\Init()
├── admin/                          # ← ALL backend PHP
│   ├── composer.json               #   PSR-4: MeuMouse\WC_Custom_Installments\ → src/
│   ├── composer.lock
│   ├── src/
│   │   ├── Admin/                  #   Menu, Default_Options, Product_Settings
│   │   │   ├── Settings/           #     Registry (schema), Repository (persist), Transfer
│   │   │   └── Onboarding/         #     Registry
│   │   ├── Api/                    #   Extensions facade — the Pro add-on's entry point
│   │   ├── Assets/                 #   Abstract_Assets, Settings_Assets, Storefront_Assets
│   │   ├── Compatibility/          #   Hook_Aliases, Legacy_Hooks, Legacy_Filters, Conflict_Guard
│   │   ├── Core/                   #   Init, Scripts, Helpers, Logger, Features, Migrator, Onboarding
│   │   ├── Engine/                 #   Calculate_Installments, Calculate_Values, Discounts, Interests
│   │   ├── Frontend/               #   Render_Elements, Styles, Components, Shortcodes, Element_Registry
│   │   ├── Integrations/           #   Themes, Elementor (+ Widgets/), pricing plugins
│   │   └── Rest/                   #   Abstract_Route + one class per route
│   └── vendor/                     #   Composer output — NOT versioned, generated by the build
├── app/                            # ← ALL frontend
│   ├── package.json · vite.config.js · tailwind.config.js · postcss.config.js · tsconfig.json
│   ├── src/{entries,pages,components,composables,stores,utils,styles,types,storefront}
│   └── dist/                       #   Vite output — NOT versioned, generated by the build
├── assets/                         # Brand SVGs, payment badges (static, not built)
├── templates/                      # WooCommerce template overrides
├── languages/                      # i18n pipeline (Node) + .pot
├── scripts/                        # build.mjs, deploy-svn.mjs, version.mjs, lint-php.mjs, env.mjs
├── tests/                          # Standalone PHP harnesses
├── .wordpress-org/                 # Banner, icon, screenshots (not shipped)
├── examples/ · docs/
└── readme.txt · LICENSE · AGENTS.md · DEVELOPERS.md · CHANGELOG.md · README.md
```

`.gitignore` excludes `admin/vendor/`, `app/dist/`, `app/node_modules/`, `node_modules/`, `release/`,
`.env`, `.wporg-svn/`. Both generated trees are produced by `scripts/build.mjs` and ship inside the
ZIP — this is the opposite of today's plugin, which commits `vendor/` and hand-maintained `.min`
files.

The current `inc/` tree and the root `vendor/` disappear entirely.

### 3.2 Pro add-on

Same layout, same rules.

```
wc-custom-installments-pro/
├── wc-custom-installments-pro.php  # Headers incl. `Requires Plugins: wc-custom-installments`
├── admin/
│   ├── composer.json               #   PSR-4 + require meumouse/mds-php-sdk
│   ├── src/
│   │   ├── Core/Init.php           #   Boots only when the free plugin is present and compatible
│   │   ├── Licensing/              #   MDS_Integration, Feature_Provider, Trial, Legacy_Migrator
│   │   ├── Settings/               #   Registers Pro sections/cards/fields into the free schema
│   │   ├── Engine/                 #   Gateway discounts/interests, quantity discounts, badges
│   │   ├── Frontend/               #   Pro render elements, dynamic styles, Schema.org, XML feed
│   │   ├── Integrations/           #   Elementor Pro widgets, Rank Math
│   │   └── Rest/                   #   License, rollback and trial routes
│   └── vendor/                     #   Composer output incl. the MDS SDK — shipped in the ZIP
├── app/                            # Vite bundle: Pro field components + the license screen
├── dist/                           # Release ZIPs (uploaded to MDS)
└── scripts/ · languages/ · docs/ · readme.md
```

### 3.3 Bootstrapping

- `Core\Init::__construct( $file, $version )` fires `WC_Custom_Installments/Core/Before_Init`, checks
  PHP ≥ 8.1 and WooCommerce ≥ 8.0, defines constants, declares HPOS + Cart/Checkout Blocks
  compatibility, then registers on `plugins_loaded` (priority 20).
- Class discovery keeps reading `admin/vendor/composer/autoload_classmap.php`, skipping
  `Abstract|Interface|Trait`. Hook resolution per namespace:

  | Namespace | Hook |
  |---|---|
  | `Rest\*` | `init` (the route class registers its own `rest_api_init`) |
  | `Integrations\Elementor*` | `elementor/init` |
  | `Frontend\*` | `init` |
  | everything else | `init` |

- Third parties (and the Pro add-on) inject classes via
  `WC_Custom_Installments/Init/Instance_Classes`.
- **Requirement kept:** every auto-instantiated constructor works with zero required arguments.
- The Pro add-on registers its MDS integration on `mds_sdk_loaded` — see §7 — which is **earlier**
  than everything above, so that registration lives in the main plugin file, not in a discovered class.

### 3.4 Settings storage

- One option: `wc_custom_installments_settings` (associative array).
- `Admin\Settings\Repository::get_settings()` merges with `Admin\Default_Options::get_defaults()`.
- `Repository::save_settings( array $incoming )` sanitizes **per field definition** taken from
  `Registry::get_field_definitions()` — this replaces the hand-maintained `$switchs_without_license`
  array and the `parse_str()` form parsing entirely.
- Toggles stay `'yes'`/`'no'` strings, so the engine code ports without edits.
- `Core\Migrator` reads the legacy `woo-custom-installments-setting` option, maps keys and writes the
  new one. Idempotent, guarded by `wc_custom_installments_db_version`. See §12.

### 3.5 The feature gate — the heart of the free/Pro split

A single contract, one enforcement point, no scattered `License::is_valid()` calls.

```php
// admin/src/Core/Features.php  (free plugin)

Features::register( 'gateway_discounts', array(
    'label' => __( 'Discounts per payment method', 'wc-custom-installments' ),
    'tier'  => 'pro',
) );

Features::is_available( 'gateway_discounts' );   // false until the Pro add-on says otherwise
```

- `Features::is_available( $id )` returns `true` for `tier === 'free'`; for `tier === 'pro'` it
  returns `apply_filters( 'WC_Custom_Installments/Features/Is_Available', false, $id )`.
- The Pro add-on registers **one** listener, backed by the MDS SDK:

```php
// wc-custom-installments-pro — admin/src/Licensing/Feature_Provider.php
add_filter( 'WC_Custom_Installments/Features/Is_Available', function( $available, $feature_id ) {
    return MDS_Integration::is_licensed() ? true : $available;
}, 10, 2 );
```

- Every schema field declares `'feature' => 'gateway_discounts'`. From that one key the system
  derives three behaviours automatically:
  1. **UI** — the field ships to the browser with `locked: true` and the Vue renderer wraps it in the
     Pro overlay.
  2. **Persistence** — `Repository` refuses to write a locked field; the stored/default value stays.
  3. **Runtime** — engine classes call `Features::is_available()` before applying the behaviour.
- A locked field is still **sent** to the browser with its default value, so the user sees exactly
  what the feature looks like. It is never *silently* saveable.

### 3.6 Extension API (`Api\Extensions` + `wcci_*()` helpers)

The Pro add-on must not edit the free core. Everything it needs goes through a documented facade,
mirroring `Joinotify\Api\Extensions`:

| Helper | Registers |
|---|---|
| `wcci_register_feature( $id, $args )` | A gated feature |
| `wcci_register_settings_section( $section )` | A settings tab |
| `wcci_register_settings_card( $section_id, $card )` | A card inside a tab |
| `wcci_register_settings_field( $card_id, $field )` | A field inside a card |
| `wcci_register_default_options( $defaults )` | Defaults for new keys |
| `wcci_register_rest_route( $route )` | A route under `wc-custom-installments/v1` |
| `wcci_register_price_element( $element )` | An element in the storefront price group |
| `wcci_register_product_setting( $field )` | A field in the product data panel |
| `wcci_register_shortcode( $tag, $callback )` | A shortcode |

Each is one `add_filter()` underneath. `DEVELOPERS.md` documents them, and a runnable example ships
in `examples/`.

### 3.7 REST surface (`wc-custom-installments/v1`)

| Route | Method | Purpose |
|---|---|---|
| `/admin/settings` | GET · POST | Bootstrap (schema + values + features + environment) · Save |
| `/admin/settings/reset` · `/export` · `/import` | POST · GET · POST | Defaults and JSON transfer |
| `/admin/onboarding` · `/step` · `/complete` · `/skip` | GET · POST · POST · POST | Setup wizard |
| `/admin/product/{id}` | GET · POST | Per-product overrides |
| `/admin/preview` | POST | Render the price group for the live preview |
| `/admin/system-status` | GET | Environment report |
| `/admin/debug/logs` · `/clear` · `/download` | GET · POST · GET | Log tools |
| `/trial` | POST | Free-plugin trial form → MDS `/v1/licenses/demo` (§7.4) |
| `/license` · `/activate` · `/deactivate` · `/recheck` | GET · POST · POST · POST | **Pro only** |
| `/license/versions` · `/rollback` | GET · POST | **Pro only** |

All admin routes: `permission_callback` = `current_user_can('manage_woocommerce')`, `X-WP-Nonce`.
`Rest\Abstract_Route` is ported verbatim from Joinotify (`success_response()` / `error_response()`).

---

## 4. Admin frontend (Vue 3 + Vite + Tailwind)

### 4.1 Reuse from Joinotify

| From `joinotify/app/src` | Use |
|---|---|
| `utils/bootstrap.js`, `utils/api.js`, `utils/i18n.ts`, `utils/object.js`, `utils/html.ts` | Verbatim, prefix renamed |
| `components/base/*` (17 primitives) | Verbatim |
| `components/fields/*` + `fieldRegistry.js` | Verbatim; drop WhatsApp/OTP/phone/cloud fields, add the plugin's own |
| `components/cards/SectionCard.vue`, `StatusBadge.vue` | Verbatim |
| `components/modals/*`, `toasts/ToastStack.vue`, `toggles/ToggleSwitch.vue`, `skeletons/*` | Verbatim |
| `pages/settings/{SettingsPage.vue,SectionTabs.vue,SettingsActionBar.vue}` | Adapted shell |
| `pages/settings/components/cards/{DangerZone,ImportExportSettings,SystemStatusPanel,DebugLogModal}.vue` | Verbatim |
| `pages/onboarding/*` | Adapted step flow |
| `tailwind.config.js`, `postcss.config.js`, `tsconfig.json`, `vite.config.js` | Adapted entries |

### 4.2 New field components

| Component | Used by |
|---|---|
| `InstallmentTableField.vue` | Per-installment fee table (Pro) |
| `GatewayRateField.vue` | Per-gateway discount/interest rows (Pro) |
| `QuantityTierField.vue` | Discount per quantity tiers (Pro) |
| `HookSelectField.vue` | Display-position selectors, with a custom-hook text input |
| `PlaceholderTextField.vue` | Text fields with `{{ parcelas }}` / `{{ valor }}` token insertion |
| `ElementDesignField.vue` | Per-element typography/spacing controls, mobile/desktop tabs |
| `PaymentBadgePickerField.vue` | Card-brand checkbox grid with the bundled SVGs |
| `PricePreviewField.vue` | Live preview of the rendered price group |
| `ProLockOverlay.vue` | Wrapper for any `locked: true` field |

### 4.3 The Pro lock treatment

- `FieldRenderer.vue` checks `field.locked`. When true it renders the real control **disabled**,
  overlaid with a subtle blur and a `PRO` badge; clicking opens `ProUpsellModal.vue`.
- The modal states the feature, lists what else the Pro tier unlocks, and offers **Start the 7-day
  free trial** (opens the trial form, §7.4) and **See plans** (a plain link).
- A whole locked section renders a `ProSectionNotice` header instead of per-field overlays.
- When the Pro add-on is active and licensed, the bootstrap payload reports the feature as available
  and the overlay never mounts.

### 4.4 Entries

| Entry | Screen | Handle |
|---|---|---|
| `settings.js` | Settings | `wc-custom-installments-settings-app` |
| `onboarding.js` | Setup wizard | `wc-custom-installments-onboarding-app` |
| `product.js` | Product data panel | `wc-custom-installments-product-app` |
| `storefront.js` | Storefront runtime (§5) | `wc-custom-installments-storefront` |
| `license.js` (Pro `app/`) | License, trial and rollback screen | `wc-custom-installments-pro-license-app` |

### 4.5 Design system

Start from Joinotify's Tailwind tokens (`primary`, `shell`, `ink`, `success`, `danger`, `warning`,
`info`, `muted`, `shadow-soft`, `rounded-2xl`) unchanged, so the two products look like one family.
`important: true` stays on, to beat the WordPress admin CSS.

---

## 5. Storefront runtime

The storefront is **not** a Vue app and **not** Tailwind — a utility framework on a customer-facing
page fights the theme and inflates the payload.

- **Markup** stays server-rendered PHP (`Frontend\Render_Elements`, `templates/`), because it must be
  theme-overridable and must appear in the HTML for SEO and for the schema.org output.
- **CSS**: hand-written, scoped to `.wci-*`, authored in `app/src/storefront/styles/`, driven by CSS
  custom properties that `Frontend\Styles` prints from the settings.
- **JS**: the current jQuery `woo-custom-installments-front-scripts.js` is rewritten as a vanilla ES
  module bundled to `app/dist/storefront/app.js`, handling the popup, variation price updates,
  quantity-driven updates and the cart/checkout refresh.
- **Public API preserved** — see §12.6.

---

## 6. Onboarding wizard

Reuses `Core\Onboarding` from Joinotify almost verbatim (activation transient, one-time redirect,
dismissible notice, `admin_body_class`, state option, wizard reachable by URL forever).

| # | Step | Writes |
|---|---|---|
| 1 | **Welcome** — what the plugin does, store currency and locale detected | — |
| 2 | **Installments** — max installments, installments without fee, global interest rate, minimum installment value | `max_qtd_installments`, `max_qtd_installments_without_fee`, `fee_installments_global`, `min_value_installments` |
| 3 | **Discount** — enable the à-vista/Pix discount, method and amount | `enable_all_discount_options`, `product_price_discount_method`, `discount_main_price` |
| 4 | **Payment methods** — which containers and which card brands to show | `enable_*_method_payment_form`, `enable_*_flag_*` |
| 5 | **Display** — where and how to show, with a live preview | `hook_display_best_installments`, `display_installment_type`, `display_discount_price_hook` |
| 6 | **Pro trial** — the 7-day trial form (§7.4) | nothing, unless the user submits |
| 7 | **Finish** — links to the settings screen, the docs and the shop preview | marks the wizard complete |

An install that already has legacy settings (§12.1) **skips straight to step 7** with a "settings
imported" summary — an existing customer must not be asked to reconfigure what they already have.

---

## 7. Licensing, updates and the trial — the MDS PHP SDK

The Pro add-on delegates **all** of licensing, updates and rollback to
[`meumouse/mds-php-sdk`](https://github.com/meumouse/mds-php-sdk), against `https://cloud.meumouse.com`.
The current hand-written `inc/API/License.php` (1,127 lines, talking to
`https://api.meumouse.com/wp-json/license/`) is **deleted, not ported**.

### 7.1 Registration

The SDK elects its newest embedded copy on `plugins_loaded` priority `-100` and fires
`mds_sdk_loaded`. Registration therefore lives in the Pro plugin's main file, before any class
discovery:

```php
// wc-custom-installments-pro.php
require_once __DIR__ . '/admin/vendor/autoload.php';

add_action( 'mds_sdk_loaded', function() {
    \MeuMouse\MDS\SDK\SDK::register( array(
        'product_slug'    => 'wc-custom-installments-pro',
        'type'            => 'plugin',
        'file'            => plugin_basename( __FILE__ ),
        'current_version' => WC_CUSTOM_INSTALLMENTS_PRO_VERSION,
        'api_base_url'    => WC_CUSTOM_INSTALLMENTS_PRO_MDS_API_URL,     // https://cloud.meumouse.com
        'api_key'         => WC_CUSTOM_INSTALLMENTS_PRO_MDS_API_KEY,
        'public_key'      => WC_CUSTOM_INSTALLMENTS_PRO_MDS_PUBLIC_KEY,
        'item_name'       => 'Custom Installments for WooCommerce Pro',
        'text_domain'     => 'wc-custom-installments-pro',
        'mode'            => 'full',
        'features'        => array(
            'admin_menu'          => false,  // the license screen is ours, in Vue
            'admin_license_panel' => false,
            'admin_rollback_panel'=> false,
            'notices'             => true,   // keep the SDK's expiry warnings
        ),
    ) );
} );

register_deactivation_hook( __FILE__, function() {
    $integration = \MeuMouse\MDS\SDK\SDK::get('wc-custom-installments-pro');

    if ( $integration ) {
        $integration->shutdown();  // clears the heartbeat cron event
    }
} );
```

The three MDS constants are defined in `Core\Init::setup_constants()` with `defined()` guards, so
`wp-config.php` can override them for staging.

`mds_wc_custom_installments_pro_settings_url` is filtered to point at our own license screen, so the
SDK notices link to the right place.

### 7.2 What the SDK gives us for free

| Concern | SDK component | Replaces |
|---|---|---|
| Activation / deactivation, fail-closed, ed25519-verified | `License\Manager` | `API\License::validate_license()` |
| Daily re-validation with jitter and a 14-day grace period | `Cron\Scheduler` | nothing — the current plugin has no heartbeat |
| Plugin update injection + "View details" modal | `Updates\PluginUpdater` | `API\Updater` (already SDK-based, `updates_only`) |
| Version listing and downgrade | `Rollback\Manager` | **new capability** |
| Bundle licenses ("Clube M") | `LicenseStatus::get('bundle')` | **new capability** |
| Server-side gate waivers for grandfathered customers | `allows_updates()` | **new capability** |
| Expiry / invalid notices | `Admin\Notices` | ad-hoc notices in `Views/Settings/Tabs/About.php` |

Rollback and bundle display are genuinely new features that come at almost no cost — worth
advertising in the Pro release notes.

### 7.3 Feature gating on top of the SDK

```php
// admin/src/Licensing/MDS_Integration.php
public static function is_licensed() {
    $integration = \MeuMouse\MDS\SDK\SDK::get('wc-custom-installments-pro');

    return $integration ? $integration->is_licensed() : false;
}
```

`is_licensed()` already honours the grace period, so a store whose site cannot reach
`cloud.meumouse.com` keeps its Pro features for 14 days instead of silently degrading mid-campaign.
When the license really does expire, `Features::is_available()` starts returning `false` and the
store falls back to the free tier with no fatal error — that is the whole point of routing every
gate through one function.

### 7.4 The 7-day trial

`POST /v1/licenses/demo` on the MDS API is **unauthenticated**, per-IP rate limited, and mints a
license from the server-side demo plan (`DEMO_LICENSE_PLAN_SLUG`) — the plugin never chooses the
duration. It takes `productId`, `firstName`, `lastName`, `email`, `phone`, refuses a second live demo
for the same email + product, e-mails the key, and returns it once in the response.

Because the endpoint exists and is designed for exactly this, the trial form can live **inside the
free plugin**:

1. Onboarding step 6 (and the Pro upsell modal) shows a form: first name, last name, e-mail, phone,
   plus an explicit consent line naming `cloud.meumouse.com` and what is sent.
2. Submitting calls our own `POST wc-custom-installments/v1/trial`, which forwards to
   `https://cloud.meumouse.com/v1/licenses/demo`. **Nothing is sent before the click.**
3. The response is shown on screen: the license key, the expiry date, and a download link for the Pro
   ZIP. The same information arrives by e-mail.
4. The user installs the Pro add-on and pastes the key — or, if the Pro add-on is already installed,
   the free plugin hands the key straight to `/license/activate`.

`productId` is an MDS record id, not a slug. Store it as
`WC_CUSTOM_INSTALLMENTS_PRO_PRODUCT_ID`, with a fallback that resolves it from the public
`GET /v2/products/wc-custom-installments-pro`.

The `readme.txt` **External services** section must declare this endpoint, the fields sent, and link
the terms and privacy pages — the same shape Joinotify uses for its telemetry consent.

> **Server-side prerequisites** (owners: MDS + site): a demo plan configured at 7 days; the product
> `wc-custom-installments-pro` created and active; the demo e-mail template carrying the ZIP download
> link.

### 7.5 Migrating existing licensees

The current plugin licenses against a **different backend** (`api.meumouse.com/wp-json/license/`)
than MDS. Migration is therefore a two-sided job:

- **Server side (blocking prerequisite):** every active `woo-custom-installments` license must exist
  in MDS under the `wc-custom-installments-pro` product, keyed identically. Until that import is
  done, no client-side migration can succeed.
- **Client side:** `Licensing\Legacy_Migrator` runs once on upgrade. It reads
  `woo_custom_installments_license_key`, calls `$integration->license()->activate( $key )`, and on
  success deletes the two legacy options. On a server rejection it keeps the legacy options
  untouched and raises a notice with a "re-enter your key" link — **never** a silent downgrade.
- Legacy options are **kept, not deleted**, until a successful activation, so a failed migration can
  be retried and a rollback to 5.5.x still works.

### 7.6 Namespace prefixing

The README recommends Strauss. The current plugin ships the SDK unprefixed, as do the other MeuMouse
products, and the loader's version election is explicitly designed to make that safe. **Recommendation:
stay unprefixed**, consistent with the fleet, and revisit only if a third-party conflict appears.

---

## 8. Backend reuse map

| Current file | Destination (`admin/src/…`) | Action |
|---|---|---|
| `inc/Core/Init.php` | `Core/Init.php` | **Refactor** — keep the classmap instantiation, drop illegal-copy checks and the Pro badge |
| `inc/Core/Calculate_Installments.php` | `Engine/Calculate_Installments.php` | **Port** — `License::is_valid()` → `Features::is_available()` |
| `inc/Core/Calculate_Values.php` | `Engine/Calculate_Values.php` | **Port** as is |
| `inc/Core/Discounts.php` | `Engine/Discounts.php` + Pro `Engine/{Gateway,Quantity}_Discounts.php` | **Split** by tier |
| `inc/Core/Interests.php` | `Engine/Interests.php` + Pro `Engine/Gateway_Interests.php` | **Split** by tier |
| `inc/Core/Render_Elements.php` | `Frontend/Render_Elements.php` | **Port** — element list becomes a filterable registry so Pro can append |
| `inc/Views/Components.php` (1,352 lines) | `Frontend/Components.php` | **Split** — settings-markup builders deleted, storefront builders kept |
| `inc/Views/Shortcodes.php` | `Frontend/Shortcodes.php` | **Port** — same 15 tags (§12.4) |
| `inc/Views/Styles.php` | `Frontend/Styles.php` | **Refactor** — emit CSS custom properties instead of literal rules |
| `inc/Core/Schema.php` | Pro `Frontend/Schema.php` | **Move** |
| `inc/Core/Helpers.php` · `Logger.php` | `Core/Helpers.php` · `Core/Logger.php` | **Port**, trimmed |
| `inc/Core/Assets.php` | `Assets/*` | **Rewrite** — Vite manifest resolution replaces hand-maintained `.min` pairs |
| `inc/Core/Ajax.php` | — | **Delete** — every callback becomes a REST route |
| `inc/Admin/Admin_Options.php` | `Admin/Settings/Repository.php` | **Rewrite** |
| `inc/Admin/Default_Options.php` | `Admin/Default_Options.php` | **Port** — `elements_design` moves to `Frontend\Element_Registry` |
| `inc/Admin/Product_Settings.php` | `Admin/Product_Settings.php` + `Rest/Product_*` | **Rewrite** as REST + Vue panel |
| `inc/Views/Settings*.php` (2,582 lines) | — | **Delete** — replaced by `Admin\Settings\Registry` + Vue |
| `inc/API/License.php` | — | **Delete** — the MDS SDK replaces it (§7) |
| `inc/API/Updater.php` | Pro `Licensing/MDS_Integration.php` | **Collapse** into the SDK registration |
| `inc/Compatibility/Legacy_*.php` | `Compatibility/Legacy_*.php` | **Port + extend** (§12.2) |
| `inc/Integrations/{Astra,Woodmart,Xstore,Machic,Ricky,Shoptimizer,Tiered_Pricing_Table,Dynamic_Pricing_Discounts}.php` | `Integrations/` | **Port** as is |
| `inc/Integrations/Rank_Math.php` | Pro `Integrations/Rank_Math.php` | **Move** |
| `inc/Integrations/Elementor*.php` | `Integrations/Elementor/` (2 widgets) + Pro (7 widgets) | **Split** by tier, slugs unchanged (§12.5) |
| `templates/single-product/price.php` | `templates/single-product/price.php` | **Port** — same path, same override mechanism |
| `assets/frontend/*` | `app/src/storefront/` + `assets/` | **Rewrite** (§5) |
| `assets/admin/*` | — | **Delete** — replaced by the Vue app |

**Roughly:** ~4,600 lines of domain PHP port with light edits, ~4,200 lines of settings markup, AJAX
and licensing are deleted, ~1,700 lines move into the Pro add-on.

---

## 9. Internationalization — wp-i18n everywhere, English source

**Every user-facing string, in every layer, is authored in English (en_US) and passed through a
wp-i18n function.** This is a break from the current plugin, whose source locale is pt_BR, and it is
required for a WordPress.org listing.

| Layer | Function | Notes |
|---|---|---|
| PHP runtime | `__()`, `esc_html__()`, `esc_attr__()`, `_n()`, `_x()` | Domain `wc-custom-installments` |
| Settings schema (PHP) | same | Every `label`, `description`, `placeholder` and option label in `Settings\Registry` is wrapped — the schema travels to the browser already translated |
| Onboarding steps (PHP) | same | Titles and summaries live in `Onboarding\Registry`, like Joinotify |
| Vue / TS | `wp.i18n.__()` via `app/src/utils/i18n.ts` | `@wordpress/i18n`, so the `.pot` extractor sees them |
| Storefront JS | same | Strings passed through `wp_localize_script` under an `i18n` key stay available for anything the bundle cannot reach |
| Templates | `esc_html__()` | Theme-overridable files keep the domain |

Rules:

- **Never concatenate translated fragments.** `sprintf()` with numbered placeholders, always.
- **No Portuguese in source.** The existing pt_BR strings become the *pt_BR translation* of each new
  English string — extracted from the current source rather than retranslated, so nothing drifts.
- Script translations are wired with `wp_set_script_translations( $handle, 'wc-custom-installments',
  WC_CUSTOM_INSTALLMENTS_DIR . 'languages' )`, one `.json` per handle, produced by the Joinotify
  `languages/` Node pipeline (`npm run pot`, `translate:ai`, `compile:mo|php|json`).
- Maintained locales: **en_US (source), pt_BR, es_ES**.
- Only `wc-custom-installments.pot` ships in the WordPress.org ZIP; translate.wordpress.org delivers
  the rest. **Exception for the first releases:** ship the compiled pt_BR catalogue too, because the
  entire existing user base is Brazilian and language packs will not exist yet.
- The Pro add-on, distributed outside the directory, always ships compiled catalogues.

---

## 10. Build and release

Port `scripts/` from Joinotify wholesale into both repositories:

| Script | Role |
|---|---|
| `build.mjs` | `app` build → `admin` composer `--no-dev` → translations → staging → ZIP |
| `version.mjs` | Refuses to build when the plugin header, `$plugin_version`, `readme.txt` Stable tag and `package.json` disagree |
| `lint-php.mjs` | Parses every source file with a real PHP 8.1 binary |
| `deploy-svn.mjs` | WordPress.org SVN publishing; nothing is committed without `--commit` |
| `env.mjs` | Reads the Git-ignored `.env` |

The Pro add-on reuses `build.mjs` and `version.mjs`; instead of SVN it uploads the ZIP to MDS
(`POST /v2/uploads/package` → `POST /v2/products/:id/versions` → `POST /v2/versions/:id/publish`),
which is worth scripting as `scripts/release-mds.mjs`.

---

## 11. Documentation deliverables

| File | Content |
|---|---|
| `AGENTS.md` | Rewritten for the new architecture — the single source of truth |
| `DEVELOPERS.md` | The extension API and the full hook reference, including every preserved legacy name |
| `README.md` | Overview, architecture, install, build |
| `readme.txt` | WordPress.org page, incl. **External services** (the trial endpoint) |
| `CHANGELOG.md` | Keep a Changelog, English, starting at `6.0.0`, continuing the 5.x numbering |
| `docs/free-vs-pro.md` | The feature matrix, kept in sync with `Features` |
| `docs/migration-from-5.x.md` | The compatibility contract of §12, for support to hand to customers |
| `examples/wc-custom-installments-extension-example.php` | Runnable third-party extension |

In `docs-meumouse-products`, the existing `docs/woo-custom-installments/` category is **kept and
extended**, not replaced: `installation.md` gains the free + Pro split, `license-activation.md` is
rewritten for the MDS flow, `widgets.md` and `shortcodes.md` gain free/Pro markers, and a new
`migration-5x-to-6x.md` mirrors §12. Add a `wc-custom-installments-pro` sidebar entry only if the two
products end up needing separate navigation.

---

## 12. Compatibility contract — nothing breaks, nothing is lost

This is a **contract, not a best effort**. Every item below is a regression test in `tests/`.

### 12.1 Data

| Data | Rule |
|---|---|
| `woo-custom-installments-setting` (option) | Read by `Core\Migrator`, mapped key-for-key into `wc_custom_installments_settings`. **The legacy option is never deleted**, so a rollback to 5.5.x finds its data intact. |
| `woo_custom_installments_license_key` / `_license_status` | Kept until the MDS activation succeeds (§7.5). |
| Product meta — `__disable_installments`, `__disable_discount_main_price`, `_product_price_on_pix`, `discount_gateway`, `enable_discount_per_quantity`, `enable_discount_per_unit`, `minimum_quantity_discount`, `quantity_discount_amount`, `unit_discount_amount`, `discount_per_quantity_method`, `discount_per_unit_method` | **Names unchanged.** No migration, no rename, ever. |
| `woo_custom_installments_product_price_xml_feed_cache` | Unchanged. |
| Gateway maps — `woo_custom_installments_discounts`, `woo_custom_installments_interests` | Key names preserved inside the settings array. |
| `elements_design` | Structure preserved; new keys are additive. |

Migration is idempotent, versioned by `wc_custom_installments_db_version`, and logs a summary of what
it copied. It runs **before** the onboarding redirect, so a migrated install never sees the wizard
asking for values it already has.

### 12.2 Hooks and filters

The current plugin exposes **54 `Woo_Custom_Installments/*` hooks** plus a legacy bridge covering
**~30 `woo_custom_installments_*` snake_case names**. All of them keep working.

`Compatibility\Hook_Aliases` fires both names at every call site:

```php
// admin/src/Frontend/Render_Elements.php
$classes = Hook_Aliases::apply_filters(
    'WC_Custom_Installments/Price/Group_Classes',   // new canonical name
    'Woo_Custom_Installments/Price/Group_Classes',  // preserved alias, no deprecation notice
    $classes,
    $product
);
```

- The alias is applied **after** the new hook, on the same value, so a listener on either name sees
  and can change the result.
- The 5.x names carry **no deprecation warning**. They are supported, not tolerated — a store owner
  with a snippet in `functions.php` should never see a notice because we renamed our own classes.
- `Compatibility\Legacy_Filters` and `Legacy_Hooks` port as they are, so the pre-5.4 snake_case names
  keep resolving through the existing two-layer chain.
- The full three-column table (new name · 5.x name · pre-5.4 name) is generated into
  `DEVELOPERS.md` from the alias map, so it cannot drift.

Preserved verbatim, among others: `Woo_Custom_Installments/Init/Instance_Classes`,
`Woo_Custom_Installments/Admin/Set_Default_Options`, `Woo_Custom_Installments/Price/*` (11 hooks),
`Woo_Custom_Installments/Installments/*` (8), `Woo_Custom_Installments/Elements/*` (7),
`Woo_Custom_Installments/Product/*` (4), `Woo_Custom_Installments/Cart/*` (3),
`Woo_Custom_Installments/Widgets/*` (3), `Woo_Custom_Installments/Assets/*` (3).

`Woo_Custom_Installments/Admin/Register_Settings_Tabs` and `…/Admin/Settings_Nav_Tabs` are the one
exception: the PHP-rendered tab system they extend no longer exists. They are kept as **no-op
shims that emit a deprecation notice** pointing at `wcci_register_settings_section()`, and
`DEVELOPERS.md` documents the one-to-one replacement.

### 12.3 Templates

`templates/single-product/price.php` keeps its path and its `woocommerce_locate_template` injection,
so a theme override at `wp-content/themes/<theme>/woocommerce/single-product/price.php` keeps
winning exactly as it does today.

### 12.4 Shortcodes

All 15 `woo_custom_installments_*` tags are registered unchanged:
`_modal`, `_card_info`, `_group`, `_table_installments`, `_pix_container`, `_ticket_container`,
`_credit_card_container`, `_debit_card_container`, `_ticket_discount_badge`, `_pix_info`,
`_economy_pix_badge`, `_get_price_on_pix`, `_get_price_on_ticket`, `_get_economy_pix_price`,
`_sale_badge`. New `wc_custom_installments_*` names are added as aliases, never as replacements —
a shortcode sits in post content and cannot be migrated.

### 12.5 Elementor widgets

Widget slugs are stored inside saved Elementor pages; renaming one deletes the widget from the page.
All nine keep their exact `get_name()`: `wci_single_product_price`, `wci_installments_table`,
`wci_credit_card_badges`, `wci_debit_card_badges`, `wci_popup_payment_methods`,
`wci_accordion_payment_methods`, `wci_price_info_box`, `wci_sale_badge`,
`wci_discount_per_quantity_message`.

A Pro widget on a page of a site without the license renders the free fallback (the price group) plus
an editor-only notice — it must not vanish and must not fatal.

### 12.6 Storefront CSS and JS

| Surface | Rule |
|---|---|
| CSS classes | `wci-*` and `woo-custom-installments-*` preserved. New classes are additive. |
| `window.Woo_Custom_Installments` | Kept as an alias of `window.WCCustomInstallments`. |
| `woo_custom_installments_ready` jQuery event | Still triggered when jQuery is present. |
| `wci_front_params` localized object | Kept, alongside the new name. |
| `Woo_Custom_Installments/Assets/Frontend_Params` | Still filters that payload. |

### 12.7 Coexistence

`Compatibility\Conflict_Guard` detects an active `woo-custom-installments` and stops the new plugin
from rendering the price group, showing one admin notice: *"Two versions are active — deactivate the
legacy plugin to continue."* Two plugins hooking `woocommerce_get_price_html` at once is a broken
shop, and silence would be worse than the notice.

### 12.8 Rollout sequence

1. The **free** plugin ships first on WordPress.org. Installed next to 5.5.x it migrates the settings
   and stays inert until the legacy plugin is deactivated.
2. The **Pro** add-on is published to MDS as the successor of the current paid plugin, after the
   server-side license import (§7.5). Its first release migrates the license and deactivates the
   legacy plugin, then shows a summary screen.
3. Legacy `woo-custom-installments` gets a final `5.6.0` whose only job is an upgrade notice. It keeps
   working, untouched, for anyone who postpones.

---

## 13. Phases

Sizing is relative: **S** ≈ up to a week, **M** ≈ 1–2 weeks, **L** ≈ 3–4 weeks, for one developer.

### Phase 0 — Decisions and prerequisites · S

Freeze the feature matrix (§2) and the compatibility contract (§12). Reserve the WordPress.org slug: the directory derives it from the display name (`custom-installments-for-woocommerce`), so ask the reviewers for `wc-custom-installments` when submitting. A `woo-` prefix is no longer accepted for new plugins.
Write the new `AGENTS.md`. **Kick off the MDS server-side work now, because it blocks Phase 8:**
create the `wc-custom-installments-pro` product, issue its API key and ed25519 key pair, configure
the 7-day demo plan, and plan the import of existing licenses from the legacy license server.

*Done when:* the matrix is signed off, the slug is reserved and the MDS product exists.

### Phase 1 — Scaffolding · M

Both repositories with the `/admin` + `/app` layout. `admin/composer.json` + PSR-4, `Core\Init`,
constants, activation/deactivation, HPOS and Cart/Checkout Blocks declarations, WooCommerce
dependency check, `Requires Plugins` on the add-on. Port `scripts/` and the `app/` skeleton (Vite,
Tailwind tokens, Pinia, `mountPage`, `api.js`, `i18n.ts`) and the Joinotify base component library.

*Done when:* both plugins activate cleanly, `npm run build` produces a ZIP with `admin/vendor` and
`app/dist` inside it, `npm run lint:php` passes, and an empty Vue page mounts from a REST bootstrap.

### Phase 2 — Settings backend · M

`Default_Options`, `Settings\Registry` (full schema, every string wrapped, `feature` keys),
`Settings\Repository`, `Core\Features`, `Core\Migrator`, `Rest\Abstract_Route` and the
settings/transfer routes.

*Done when:* the payload round-trips over REST, locked fields cannot be written, and a legacy option
migrates with zero loss (verified by a fixture harness).

### Phase 3 — Settings UI · L

`SettingsPage` shell, `SectionTabs`, `SettingsActionBar`, skeletons, toasts, `FieldRenderer` +
registry, the nine new field components (§4.2), `ProLockOverlay`, `ProUpsellModal`, danger zone,
import/export, system status, debug log.

*Done when:* every free setting is editable, every Pro setting is visible and locked, and saving
persists across a reload.

### Phase 4 — Domain engine · L

Port the engine, the frontend renderers, the styles, the helpers and the price template. Replace
every `License::is_valid()` with `Features::is_available()`. **Ship `Compatibility\Hook_Aliases` in
this phase**, not later — it is what makes the port invisible to existing sites.

*Done when:* a free-tier store renders identically to 5.5.x on simple, variable and out-of-stock
products, in the loop and on the single page, and every hook in §12.2 still fires.

### Phase 5 — Storefront runtime · M

Vanilla-JS rewrite, CSS custom properties, the §12.6 compatibility shim, cart and checkout updates.

*Done when:* popup, variation switching, quantity updates and checkout totals work with no jQuery
dependency of our own, and the legacy JS API still answers.

### Phase 6 — Product panel, shortcodes, integrations · M

Product data panel as a Vue app over REST, all 15 shortcodes, theme integrations, the two free
Elementor widgets.

*Done when:* per-product overrides save over REST, every shortcode renders, and each supported theme
is verified by hand.

### Phase 7 — Onboarding · M

`Core\Onboarding`, `Onboarding\Registry`, the wizard routes, the seven-step Vue flow with the live
preview, the migrated-install shortcut, and the trial form + `POST /trial`.

*Done when:* a fresh activation opens the wizard once, each step persists, a migrated install skips
to the summary, and the trial form issues a working key against the MDS staging environment.

### Phase 8 — Pro add-on · L

MDS SDK registration and constants, `Feature_Provider`, `Legacy_Migrator`, the Vue license/rollback
screen with its REST routes, the Pro settings registration, the Pro engine classes (gateway discounts
and interests, quantity discounts, boleto and Pix badges, sale badge, price range, schema and XML
feed), the seven Pro Elementor widgets, the Pro Vue bundle.

*Done when:* activating the add-on with a valid MDS license unlocks every locked field; deactivating
it degrades to the free tier with no fatal error; an expired license degrades after the grace period;
rollback installs an older version; a legacy 5.5.x license migrates in one step.

### Phase 9 — i18n, docs, compliance · M

`.pot` extraction across PHP **and** Vue, pt_BR and es_ES catalogues, `wp_set_script_translations`
wiring, `AGENTS.md`, `DEVELOPERS.md` (with the generated hook table), `README.md`, `readme.txt` with
the External services section, `docs/`, the example extension, the `docs-meumouse-products` updates,
directory artwork, and a compliance pass: escaping, sanitization, capabilities, nonces, no remote
code, no premature external request, GPL headers.

*Done when:* Plugin Check reports no errors, no untranslated user-facing string remains, and a
self-review against the plugin guidelines is clean.

### Phase 10 — Migration and release · M

`Conflict_Guard`, the migrator hardened against partial and repeated runs, the Pro upgrade routine,
the legacy `5.6.0` notice release, the WordPress.org submission and the MDS release.

*Done when:* a store running 5.5.x upgrades to free + Pro with its settings, its license, its
Elementor pages, its shortcodes, its custom snippets and its storefront output all intact.

**Total: roughly 5–7 months for one developer**, critical path Phases 3, 4 and 8.

---

## 14. Risks

| Risk | Impact | Mitigation |
|---|---|---|
| The MDS license import from the legacy server slips | Phase 8 cannot complete; existing customers cannot activate | Started in Phase 0; `Legacy_Migrator` keeps the old options and fails loudly rather than downgrading |
| WordPress.org rejects the submission over the trial form | Blocks the launch | Explicit consent copy, nothing sent before the click, endpoint declared in `readme.txt`; fallback is a plain link-out, a one-line change |
| The installed base ends up with two plugins hooking the price at once | Broken shops, support load | `Conflict_Guard` (§12.7) plus the migration screen |
| Behavioural drift while porting the engine | Wrong prices — the worst possible bug | Golden-file harnesses in `tests/`: fixed product/settings inputs, expected rendered output, old vs new |
| A hook, shortcode or widget slug quietly changes | Customer snippets and Elementor pages break silently | §12 is a test suite, not a promise; the hook table is generated from the alias map |
| The rewritten storefront JS breaks a customer's custom snippet | Silent breakage for a few stores | Public JS API and CSS classes preserved; announce one release ahead |
| `Views\Components` is 1,352 lines mixing settings and storefront markup | Slow, error-prone port | Split it in Phase 4 as an explicit task |
| Two Vue bundles (free + Pro) drifting apart | Broken Pro fields after a free update | The Pro bundle only registers into `window.WCCustomInstallmentsFieldComponents`; the registry is the contract, and it is versioned |
| Source strings switching from pt_BR to English | Untranslated admin for the existing base until language packs land | Ship the compiled pt_BR catalogue inside the free ZIP for the first releases (§9) |

---

## 15. Open decisions for Phase 0

1. **The feature matrix in §2** — confirm, in particular whether the two free Elementor widgets and
   the theme integrations stay free.
2. **The legacy license import into MDS** — who owns it, and by when? It gates Phase 8.
3. **Trial delivery** — does the demo e-mail carry the Pro ZIP download link, or does the customer
   fetch it from the account panel? The onboarding copy depends on the answer.
4. **Minimum versions** — PHP 8.1 and WooCommerce 8.0 proposed, against the current 7.4 / 6.0. Check
   the installed base before committing.
5. **Repository strategy** — two repositories, or one monorepo with two build targets?
6. **SDK namespace prefixing** — confirm the recommendation in §7.6 (stay unprefixed).
