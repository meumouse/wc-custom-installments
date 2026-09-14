/**
 * currency.js - format an amount the way WooCommerce would.
 *
 * The 5.x script leaned on accounting.js, which WooCommerce happens to enqueue
 * on some pages and not others; when it was missing the fallback formatted
 * differently from the server and the page showed two spellings of the same
 * price. This implements the WooCommerce format string directly, so there is
 * one answer.
 *
 * @since 6.0.0
 */
import { params } from './params';

/**
 * Format an amount as the store's currency.
 *
 * @since 6.0.0
 * @param {number} amount - The amount.
 * @return {string} The formatted price, markup included.
 */
export function formatPrice(amount) {
  return `<span class="woocommerce-Price-amount amount">${formatAmount(amount)}</span>`;
}

/**
 * Format an amount as the store's currency, as plain text.
 *
 * What 5.x's `getFormattedPrice()` returned - accounting.js output with no
 * wrapper - which snippets write into an existing `.amount` element.
 *
 * @since 6.0.0
 * @param {number} amount - The amount.
 * @return {string} The formatted price.
 */
export function formatAmount(amount) {
  const currency = params.currency || {};
  const decimals = Number.isFinite(Number(currency.decimals)) ? Number(currency.decimals) : 2;
  const value = Number(amount);
  const safe = Number.isFinite(value) ? value : 0;

  const fixed = Math.abs(safe).toFixed(decimals);
  const [whole, fraction] = fixed.split('.');

  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, currency.thousand_separator ?? '.');
  const number = fraction ? `${grouped}${currency.decimal_separator ?? ','}${fraction}` : grouped;
  const sign = safe < 0 ? '-' : '';

  // WooCommerce expresses the layout as a format string: %s is the symbol and
  // %v the number, so a store using "R$ %v" or "%v €" keeps its own order.
  const format = currency.format || '%s%v';
  const body = format.replace('%s', currency.symbol ?? '').replace('%v', number);

  return `${sign}${body}`;
}
