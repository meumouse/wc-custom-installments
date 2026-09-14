/**
 * money.js - amounts written the way the store writes them, in the admin.
 *
 * The storefront has its own formatter (storefront/currency.js), fed by the
 * runtime's parameters; the admin screens read the same facts from their
 * bootstrap's `environment`.
 *
 * @since 6.0.0
 */

/**
 * Format an amount the way the store does.
 *
 * @since 6.0.0
 * @param {number} amount - The amount.
 * @param {{currency_symbol?: string, price_format?: string, decimal_separator?: string, thousand_separator?: string}} [environment] - The store's formatting.
 * @return {string} The formatted price.
 */
export function formatMoney(amount, environment = {}) {
  const decimals = environment.decimal_separator ?? ',';
  const thousands = environment.thousand_separator ?? '.';
  const [whole, fraction] = (Number(amount) || 0).toFixed(2).split('.');
  const number = `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, thousands)}${decimals}${fraction}`;

  // %s is the symbol and %v the number, so "R$ %v" and "%v €" keep their order.
  const format = environment.price_format || '%s%v';

  return format.replace('%s', environment.currency_symbol ?? 'R$').replace('%v', number);
}

/**
 * Round to the cent, the way the server does before multiplying an instalment.
 *
 * @since 6.0.0
 * @param {number} value - The amount.
 * @return {number} The rounded amount.
 */
export function roundCents(value) {
  return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}
