/**
 * prices.js - repaint the price group when the price changes.
 *
 * Everything here writes into markup the server already rendered. That is
 * deliberate: the server decides what exists, this only updates the numbers, so
 * a display rule can never be contradicted by the browser.
 *
 * @since 6.0.0
 */
import { all, one, setAmount } from './dom';
import { formatPrice } from './currency';
import { buildPlan } from './installments';
import { debug, params } from './params';

/**
 * Where the payment methods block repeats a discounted amount.
 *
 * The dialog, the collapsible panel and the Pix section a shortcode may place
 * anywhere on the product page. Scoped rather than page-wide, because every
 * product card further down the page carries badges of its own.
 *
 * @since 6.0.0
 */
const PANELS = ['.wci-popup-body', '.wci-accordion-content', '.woo-custom-installments-pix-section'];

/**
 * Read a price in either shape the public API has accepted.
 *
 * 6.0 passes `{regular, current}`; 5.x passed `{old_price, new_price}`, and
 * customer snippets still call `updateAmounts()` that way.
 *
 * @since 6.0.0
 * @param {object} price - The unit prices, in either shape.
 * @param {{regular: number, current: number}} [fallback] - Used for what is absent.
 * @return {{regular: number, current: number}} The unit prices.
 */
export function normalizePrice(price, fallback = { regular: 0, current: 0 }) {
  const source = price && typeof price === 'object' ? price : {};
  const pick = (...values) => values.find((value) => value !== undefined && value !== null && value !== '');

  return {
    regular: Number(pick(source.regular, source.old_price, fallback.regular)) || 0,
    current: Number(pick(source.current, source.new_price, fallback.current)) || 0,
  };
}

/**
 * Whether the page-level group shows a min-max range left in place.
 *
 * @since 6.0.0
 * @return {boolean} True for a variable product whose range was not replaced.
 */
function showsRange() {
  return 'simple' !== params.product?.type && Boolean(params.product_variation_with_range) && 'yes' !== params.active_price_range;
}

/**
 * The element holding the price group for the current product state.
 *
 * A variable product with the range replaced renders into its own container,
 * and which of the two is live depends on whether a variation is selected.
 * With the range left in place, the page-level group shows two prices one
 * number cannot stand for, so the group that follows the customer is the one
 * WooCommerce prints for the chosen variation - and there is none until a
 * variation is chosen.
 *
 * @since 6.0.0
 * @return {Element|null} The group to update.
 */
export function priceGroup() {
  const container = one('.woo-custom-installments-price-container');

  if (container && container.classList.contains('active') && container.innerHTML.trim() !== '') {
    return container;
  }

  if (showsRange()) {
    return one('.woocommerce-variation-price .woo-custom-installments-group');
  }

  return one('.woo-custom-installments-group');
}

/**
 * Take a percentage off an amount.
 *
 * @since 6.0.0
 * @param {number} percentage - The percentage.
 * @param {number} price - The amount.
 * @return {number} What remains.
 */
export function afterPercentage(percentage, price) {
  return Number(price) - Number(price) * (Number(percentage) / 100);
}

/**
 * Repaint every price in the group.
 *
 * @since 6.0.0
 * @param {{regular: number, current: number}|{old_price: number, new_price: number}} price - The unit prices.
 * @param {number} quantity - How many are being bought.
 * @param {object|null} [rules] - The discounts to apply; the page's when omitted.
 * @return {void}
 */
export function updateAmounts(price, quantity = 1, rules = null) {
  const group = priceGroup();

  if (!group) {
    return;
  }

  const unit = normalizePrice(price);
  const count = 'yes' === params.update_price_with_quantity ? Math.max(1, parseInt(quantity, 10) || 1) : 1;
  const current = unit.current * count;
  const regular = unit.regular * count;

  debug('updating amounts', { current, regular, quantity: count });

  updateMainPrice(group, { regular, current });
  updateTable(current);

  const discounts = rules || params.discounts || {};
  let discounted = null;

  // A per-unit product discount overrides the store-wide one, the same
  // precedence the server applies.
  if ('yes' === discounts.enable_discount_per_unit && discounts.unit_discount_amount) {
    discounted =
      'percentage' === discounts.discount_per_unit_method
        ? afterPercentage(discounts.unit_discount_amount, unit.current)
        : unit.current - Number(discounts.unit_discount_amount);
  } else if (discounts.pix_discount) {
    discounted =
      'percentage' === discounts.pix_discount_method
        ? afterPercentage(discounts.pix_discount, unit.current)
        : unit.current - Number(discounts.pix_discount);
  }

  if (null !== discounted) {
    const total = discounted * count;

    updatePixDiscount(total, group);
    updateEconomy(current - total, group);
  }

  if (discounts.slip_bank_discount) {
    const slip =
      'percentage' === discounts.slip_bank_method
        ? afterPercentage(discounts.slip_bank_discount, unit.current) * count
        : (unit.current - Number(discounts.slip_bank_discount)) * count;

    updateSlipBank(slip, group);
  }
}

/**
 * Repaint the product price itself.
 *
 * @since 6.0.0
 * @param {Element|null} group - The price group.
 * @param {{regular: number, current: number}|{old_price: number, new_price: number}} price - The prices.
 * @return {void}
 */
export function updateMainPrice(group, price) {
  if (!group) {
    return;
  }

  const amounts = normalizePrice(price);
  const struck = all('.woo-custom-installments-price.has-discount', group);

  if (struck.length) {
    if (amounts.regular) {
      setAmount(struck, formatPrice(amounts.regular));
    }

    if (amounts.current) {
      setAmount(all('.woo-custom-installments-price.sale-price', group), formatPrice(amounts.current));
    }

    return;
  }

  if (amounts.current) {
    setAmount(all('.woo-custom-installments-price', group), formatPrice(amounts.current));
  }
}

/**
 * Write the upfront (Pix) price into the group and the payment methods block.
 *
 * @since 6.0.0
 * @param {number} amount - The discounted total.
 * @param {Element|null} [group] - The price group; the live one when omitted.
 * @return {void}
 */
export function updatePixDiscount(amount, group = priceGroup()) {
  const markup = formatPrice(amount);

  if (group) {
    setAmount(all('.woo-custom-installments-offer', group), markup);
  }

  setAmount(all('.pix-method-container'), markup);
}

/**
 * Write the bank slip price into the group and the payment methods block.
 *
 * @since 6.0.0
 * @param {number} amount - The discounted total.
 * @param {Element|null} [group] - The price group; the live one when omitted.
 * @return {void}
 */
export function updateSlipBank(amount, group = priceGroup()) {
  const markup = formatPrice(amount);

  if (group) {
    setAmount(all('.woo-custom-installments-ticket-discount', group), markup);
  }

  setAmount(all('.woo-custom-installments-ticket-section'), markup);
}

/**
 * Write what the customer saves into the group and the payment methods block.
 *
 * @since 6.0.0
 * @param {number} amount - The amount saved.
 * @param {Element|null} [group] - The price group; the live one when omitted.
 * @return {void}
 */
export function updateEconomy(amount, group = priceGroup()) {
  const markup = formatPrice(amount);
  const badges = PANELS.flatMap((panel) => all(`${panel} .woo-custom-installments-economy-pix-badge`));

  if (group) {
    badges.push(...all('.woo-custom-installments-economy-pix-badge', group));
  }

  setAmount([...new Set(badges)], markup);
}

/**
 * Rebuild the instalment table and the highlighted instalment.
 *
 * @since 6.0.0
 * @param {number} price - The price to build the plan from.
 * @return {void}
 */
export function updateTable(price) {
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

  const bestTemplate = i18n.best_installments || i18n.best_installments_sp || template;

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
 * @since 6.0.0
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
