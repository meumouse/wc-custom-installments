/**
 * integrationMarks.js
 *
 * The mark each integration card shows, by the slug the schema gives it.
 *
 * Imported through the `@integrations` alias rather than built from a URL at
 * runtime: a path hard-coded to /wp-content/plugins breaks on every install
 * with a moved content directory, and the previews have no WordPress to ask.
 * An integration with no mark here falls back to its initial, which is what an
 * add-on's card gets until it ships one.
 *
 * @since 6.0.0
 */
import dynamicPricingDiscounts from '@integrations/dynamic-pricing-discounts.webp';
import elementor from '@integrations/elementor.svg';
import rankMath from '@integrations/rank-math.jpg';
import roleBasedPricing from '@integrations/role-based-pricing.png';
import tieredPricingTable from '@integrations/tiered-pricing-table.png';

const MARKS = {
  'dynamic-pricing-discounts': dynamicPricingDiscounts,
  elementor,
  'rank-math': rankMath,
  'role-based-pricing': roleBasedPricing,
  'tiered-pricing-table': tieredPricingTable,
};

/**
 * The mark of one integration.
 *
 * @since 6.0.0
 * @param {string} slug - The integration's slug.
 * @return {string} The bundled URL, or an empty string when there is none.
 */
export function markFor(slug) {
  return MARKS[String(slug || '')] || '';
}
