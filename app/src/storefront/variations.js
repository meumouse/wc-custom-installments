/**
 * variations.js - follow the customer's variation choice.
 *
 * WooCommerce announces variation changes as jQuery events, and there is no DOM
 * equivalent: `found_variation` is triggered with `jQuery.trigger`, which only
 * jQuery handlers receive. So this listens through jQuery when it is present -
 * that is not a dependency of ours, it is the shape of WooCommerce's API - and
 * still works without it for the parts that are real DOM events.
 *
 * @since 1.0.0
 */
import { all, one } from './dom';
import { debug, params } from './params';

/**
 * Register the variation listeners.
 *
 * @since 1.0.0
 * @param {Function} onChange - Called with the new prices.
 * @return {void}
 */
export function initVariations(onChange) {
  const jq = globalThis.jQuery;

  if (!jq) {
    debug('jQuery is absent; variation events cannot be observed.');

    return;
  }

  let timer = null;

  /**
   * Debounce, because selecting an attribute fires several events at once.
   *
   * @param {Function} callback - What to run.
   * @return {void}
   */
  const defer = (callback) => {
    clearTimeout(timer);
    timer = setTimeout(callback, 50);
  };

  jq(document).on('found_variation show_variation', 'form.variations_form', (event, variation) => {
    if (!variation) {
      return;
    }

    defer(() => {
      if ('yes' === params.active_price_range) {
        showVariationPrice(variation.variation_id);
      }

      onChange({
        regular: variation.display_regular_price,
        current: variation.display_price,
      });
    });
  });

  jq(document).on('click', 'a.reset_variation, a.reset_variations', () => {
    clearTimeout(timer);
    clearVariationPrice();
  });

  // Themes and builders that swap variations their own way can say so.
  const triggers = String(params.element_triggers || '')
    .split(',')
    .map((selector) => selector.trim())
    .filter(Boolean);

  triggers.forEach((selector) => {
    all(selector).forEach((element) => {
      ['click', 'change'].forEach((type) => {
        element.addEventListener(type, () => defer(() => onChange(currentPrices())));
      });
    });
  });
}

/**
 * Swap the price group for the chosen variation's own price.
 *
 * @since 1.0.0
 * @param {number|string} variationId - The chosen variation.
 * @return {void}
 */
function showVariationPrice(variationId) {
  const container = one('.woo-custom-installments-price-container');
  const item = one(`#wci-variation-prices .wci-variation-item[data-variation-id="${variationId}"]`);

  if (!container || !item) {
    return;
  }

  container.innerHTML = item.innerHTML;
  container.classList.add('active');
  container.classList.remove('d-none');

  // Two price groups on screen at once is the failure this prevents.
  const group = container.parentElement?.querySelector('.woo-custom-installments-group');

  if (group) {
    group.classList.toggle('d-none', container.innerHTML.trim() !== '');
  }
}

/**
 * Put the range back when the customer clears their choice.
 *
 * @since 1.0.0
 * @return {void}
 */
function clearVariationPrice() {
  const container = one('.woo-custom-installments-price-container');

  if (!container) {
    return;
  }

  container.classList.remove('active');
  container.classList.add('d-none');
  container.innerHTML = '';

  container.parentElement?.querySelector('.woo-custom-installments-group')?.classList.remove('d-none');
}

/**
 * The prices the runtime started with.
 *
 * @since 1.0.0
 * @return {{regular: number, current: number}} The unit prices.
 */
export function currentPrices() {
  const product = params.product || {};

  return {
    regular: Number(product.regular_price) || 0,
    current: Number(product.current_price) || 0,
  };
}
