# Free and Pro

_Generated from the feature registry by `npm run docs:features`. Do not edit by hand._

Everything not listed below is in the free plugin, on WordPress.org, with no licence and no
account. That includes the whole instalment display, one upfront-payment discount, every payment
badge, all fifteen shortcodes, the theme integrations and the setup wizard.

## What the Pro add-on adds

14 capabilities. Each one is a gate: the setting is visible in the free plugin, marked
Pro, and cannot be saved or take effect until a licence answers for it.

| Capability | Gate |
|---|---|
| Juros por parcela | `fee_per_installment` |
| Descontos por forma de pagamento | `gateway_discounts` |
| Juros por forma de pagamento | `gateway_interests` |
| Descontos por quantidade | `quantity_discounts` |
| Desconto no boleto | `ticket_discount` |
| Selo de economia no Pix | `pix_economy_badge` |
| Selo de promoção | `sale_badge` |
| Modelos de texto de parcelas personalizados | `custom_placeholders` |
| Controle da faixa de preço de produtos variáveis | `variable_price_range` |
| Preço com desconto nos dados estruturados | `seo_schema` |
| Controles avançados de design | `advanced_design` |
| Descontos personalizados por produto | `product_overrides` |
| Widgets adicionais do Elementor | `elementor_widgets_pro` |
| Campanhas de desconto e parcelamento | `campaigns` |

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
