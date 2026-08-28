/**
 * prices.js - repaint the price group when the price changes.
 *
 * Everything here writes into markup the server already rendered. That is
 * deliberate: the server decides what exists, this only updates the numbers, so
 * a display rule can never be contradicted by the browser.
 *
 * @since 1.0.0
 */
import { all, one, setAmount } from './dom';
import { formatPrice } from './currency';
import { buildPlan } from './installments';
import { debug, params } from './params';

/**
 * The element holding the price group for the current product state.
 *
 * A variable product with the range replaced renders into its own container,
 * and which of the two is live depends on whether a variation is selected.
 *
 * @since 1.0.0
 * @return {Element|null} The group to update.
 */
export function priceGroup() {
  const container = one('.woo-custom-installments-price-container');

  if (container && container.classList.contains('active') && container.innerHTML.trim() !== '') {
    return container;
  }

  return one('.woo-custom-installments-group');
}

/**
 * Take a percentage off an amount.
 *
 * @since 1.0.0
 * @param {number} percentage - The percentage.
 * @param {number} price - The amount.
 * @return {number} What remains.
 */
function afterPercentage(percentage, price) {
  return price - price * (Number(percentage) / 100);
}

/**
 * Repaint every price in the group.
 *
 * @since 1.0.0
 * @param {{regular: number, current: number}} price - The unit prices.
 * @param {number} quantity - How many are being bought.
 * @return {void}
 */
export function updateAmounts(price, quantity = 1) {
  const group = priceGroup();

  if (!group) {
    return;
  }

  const count = 'yes' === params.update_price_with_quantity ? Math.max(1, parseInt(quantity, 10) || 1) : 1;
  const current = (Number(price.current) || 0) * count;
  const regular = (Number(price.regular) || 0) * count;

  debug('updating amounts', { current, regular, quantity: count });

  updateMainPrice(group, { regular, current });
  updateTable(current);

  const discounts = params.discounts || {};
  let discounted = null;

  // A per-unit product discount overrides the store-wide one, the same
  // precedence the server applies.
  if ('yes' === discounts.enable_discount_per_unit && discounts.unit_discount_amount) {
    discounted =
      'percentage' === discounts.discount_per_unit_method
        ? afterPercentage(discounts.unit_discount_amount, Number(price.current))
        : Number(price.current) - Number(discounts.unit_discount_amount);
  } else if (discounts.pix_discount) {
    discounted =
      'percentage' === discounts.pix_discount_method
        ? afterPercentage(discounts.pix_discount, Number(price.current))
        : Number(price.current) - Number(discounts.pix_discount);
  }

  if (null !== discounted) {
    const total = discounted * count;

    setAmount(all('.woo-custom-installments-offer', group), formatPrice(total));
    setAmount(all('.pix-method-container'), formatPrice(total));
    setAmount(all('.woo-custom-installments-economy-pix-badge'), formatPrice(current - total));
  }

  if (discounts.slip_bank_discount) {
    const slip =
      'percentage' === discounts.slip_bank_method
        ? afterPercentage(discounts.slip_bank_discount, Number(price.current)) * count
        : (Number(price.current) - Number(discounts.slip_bank_discount)) * count;

    setAmount(all('.woo-custom-installments-ticket-discount', group), formatPrice(slip));
    setAmount(all('.woo-custom-installments-ticket-section'), formatPrice(slip));
  }
}

/**
 * Repaint the product price itself.
 *
 * @since 1.0.0
 * @param {Element} group - The price group.
 * @param {{regular: number, current: number}} price - The prices.
 * @return {void}
 */
function updateMainPrice(group, price) {
  const struck = all('.woo-custom-installments-price.has-discount', group);

  if (struck.length) {
    if (price.regular) {
      setAmount(struck, formatPrice(price.regular));
    }

    if (price.current) {
      setAmount(all('.woo-custom-installments-price.sale-price', group), formatPrice(price.current));
    }

    return;
  }

  if (price.current) {
    setAmount(all('.woo-custom-installments-price', group), formatPrice(price.current));
  }
}

/**
 * Rebuild the instalment table and the highlighted instalment.
 *
 * @since 1.0.0
 * @param {number} price - The price to build the plan from.
 * @return {void}
 */
function updateTable(price) {
  const body = one('.woo-custom-installments-table tbody');
  const template = body?.dataset.defaultText || '';
  const plan = buildPlan(price);
  const i18n = params.i18n || {};

  if (body && template) {
    body.innerHTML = plan
      .map((row) => {
        const text = fill(template, row, i18n);

        return `<tr class="${row.hasFee ? 'fee-included' : 'no-fee'}"><th class="final-text">${text}</th><th class="final-price">${formatPrice(row.total)}</th></tr>`;
      })
      .join('');
  }

  const group = priceGroup();

  if (!group) {
    return;
  }

  // The highlighted instalment is the largest of its kind, which is the last
  // row of that kind in the plan.
  const best = {
    'no-fee': [...plan].reverse().find((row) => !row.hasFee),
    'fee-included': [...plan].reverse().find((row) => row.hasFee),
  };

  const bestTemplate = i18n.best_installments || template;

  if (!bestTemplate) {
    return;
  }

  if (best['no-fee']) {
    const target = one('.woo-custom-installments-details.best-value.no-fee', group);

    if (target) {
      target.innerHTML = fill(bestTemplate, best['no-fee'], i18n);
    }
  }

  if (best['fee-included']) {
    const target = one('.woo-custom-installments-details-with-fee .best-value.fee-included', group);

    if (target) {
      target.innerHTML = fill(bestTemplate, best['fee-included'], i18n);
    }
  }
}

/**
 * Put one instalment into a copy template.
 *
 * The token names are Portuguese because they are in the wild: store owners
 * have them written into their settings, and the server uses the same ones.
 *
 * @since 1.0.0
 * @param {string} template - The copy template.
 * @param {object} row - One instalment.
 * @param {object} i18n - The interest suffixes.
 * @return {string} The rendered copy.
 */
function fill(template, row, i18n) {
  return template
    .replace('{{ parcelas }}', row.count)
    .replace('{{ valor }}', formatPrice(row.value))
    .replace('{{ total }}', formatPrice(row.total))
    .replace('{{ juros }}', row.hasFee ? i18n.with_fee_label || '' : i18n.without_fee_label || '');
}
