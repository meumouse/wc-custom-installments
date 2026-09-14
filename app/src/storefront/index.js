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
import { bindModal, initAccordion, initModal, toggleAccordion } from './panels';
import { initCheckout } from './checkout';
import { initQuantity, initTieredPricing, tieredPricingActive } from './quantity';
import {
  applyVariation,
  clearVariationPrice,
  currentPrices,
  initVariations,
  preventDuplicatePrices,
  replacesRange,
  showVariationPrice,
} from './variations';
import {
  afterPercentage,
  normalizePrice,
  priceGroup,
  updateAmounts,
  updateEconomy,
  updateMainPrice,
  updatePixDiscount,
  updateSlipBank,
  updateTable,
} from './prices';
import { buildPlan } from './installments';
import { formatAmount, formatPrice } from './currency';
import { debug, params } from './params';

const state = {
  price: currentPrices(),
  quantity: 1,
  discounts: null,
};

let booted = false;
let debounceTimer = null;

/**
 * Repaint everything from the current state.
 *
 * @since 6.0.0
 * @return {void}
 */
function refresh() {
  updateAmounts(state.price, state.quantity, state.discounts);
}

/**
 * The discounts that apply to the chosen variation.
 *
 * The server hands each variation its own per-unit discount, already resolved
 * against its parent; the store-wide ones (upfront and bank slip) stay the
 * page's. Null when the variation carries none, and the page's apply.
 *
 * @since 6.0.0
 * @param {object} [variation] - WooCommerce's variation data.
 * @return {object|null} The discounts, or null.
 */
function variationDiscounts(variation) {
  const own = variation && variation.wcci_discount;

  return own && 'object' === typeof own ? { ...(params.discounts || {}), ...own } : null;
}

/**
 * Whether the store has the price follow the quantity field.
 *
 * @since 6.0.0
 * @return {boolean} True when the setting is on.
 */
function quantityApplies() {
  return 'yes' === params.update_price_with_quantity;
}

/**
 * Take on the prices of the variation the customer chose.
 *
 * With the range replaced, the chosen variation's group is repainted. With the
 * range left in place, the variation's price is WooCommerce's to show, as it
 * was in 5.x, and the page-level range is never written over; only a quantity
 * above one - which WooCommerce's variation markup does not account for - is
 * carried into the variation's own group.
 *
 * @since 6.0.0
 * @param {{regular: number, current: number}} price - The variation's unit prices.
 * @param {object} [variation] - WooCommerce's variation data.
 * @return {void}
 */
function onVariation(price, variation) {
  state.price = price;
  state.discounts = variationDiscounts(variation);

  if (replacesRange() || (quantityApplies() && state.quantity > 1)) {
    refresh();
  }
}

/**
 * Go back to the product's own prices when the choice is cleared.
 *
 * @since 6.0.0
 * @return {void}
 */
function onVariationReset() {
  state.price = currentPrices();
  state.discounts = null;
}

/**
 * Follow the quantity field.
 *
 * @since 6.0.0
 * @param {number} quantity - The new quantity.
 * @param {boolean} [repaint] - False when a tier plugin is about to announce the price.
 * @return {void}
 */
function onQuantity(quantity, repaint = true) {
  state.quantity = quantity;

  if (repaint) {
    refresh();
  }
}

/**
 * Follow the unit price a tiered-pricing plugin announced.
 *
 * @since 6.0.0
 * @param {number} quantity - The quantity the price applies to.
 * @param {number} price - The new unit price.
 * @return {void}
 */
function onTieredPrice(quantity, price) {
  state.quantity = quantity;
  state.price = { ...state.price, current: price };

  refresh();
}

/**
 * The public runtime.
 *
 * The 6.0 surface (`params`, `state`, `buildPlan`, `formatPrice`, `priceGroup`,
 * `updateAmounts`, `refresh`, `init`) sits beside every method 5.x exposed on
 * the same global, each a thin wrapper over the 6.0 runtime, because customer
 * snippets call them by those names.
 *
 * @since 6.0.0
 */
const runtime = {
  params,
  state,
  buildPlan,
  formatPrice,
  formatAmount,
  priceGroup,
  refresh,

  /**
   * Repaint the price group for a price and a quantity.
   *
   * Accepts `{regular, current}` (6.0) and `{old_price, new_price}` (5.x);
   * a missing price falls back to the one the runtime is tracking.
   *
   * @since 6.0.0
   * @param {object} [price] - The unit prices, in either shape.
   * @param {number} [quantity] - How many are being bought.
   * @return {void}
   */
  updateAmounts(price, quantity = 1) {
    updateAmounts(normalizePrice(price, state.price), quantity, state.discounts);
  },

  /**
   * 5.x name of `updateAmounts()`.
   *
   * @since 6.0.0
   * @param {object} [price] - The unit prices, in either shape.
   * @param {number} [quantity] - How many are being bought.
   * @return {void}
   */
  executeUpdateAmounts(price, quantity = 1) {
    runtime.updateAmounts(price, quantity);
  },

  /**
   * Run a callback once calls stop arriving for `delay` milliseconds.
   *
   * @since 6.0.0
   * @param {Function} callback - What to run.
   * @param {number} delay - Milliseconds to wait.
   * @return {void}
   */
  debounce(callback, delay) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(callback, delay);
  },

  initAccordion,
  toggleAccordion,
  initModal,

  /**
   * Wire a dialog from three selectors, as 5.x's `displayModal()` did.
   *
   * @since 6.0.0
   * @param {string} openSelector - What opens the dialog.
   * @param {string} containerSelector - The backdrop holding it.
   * @param {string} closeSelector - What closes it.
   * @return {void}
   */
  displayModal(openSelector, containerSelector, closeSelector) {
    bindModal(openSelector, containerSelector, closeSelector);
  },

  /**
   * Register the variation listeners (5.x name; registered once).
   *
   * @since 6.0.0
   * @return {void}
   */
  replaceRangePrice() {
    initVariations(onVariation, onVariationReset);
  },

  /**
   * Register the variation listeners (5.x name; registered once).
   *
   * @since 6.0.0
   * @return {void}
   */
  rangeOnTriggerEvent() {
    initVariations(onVariation, onVariationReset);
  },

  /**
   * Take on a variation's data, as WooCommerce's `found_variation` passes it.
   *
   * @since 6.0.0
   * @param {object} variation - WooCommerce's variation data.
   * @return {void}
   */
  rangeHandleVariationEvent(variation) {
    applyVariation(variation, onVariation);
  },

  rangeUpdatePrice: showVariationPrice,
  rangePreventDuplicatePrices: preventDuplicatePrices,

  /**
   * Put the range back, as when the customer clears their choice.
   *
   * @since 6.0.0
   * @return {void}
   */
  rangeOnClearVariations() {
    clearVariationPrice();
    onVariationReset();
  },

  /**
   * Register the quantity listener (5.x name; registered once).
   *
   * @since 6.0.0
   * @return {void}
   */
  updateQuantity() {
    initQuantity(onQuantity);
  },

  /**
   * Register the tiered-pricing listener (5.x name; registered once).
   *
   * @since 6.0.0
   * @return {void}
   */
  updatedTieredPrice() {
    initTieredPricing(onTieredPrice);
  },

  /**
   * The live price group, as a jQuery collection when jQuery is loaded.
   *
   * 5.x returned a jQuery object and snippets chain `.find()` on it.
   *
   * @since 6.0.0
   * @return {object|Element|null} The group.
   */
  getPriceSelector() {
    const group = priceGroup();
    const jq = globalThis.jQuery;

    return jq ? jq(group || []) : group;
  },

  /**
   * Repaint the product price with already-multiplied prices.
   *
   * @since 6.0.0
   * @param {object} price - `{old_price, new_price}` or `{regular, current}`.
   * @return {void}
   */
  updateMainPriceElement(price) {
    updateMainPrice(priceGroup(), price);
  },

  /**
   * Write the upfront (Pix) price.
   *
   * @since 6.0.0
   * @param {number} amount - The discounted total.
   * @return {void}
   */
  updatePixDiscountElement(amount) {
    updatePixDiscount(amount);
  },

  /**
   * Write the bank slip price.
   *
   * @since 6.0.0
   * @param {number} amount - The discounted total.
   * @return {void}
   */
  updateSlipBankElement(amount) {
    updateSlipBank(amount);
  },

  /**
   * Write what the customer saves.
   *
   * @since 6.0.0
   * @param {number} amount - The amount saved.
   * @return {void}
   */
  updateEconomyElement(amount) {
    updateEconomy(amount);
  },

  /**
   * Rebuild the instalment table for a price.
   *
   * @since 6.0.0
   * @param {number} price - The total to build the plan from.
   * @return {void}
   */
  updateTableInstallments(price) {
    updateTable(price);
  },

  getPercentageDiscount: afterPercentage,
  getFormattedPrice: formatAmount,

  /**
   * Boot the runtime. Safe to call more than once.
   *
   * @since 6.0.0
   * @return {void}
   */
  init() {
    if (booted) {
      return;
    }

    booted = true;

    initAccordion();
    initModal();

    if ('simple' !== params.product?.type) {
      initVariations(onVariation, onVariationReset);
    }

    if (quantityApplies()) {
      initQuantity(onQuantity);
    }

    // Whenever the plugin is active, not only when the price follows the
    // quantity: the tier plugin changes the unit price by itself.
    if (tieredPricingActive()) {
      initTieredPricing(onTieredPrice);
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
