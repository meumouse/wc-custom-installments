/**
 * quantity.js - follow the quantity field, and a tiered-pricing plugin's price.
 *
 * @since 6.0.0
 */
import { delegate } from './dom';
import { debug, params } from './params';

let quantityBound = false;
let tieredBound = false;

/**
 * Whether the product on the page has tier rules of its own.
 *
 * When it does, the tier plugin announces a new price for every quantity, and
 * recalculating from the old unit price first would only flash a wrong total.
 *
 * @since 6.0.0
 * @return {boolean} True when the payload lists tier rules.
 */
export function hasTierRules() {
  const rules = params.tiered_get_rules;

  if (!rules || typeof rules !== 'object') {
    return false;
  }

  return Object.keys(rules).length > 0;
}

/**
 * Whether a tiered-pricing plugin is active on the site.
 *
 * @since 6.0.0
 * @return {boolean} True when the backend reported it.
 */
export function tieredPricingActive() {
  return Boolean(params.tiered_pricing_active || params.check_tiered_plugin);
}

/**
 * Register the quantity listener.
 *
 * Safe to call more than once; the listener is registered the first time.
 *
 * @since 6.0.0
 * @param {Function} onChange - Called with the new quantity, and whether to repaint.
 * @return {void}
 */
export function initQuantity(onChange) {
  if (quantityBound) {
    return;
  }

  quantityBound = true;

  let timer = null;

  const handler = (event, field) => {
    const quantity = parseInt(field.value, 10) || 1;

    clearTimeout(timer);
    timer = setTimeout(() => onChange(quantity, !(tieredPricingActive() && hasTierRules())), 150);
  };

  // `input` covers typing and the spinner; `change` covers a theme that sets
  // the value programmatically and dispatches only that.
  delegate('input[name="quantity"]', 'input', handler);
  delegate('input[name="quantity"]', 'change', handler);
}

/**
 * Follow the price a tiered-pricing plugin announces.
 *
 * Registered whenever that plugin is active, whether or not the store has the
 * price follow the quantity: the tier plugin changes the unit price itself,
 * and the instalments have to be built from the price being charged.
 *
 * @since 6.0.0
 * @param {Function} onChange - Called with the quantity and the new unit price.
 * @return {void}
 */
export function initTieredPricing(onChange) {
  if (tieredBound) {
    return;
  }

  const jq = globalThis.jQuery;

  // A jQuery event, triggered with `jQuery.trigger`; nothing else receives it.
  if (!jq) {
    debug('jQuery is absent; tiered pricing updates cannot be observed.');

    return;
  }

  tieredBound = true;

  let timer = null;

  jq(document).on('tiered_price_update', (event, data) => {
    if (!data || !data.price) {
      return;
    }

    clearTimeout(timer);
    timer = setTimeout(() => onChange(parseInt(data.quantity, 10) || 1, Number(data.price)), 100);
  });
}
