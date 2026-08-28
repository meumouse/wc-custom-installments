/**
 * checkout.js - recalculate the totals when the payment method changes.
 *
 * The discount depends on which gateway is selected, so the totals are stale
 * the moment the customer picks another one.
 *
 * @since 1.0.0
 */

/**
 * Ask WooCommerce to recalculate.
 *
 * @since 1.0.0
 * @return {void}
 */
export function initCheckout() {
  const jq = globalThis.jQuery;

  if (!jq) {
    return;
  }

  // `update_checkout` is a jQuery event WooCommerce listens for; there is no
  // DOM equivalent to dispatch instead.
  jq(document.body).on('change', 'input[name="payment_method"]', () => {
    jq(document.body).trigger('update_checkout');
  });
}
