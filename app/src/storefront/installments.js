/**
 * installments.js - rebuild the instalment plan in the browser.
 *
 * This mirrors Engine\Calculate_Installments, and the two have to agree: the
 * server renders the plan on load, this renders it again when the price
 * changes, and a customer watching the number move must not see it land
 * somewhere the server would not have put it.
 *
 * @since 6.0.0
 */
import { params } from './params';
import { roundMoney } from './rounding';

/**
 * The interest rate applying to one instalment count.
 *
 * @since 6.0.0
 * @param {number} count - Instalment count.
 * @return {number} The rate, as a percentage.
 */
function feeFor(count) {
  const { fees = {}, fee = 0 } = params.installments || {};
  const specific = fees[count] ?? fees[String(count)];

  const rate = specific !== undefined && specific !== '' ? specific : fee;

  return parseFloat(String(rate).replace(',', '.')) || 0;
}

/**
 * The fixed amount one instalment count adds to the total.
 *
 * Only a per-instalment table carries one ("R$ 2,99 + 3%").
 *
 * @since 6.0.0
 * @param {number} count - Instalment count.
 * @return {number} The amount.
 */
function fixedFor(count) {
  if (!params.fee_per_installment) {
    return 0;
  }

  const { fees_fixed: fixed = {} } = params.installments || {};
  const amount = fixed[count] ?? fixed[String(count)] ?? 0;

  return Math.max(0, parseFloat(String(amount).replace(',', '.')) || 0);
}

/**
 * Build every instalment the store offers for a price.
 *
 * @since 6.0.0
 * @param {number} price - The base amount.
 * @return {Array<object>} One entry per instalment.
 */
export function buildPlan(price) {
  const config = params.installments || {};
  const max = parseInt(config.max_installments, 10) || 0;
  const withoutFee = parseInt(config.max_installments_no_fee, 10) || 0;
  const minimum = parseFloat(config.min_installment) || 0;
  const base = Number(price) || 0;
  const plan = [];

  for (let count = 1; count <= max; count += 1) {
    const rate = feeFor(count);
    const fixed = fixedFor(count);
    const free = count <= withoutFee || (rate === 0 && fixed === 0);
    let value;
    let total;

    if (free) {
      value = base / count;
      total = round(value) * count;
    } else {
      const percentage = rate / 100;

      // A per-instalment table is a flat surcharge on the price, plus the
      // row's fixed amount once; a single global rate compounds. Same split
      // as the server.
      if (params.fee_per_installment) {
        value = (base + base * percentage + fixed) / count;
      } else {
        const power = (1 + percentage) ** count;

        value = (base * percentage * power) / (power - 1);
      }

      // Either way the total is what the customer is billed: the instalment
      // rounded to the cent, times the count.
      total = round(value) * count;
    }

    // The single payment is always offered: it is the price itself.
    if (count !== 1 && value < minimum) {
      continue;
    }

    plan.push({
      count,
      value,
      total,
      hasFee: !free,
    });
  }

  return plan;
}

/**
 * Round to the cent, the way the server does before multiplying.
 *
 * @since 6.0.0
 * @param {number} value - The amount.
 * @return {number} The rounded amount.
 */
function round(value) {
  return roundMoney(value, 2);
}
