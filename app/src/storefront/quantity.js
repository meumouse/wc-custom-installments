/**
 * quantity.js - follow the quantity field.
 *
 * @since 1.0.0
 */
import { delegate } from './dom';
import { params } from './params';

/**
 * Register the quantity listener.
 *
 * @since 1.0.0
 * @param {Function} onChange - Called with the new quantity.
 * @return {void}
 */
export function initQuantity(onChange) {
  let timer = null;

  const handler = (event, field) => {
    const quantity = parseInt(field.value, 10) || 1;

    clearTimeout(timer);
    timer = setTimeout(() => onChange(quantity), 150);
  };

  // `input` covers typing and the spinner; `change` covers a theme that sets
  // the value programmatically and dispatches only that.
  delegate('input[name="quantity"]', 'input', handler);
  delegate('input[name="quantity"]', 'change', handler);

  // A tiered-pricing plugin owns the price at that point, so it announces the
  // new one rather than us deriving it from the quantity.
  const jq = globalThis.jQuery;

  if (jq && params.tiered_pricing_active) {
    jq(document).on('tiered_price_update', (event, data) => {
      if (data && data.price) {
        onChange(parseInt(data.quantity, 10) || 1, Number(data.price));
      }
    });
  }
}
