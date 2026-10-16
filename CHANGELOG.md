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
- Settings tabs an add-on renders: a section with a `component` gets a mount point that the add-on fills with its own app, registered through `window.WCCustomInstallmentsSections`. `?section=<id>` opens a tab directly, and the tabs tighten when there are more than six.
- A menu of its own, Parcelas Customizadas, with the settings screen as its first entry, a one-colour logo as its icon, and `WC_Custom_Installments/Admin/Menu_Position` to move it. The address of the 5.x settings screen redirects there.
- Screens an add-on places in that menu through `WC_Custom_Installments/Admin/Pages`: the plugin draws the header and the panel, and the add-on mounts its own app inside, as in a settings tab. The Pro add-on's licence screen is one, and `WC_Custom_Installments/Admin/License_Url` lets the product screen's licence notice link to it.
- Field components for measurements, tokenised text, per-gateway rates and the interest table, resolved through a registry an add-on can extend.
- `preview.html` and `tests/dump-bootstrap.php`, which render the real admin bundle against a generated fixture with no WordPress and no database.
- Instalment and discount engine ported from the 5.x plugin, with its rounding and both interest formulas intact.
- Campaigns (Pro): a discount, an instalment plan or both for a set of products — every product, a list, categories with their subcategories, tags or brands, with exclusions and a filter for products on sale — from a start to an end in the store's timezone. A product's own discount still wins; among campaigns the higher priority does, per effect. A campaign's discount can be tied to one or more payment methods and is then charged at checkout when the customer pays with any of them, and its plan can set its own count, interest-free count, minimum instalment and interest (a single rate or its own table). Prices change the moment a campaign starts or ends, with no scheduled job in the way; `WC_Custom_Installments/Campaigns/Changed` tells page caches and feeds when to refresh.
- Parcelas Customizadas › Campanhas, in the design system shared with Joinotify: the list filtered by state (running, scheduled, ended, inactive, trash) with search, a switch per campaign and bulk actions; the editor, with product, category, tag and brand pickers searched as you type, the period, the discount and the plan, and a summary beside it counting the products reached and naming the campaigns it overlaps. Without the licence the screen shows what campaigns do and keeps stored ones visible, and they can still be switched off or removed. `preview-campaigns.html` renders it with no WordPress.
- Each row of the Pro interest table takes a fixed amount as well as a percentage, as some payment methods charge ("R$ 2,99 + 3%" for 4x). The fixed amount is added once to the total, the storefront recalculates with it, and the table shows a worked example on a R$ 100,00 product. Rows stored by 5.x read unchanged.
- `Engine\Product_Config`, the one place deciding which discount and which instalment plan a product answers to. The rendered price, the Store API, the storefront runtime and the Pro add-on's checkout fees all ask it, and `WC_Custom_Installments/Product/Discount_Rule` and `WC_Custom_Installments/Product/Installments_Config` let a rule from outside the product join in.
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
- Integrations with Woodmart, Tiered Pricing Table, Dynamic Pricing & Discounts and Role Based Pricing for Woo.
- An Integrations tab in the settings: a grid of cards, one per integration, each with its mark, whether that plugin was found, a switch that turns the whole integration on or off, and a dialog holding everything it decides. It follows the shape Joinotify uses, so the two plugins read as one family. The Elementor and SEO switches moved there from Advanced, under the same setting names. The cards come from `Integrations\Catalog`, which asks each integration to describe itself, so an integration is one file and an add-on appends its own through `WC_Custom_Installments/Integrations/Catalog`.
- A master switch per integration, `integration_<id>_enabled`, on for a new store. Switched off, the plugin ignores that other plugin entirely while keeping what the store configured for it.
- The Dynamic Pricing & Discounts card is named Pricepep, the name that plugin now carries. Only the name changed: the same plugin is detected and the store's settings for it are untouched.
- Per-integration behaviour: build the plan from Tiered Pricing Table's tier price, build it from Dynamic Pricing & Discounts' adjusted price, respect the prices Role Based Pricing for Woo hides from visitors, and - off until a store asks - withhold the instalments or the upfront discount on the products it prices per role or per customer.
- `WC_Custom_Installments/Product/Opted_Out`, which answers the two per-product opt-outs for a rule rather than for one product a store owner ticked. Every element and the Store API follow it, because they all ask the same function.
- `Helpers::is_price_visible()`, the one gate deciding whether the store shows prices at all, with `WC_Custom_Installments/Product/Price_Visible` to close it. A store that hides prices - a closed catalogue, a B2B store pricing only for signed-in customers - no longer has them handed back by an instalment plan, a discount badge, a shortcode, an Elementor widget, the Store API or the numbers the storefront runtime carries in the page source. Role Based Pricing for Woo closes it while its "hide prices for visitors" option is on.
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
- Every option the 5.x settings offered is back:
  - Highlighted installment: "Hide" and "with and without interest".
  - Payment methods: the accordion, plus the "Shortcode" and "Elementor widget" placements, with the shortcode shown beside the setting and beside each payment method.
  - Installment button: the small, normal and large button styles next to the link style.
  - Icons: uploaded image icons.
  - Discounts: quantity discount "per product" mode.
  - Text templates: the `{{ total }}` variable.
  - Master switches: the discount and interest switches hide the options they govern, as they did.
- The element design panel: reorder the price group's elements and set each one's font size, weight, colours, margin, padding, border radius and icon, separately for desktop and mobile, with a live preview and icons picked from the media library.
- A read-only "System status" card in Advanced, listing the WordPress, WooCommerce and server details the 5.x "About" tab showed.
- Font Awesome Free 6, bundled and loaded from the store itself when icons are set to Font Awesome classes (the default), so the price group's icons render on every theme. `WC_Custom_Installments/Assets/Load_Font_Awesome` turns it off for a theme that already ships it.
- Style controls on all nine Elementor widgets again (114 controls, under their 5.x ids, so styles saved in existing pages apply), the price type and texts of the Price Info Box widget, and the same controls injected into the product widgets of Elementor Pro, Woodmart and XStore.
- Rank Math: the discounted price reaches its JSON-LD, as in 5.x.
- Every `woo_custom_installments_*` hook name older than 5.4 answers again, alongside the `Woo_Custom_Installments/…` names, with no deprecation notice.
- Product screen: dependent fields show and hide with their toggle, the quantity fields appear only in per-product mode, and a warning shows when discounts are off store-wide. A "Documentação" link on the Plugins screen.
- Store copy may carry simple HTML again, such as `<strong>`: it is kept through `wp_kses_post()` rather than stripped.
- Colours stored as `rgb()` or `rgba()` by the 5.x colour picker are accepted and shown.
- A colour picker of the plugin's own beside every colour field, in place of the browser's: a saturation and brightness area, hue and opacity sliders, the hex code, suggested colours and the ones used lately, all usable from the keyboard. A colour below full opacity is saved as `rgba()`, as the element design defaults are.
- A "Discounted price" element in the design panel, listed under the product price: its own typography, colours and spacing for the price shown beside the struck-out one. Everything left blank inherits from the product price, so a store that restyled the price sees no change. An add-on element can be a part of another one through a `parent` key.
- The design panel previews each element with its icon, Font Awesome class or uploaded image, and draws the product price in its fullest shape: a variable product on sale, with "starting from", the struck-out price, the discounted price and the sale badge.
- Every measurement selector offers the same units, REM, EM, Pixel and %, in the same order.
- Headless mode, in Advanced: for a store with its own frontend. Nothing renders on the storefront (the price group, the payment methods, the shortcodes, the widgets, the CSS and the runtime), while the admin, the REST routes and the pricing logic behind the cart keep working. `WC_Custom_Installments/Headless/Is_Enabled` forces it from code. It stands down the same classes `Conflict_Guard` does, and the Elementor controls the 5.x plugin injected into other widgets join that list.
- The instalment plan and the discounts in the WooCommerce Store API, under `extensions.wc-custom-installments` of every product and of the cart: the instalment table, the highlighted instalments, the upfront and bank-slip prices, and the store's copy, as minor-unit amounts with the currency.
- A variable product whose range is replaced by "starting from" strikes out its cheapest variation's regular price while that variation is on sale, as a simple product on sale does.
- A "Parcelas Customizadas" tab in the product data panel, carrying the brand icon, with the per-product options grouped under Parcelamento, Descontos and Desconto por quantidade instead of sitting at the end of the General tab. The Pro options show there without the add-on too, disabled and marked Pro, with a notice saying what they need: the add-on, or a licence when it is installed but not activated. Product fields can name their group with a `group` key.
- Each variation of a variable product can set its own discount, in a "Parcelas Customizadas" block of its own: the same as the product (the default, and what every existing variation keeps doing), a discount of its own, tied to a payment method or not, or the store-wide one. A variable product shows the lowest upfront price among its variations, with that variation's savings, and the cart and the price recalculated on a variation or quantity change apply each variation's discount. The fields use the product's 5.x meta keys, so nothing is migrated.
- Card brands the store registers itself (Pro, `custom_card_brands`), in Formas de pagamento › Cadastrar bandeiras: a name and a badge image, typed as an address or picked from the media library. Once saved, each brand joins the credit and debit card brand lists beside the ones the plugin ships, to be switched on in either or both, and prints on the storefront, the shortcodes and the Elementor badge widgets with its own image. A brand keeps its key when it is renamed, so what it was switched on for stays; one that is removed takes its switches with it. When the licence lapses the brands stay listed with their switches locked and leave the storefront, and come back as they were on renewal. Saving the settings now answers with the schema too, so the new switches appear without reloading the screen.

### Fixed

- An AJAX request with a cart that asked for a payment method's title before anything else read a setting — importing orders, for one — ran out of memory and answered with a blank error 500. Listing the payment methods in the settings asked each one for its checkout title, which the Pro add-on annotates by reading a setting, which listed the payment methods again. The settings now name each payment method by the title set for it, without the checkout badges.
- A unit or weight selector inside the design dialog opened behind it and could not be used.
- The design dialog no longer lets the colour fields spill into the next column, and only its content scrolls: the title, the close button and "Done" stay in view.

- Seven settings had a default but no field in the settings schema, so they could never be written and sat at that default forever. Four of them are the "rewrite the displayed price" group, which now has a card of its own; two govern quantity discounts; one was dead and has been removed.
- A store migrated from 5.x no longer loses a value on its first save because the new screen did not offer it. Before this, the save reset the value to the default: the accordion, "both" best installments, the custom-hook, shortcode and widget placements, and per-product quantity discount mode. Selects now accept the earlier spelling of a value and store the current one.
- The Elementor widgets never registered, because the Elementor classes were built after `elementor/init` had already fired.
- Quick edit no longer resets "Disable installments" and "Hide the discount" to off on every product it saves.
- The Single Product Price widget renders the full price template again, so the price changes with the chosen variation inside Elementor product templates.
- The plugin's `single-product/price.php` wins over a theme's WooCommerce copy again, as in 5.x. Without it, the price stopped changing when a variation was picked on themes that ship that template. A theme can still override it in `yourtheme/wc-custom-installments/`, or let its own copy win through `WC_Custom_Installments/Templates/Theme_Override_Wins`.
- The installment button's margins apply to the button and the accordion again, not to the dialog.
- The badges no longer show up in the Price column of the Products list.
- Variation prices: a change to `variation_id` updates the price again; custom triggers respond to touch; the min–max range is no longer repainted when it is kept; and Tiered Pricing Table updates are picked up whenever that plugin is active.
- `window.Woo_Custom_Installments` offers the 5.x methods and parameters again, and `updateAmounts` accepts both argument shapes.
- Woodmart, Tiered Pricing Table and Dynamic Pricing & Discounts behave as in 5.x again:
  - Woodmart: the price group is hidden in the sticky bar, grouped lists, product nav and quick shop.
  - Tiered Pricing Table: the tier price at the rule's minimum quantity, without that plugin's own price wrapper.
  - Dynamic Pricing & Discounts: the lowest variation price for variable products.
- The installment table's default row reads "3x de R$ 10,00 sem juros" again. The interest-per-installment table starts after the interest-free installments.
- Instalments, their totals and the cart's upfront discount round a half cent the same way on every PHP version, the way 5.x did on PHP 8.3 and earlier. PHP 8.4 changed `round()`, so an amount such as 97 + 3% + R$ 2,99 over 4 (R$ 25,725) showed as 4x R$ 25,72 = R$ 102,88 on newer hosts and 4x R$ 25,73 = R$ 102,92 elsewhere. The storefront now rounds the same way when the price changes, where it could also show an instalment a cent off the total beside it.
- Leaving a step of the setup wizard no longer switches off every setting the step does not carry. Finishing the instalment step turned off instalments on every product, the instalments in the cart, the Elementor widgets and every other on/off setting the wizard does not ask about. Importing a settings file exported by an earlier version likewise leaves a setting the file does not carry as it is, instead of switching it off. Writers carrying only some settings go through `Settings\Repository::patch_settings()`, which applies the same feature gate.
