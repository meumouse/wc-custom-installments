/**
 * money.js - amounts written the way the store writes them, in the admin.
 *
 * The storefront has its own formatter (storefront/currency.js), fed by the
 * runtime's parameters; the admin screens read the same facts from their
 * bootstrap's `environment`. Both round through roundMoney(), the mirror of the
 * server's Calculate_Values::round_money(). The storefront carries its own copy
 * of it (storefront/rounding.js) so it loads as one file, with no chunk shared
 * with these bundles; the three must stay in step.
 *
 * @since 6.0.0
 */

/**
 * Ten to a power, as the nearest double.
 *
 * Parsed from a literal, which is correctly rounded, so every power up to 1e22
 * is exact; `10 ** n` is left to the engine.
 *
 * @since 6.0.0
 * @param {number} power - The power.
 * @return {number} The power of ten.
 */
function powerOfTen(power) {
  return Number(`1e${power}`);
}

/**
 * Round to a whole number, a half away from zero.
 *
 * @since 6.0.0
 * @param {number} value - The amount.
 * @return {number} The whole number.
 */
function roundHalfUp(value) {
  return value >= 0 ? Math.floor(value + 0.5) : Math.ceil(value - 0.5);
}

/**
 * Round an amount the way the server does, on every PHP version.
 *
 * The same steps as Calculate_Values::round_money(), which are PHP 8.1-8.3's
 * round(): first to 15 significant digits, all a double holds without noise,
 * then to the decimals asked for. 25.724999999999998 (97 + 3% + R$ 2,99 over 4)
 * becomes 25.725, so 25.73. Multiplication, division and floor are exact to the
 * bit in both languages, so the two cannot disagree.
 *
 * @since 6.0.0
 * @param {number} value - The amount.
 * @param {number} [decimals=2] - Decimal places to keep.
 * @return {number} The rounded amount.
 */
export function roundMoney(value, decimals = 2) {
  const number = Number(value);
  const places = Math.max(0, Math.trunc(Number(decimals)) || 0);

  if (number === 0 || !Number.isFinite(number)) {
    return Number.isFinite(number) ? 0 : number;
  }

  // floor(log10(|number|)), read off exact powers of ten as the server does.
  const magnitude = Math.abs(number);
  let exponent = -8;

  if (magnitude < 1e-8 || magnitude > 1e22) {
    exponent = Math.floor(Math.log10(magnitude));
  } else {
    while (exponent < 22 && magnitude >= powerOfTen(exponent + 1)) {
      exponent += 1;
    }
  }

  const precision = 14 - exponent;
  let scaled;

  if (precision > places && precision - 15 < places) {
    // Fifteen significant digits, then the decimal point moved back.
    scaled = precision >= 0 ? number * powerOfTen(precision) : number / powerOfTen(-precision);
    scaled = roundHalfUp(scaled) / powerOfTen(precision - places);
  } else {
    scaled = number * powerOfTen(places);

    // Past what a double can tell apart there is nothing to round.
    if (Math.abs(scaled) >= 1e15) {
      return number;
    }
  }

  const rounded = roundHalfUp(scaled) / powerOfTen(places);

  // A negative amount that rounds to nothing is nothing, not "-0".
  return rounded === 0 ? 0 : rounded;
}

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
  const [whole, fraction] = roundMoney(Number(amount) || 0).toFixed(2).split('.');
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
  return roundMoney(value, 2);
}
