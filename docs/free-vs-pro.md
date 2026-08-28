# Free and Pro

_Generated from the feature registry by `npm run docs:features`. Do not edit by hand._

Everything not listed below is in the free plugin, on WordPress.org, with no licence and no
account. That includes the whole instalment display, one upfront-payment discount, every payment
badge, all fifteen shortcodes, the theme integrations and the setup wizard.

## What the Pro add-on adds

13 capabilities. Each one is a gate: the setting is visible in the free plugin, marked
Pro, and cannot be saved or take effect until a licence answers for it.

| Capability | Gate |
|---|---|
| Interest per installment | `fee_per_installment` |
| Discounts per payment method | `gateway_discounts` |
| Interest per payment method | `gateway_interests` |
| Discounts per quantity | `quantity_discounts` |
| Bank slip discount | `ticket_discount` |
| Pix savings badge | `pix_economy_badge` |
| Sale badge | `sale_badge` |
| Custom installment text templates | `custom_placeholders` |
| Variable product price range control | `variable_price_range` |
| Discounted price in structured data | `seo_schema` |
| Advanced design controls | `advanced_design` |
| Per-product discount overrides | `product_overrides` |
| Additional Elementor widgets | `elementor_widgets_pro` |

## How the split works

There is one function deciding all of it, `CoreFeatures::is_available()`, and three behaviours
follow from a single `feature` key on a settings field:

1. The field reaches the browser marked `locked`, so a store owner can see what the feature does
   before buying it.
2. The settings repository refuses to write it. The browser is told the field is locked; the
   browser is not what enforces it.
3. The engine asks before applying the behaviour, so a value stored while licensed stops taking
   effect when the licence lapses.

What an element *ships with* is not gated, only changing it. A lapsed licence returns the Pix badge
to its green pill rather than dropping its background — a storefront should look un-upgraded, not
broken.
