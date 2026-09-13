/**
 * index.js - the storefront runtime.
 *
 * Vanilla by design. jQuery appears only where WooCommerce's own API is jQuery
 * events, which cannot be observed any other way, and the runtime degrades to
 * doing less rather than throwing when it is absent.
 *
 * @since 6.0.0
 */
import './styles/storefront.css';
import { initAccordion, initModal } from './panels';
import { initCheckout } from './checkout';
import { initQuantity } from './quantity';
import { initVariations, currentPrices } from './variations';
import { priceGroup, updateAmounts } from './prices';
import { buildPlan } from './installments';
import { formatPrice } from './currency';
import { debug, params } from './params';

const state = {
  price: currentPrices(),
  quantity: 1,
};

/**
 * Repaint everything from the current state.
 *
 * @since 6.0.0
 * @return {void}
 */
function refresh() {
  updateAmounts(state.price, state.quantity);
}

const runtime = {
  params,
  state,
  buildPlan,
  formatPrice,
  priceGroup,
  updateAmounts,
  refresh,

  /**
   * Boot the runtime.
   *
   * @since 6.0.0
   * @return {void}
   */
  init() {
    initAccordion();
    initModal();

    if ('simple' !== params.product?.type) {
      initVariations((price) => {
        state.price = price;
        refresh();
      });
    }

    if ('yes' === params.update_price_with_quantity) {
      initQuantity((quantity, price) => {
        state.quantity = quantity;

        if (price) {
          state.price = { ...state.price, current: price };
        }

        refresh();
      });
    }

    initCheckout();

    debug('storefront runtime ready', params);
  },
};

/**
 * Announce the runtime, under both names.
 *
 * `window.Woo_Custom_Installments` and the `woo_custom_installments_ready`
 * event are the 5.x public API. Store owners have snippets bound to them, and
 * a rename of ours is not a reason for those to stop working.
 *
 * @since 6.0.0
 * @return {void}
 */
function announce() {
  globalThis.WCCustomInstallments = runtime;
  globalThis.Woo_Custom_Installments = runtime;

  const jq = globalThis.jQuery;

  if (jq) {
    jq(document).trigger('woo_custom_installments_ready');
    jq(document).trigger('wc_custom_installments_ready');
  }

  document.dispatchEvent(new CustomEvent('wc-custom-installments:ready', { detail: runtime }));
}

if ('loading' === document.readyState) {
  document.addEventListener('DOMContentLoaded', () => {
    runtime.init();
    announce();
  });
} else {
  runtime.init();
  announce();
}

export default runtime;
