/**
 * params.js - what the backend told the storefront.
 *
 * Read once and defensively: a theme that dequeues the localized object, or a
 * page where the product could not be resolved, must leave the runtime inert
 * rather than throwing on the first property access.
 *
 * @since 6.0.0
 */

const DEFAULTS = {
  ajax_url: '',
  debug_mode: false,
  dev_mode: false,
  license_valid: false,
  active_price_range: 'no',
  product_variation_with_range: false,
  update_price_with_quantity: 'no',
  element_triggers: '',
  tiered_pricing_active: false,
  check_tiered_plugin: false,
  tiered_get_rules: {},
  i18n: {},
  currency: {
    symbol: '',
    decimals: 2,
    decimal_separator: ',',
    thousand_separator: '.',
    format: '%s%v',
  },
  discounts: {},
  installments: {
    max_installments: 0,
    max_installments_no_fee: 0,
    min_installment: 0,
    fee: 0,
    fees: {},
  },
  product: {
    id: 0,
    type: 'simple',
    regular_price: 0,
    sale_price: 0,
    current_price: 0,
  },
};

/**
 * Merge one level deep, so a partial payload keeps the shape callers expect.
 *
 * @since 6.0.0
 * @param {object} defaults - The full shape.
 * @param {object} incoming - What the backend sent.
 * @return {object} The merged parameters.
 */
function merge(defaults, incoming) {
  const result = { ...defaults };

  Object.entries(incoming || {}).forEach(([key, value]) => {
    const isPlainObject =
      value && typeof value === 'object' && !Array.isArray(value) && defaults[key] && typeof defaults[key] === 'object';

    result[key] = isPlainObject ? { ...defaults[key], ...value } : value;
  });

  return result;
}

/**
 * Pairs of keys that carry the same value under the 6.0 and the 5.x name.
 *
 * The backend sends both, but a site that rebuilt part of the payload through
 * the Frontend_Params filter may have written only one of them.
 *
 * @since 6.0.0
 */
const ALIASES = {
  '': [['tiered_pricing_active', 'check_tiered_plugin']],
  i18n: [['best_installments', 'best_installments_sp']],
  currency: [
    ['decimals', 'format_num_decimals'],
    ['decimal_separator', 'format_decimal_sep'],
    ['thousand_separator', 'format_thousand_sep'],
  ],
};

/**
 * Fill in whichever name of an aliased key is missing from the payload.
 *
 * Works on copies: the localized global belongs to the page.
 *
 * @since 6.0.0
 * @param {object} incoming - What the backend sent.
 * @return {object} The payload with both names present.
 */
function withAliases(incoming) {
  const result = { ...(incoming || {}) };

  Object.entries(ALIASES).forEach(([group, pairs]) => {
    if (group && (!result[group] || typeof result[group] !== 'object' || Array.isArray(result[group]))) {
      return;
    }

    const target = group ? { ...result[group] } : result;

    pairs.forEach(([current, legacy]) => {
      if (target[current] === undefined && target[legacy] !== undefined) {
        target[current] = target[legacy];
      } else if (target[legacy] === undefined && target[current] !== undefined) {
        target[legacy] = target[current];
      }
    });

    if (group) {
      result[group] = target;
    }
  });

  return result;
}

// The 5.x name is read as a fallback: a site that customised the payload through
// the old filter still has its values arrive under the old global.
export const params = merge(
  DEFAULTS,
  withAliases(globalThis.wcCustomInstallmentsParams || globalThis.wci_front_params || {}),
);

/**
 * Log a message, when the store asked for them.
 *
 * @since 6.0.0
 * @param {...unknown} args - What to log.
 * @return {void}
 */
export function debug(...args) {
  if (params.debug_mode || params.dev_mode) {
    console.log('[Custom Installments]', ...args);
  }
}
