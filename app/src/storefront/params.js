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
  debug_mode: false,
  dev_mode: false,
  active_price_range: 'no',
  product_variation_with_range: false,
  update_price_with_quantity: 'no',
  element_triggers: '',
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

// The 5.x name is read as a fallback: a site that customised the payload through
// the old filter still has its values arrive under the old global.
export const params = merge(
  DEFAULTS,
  globalThis.wcCustomInstallmentsParams || globalThis.wci_front_params || {},
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
