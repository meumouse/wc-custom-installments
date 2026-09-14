/**
 * variations.js - follow the customer's variation choice.
 *
 * WooCommerce announces variation changes as jQuery events, and there is no DOM
 * equivalent: `found_variation` is triggered with `jQuery.trigger`, which only
 * jQuery handlers receive. So this listens through jQuery when it is present -
 * that is not a dependency of ours, it is the shape of WooCommerce's API - and
 * still works without it for the parts that are real DOM events.
 *
 * @since 6.0.0
 */
import { delegate, isSelector, one } from './dom';
import { debug, params } from './params';

let initialised = false;
let timer = null;
let lastVariation = null;

/**
 * Debounce, because selecting an attribute fires several events at once.
 *
 * One timer for every source, as in 5.x: the last event of a burst wins.
 *
 * @since 6.0.0
 * @param {Function} callback - What to run.
 * @return {void}
 */
function defer(callback) {
  clearTimeout(timer);
  timer = setTimeout(callback, 50);
}

/**
 * Whether the variable price range is replaced by the chosen variation's price.
 *
 * @since 6.0.0
 * @return {boolean} True when the store turned the replacement on.
 */
export function replacesRange() {
  return 'yes' === params.active_price_range;
}

/**
 * Register the variation listeners.
 *
 * Safe to call more than once; the listeners are registered the first time.
 *
 * @since 6.0.0
 * @param {Function} onChange - Called with the new prices and the variation.
 * @param {Function} [onReset] - Called when the customer clears their choice.
 * @return {void}
 */
export function initVariations(onChange, onReset) {
  if (initialised) {
    return;
  }

  const jq = globalThis.jQuery;

  if (!jq) {
    debug('jQuery is absent; variation events cannot be observed.');

    return;
  }

  initialised = true;

  jq(document).on('found_variation show_variation', 'form.variations_form', (event, variation) => {
    if (!variation) {
      return;
    }

    lastVariation = variation;
    defer(() => applyVariation(variation, onChange));
  });

  // WooCommerce writes the chosen id into this field and triggers `change` on
  // it through jQuery; swatch plugins that bypass the attribute selects often
  // write only this field.
  jq(document).on('change', 'input[name="variation_id"]', (event) => {
    const field = event.currentTarget;

    defer(() => syncFromField(field, onChange));
  });

  jq(document).on('click', 'a.reset_variation, a.reset_variations', () => {
    clearTimeout(timer);
    lastVariation = null;
    clearVariationPrice();

    if ('function' === typeof onReset) {
      onReset();
    }
  });

  // Themes and builders that swap variations their own way can say so.
  const triggers = String(params.element_triggers || '')
    .split(',')
    .map((selector) => selector.trim())
    .filter(Boolean);

  triggers.forEach((selector) => {
    if (!isSelector(selector)) {
      debug('ignoring an invalid variation trigger selector', selector);

      return;
    }

    const handler = () => defer(() => syncFromField(null, onChange));

    // `change` through jQuery, so a value a theme sets with `.trigger()` is
    // seen; the touch and click listeners are plain and passive.
    jq(document).on('change', selector, handler);
    delegate(selector, 'click', handler);
    delegate(selector, 'touchstart', handler);
  });
}

/**
 * Take on a variation WooCommerce (or a snippet) handed over.
 *
 * @since 6.0.0
 * @param {object} variation - WooCommerce's variation data.
 * @param {Function} [onChange] - Called with the new prices and the variation.
 * @return {void}
 */
export function applyVariation(variation, onChange) {
  if (!variation || !variation.variation_id) {
    return;
  }

  lastVariation = variation;

  if (replacesRange()) {
    showVariationPrice(variation.variation_id);
  }

  if ('function' === typeof onChange) {
    onChange(
      {
        regular: Number(variation.display_regular_price) || 0,
        current: Number(variation.display_price) || 0,
      },
      variation,
    );
  }
}

/**
 * Follow whatever variation the form currently holds.
 *
 * Its data comes from the variation WooCommerce last announced, or else from
 * the list it prints on the form. A form that loads its variations over AJAX
 * prints no list; the swap to the pre-rendered price still happens then, as it
 * did in 5.x, and the prices wait for WooCommerce's own event.
 *
 * @since 6.0.0
 * @param {Element|null} field - The `variation_id` field, or null for the first form's.
 * @param {Function} onChange - Called with the new prices and the variation.
 * @return {void}
 */
function syncFromField(field, onChange) {
  const input = field || one('form.variations_form input[name="variation_id"]');
  const id = input ? String(input.value || '').trim() : '';

  if (!id || '0' === id) {
    return;
  }

  const variation =
    lastVariation && String(lastVariation.variation_id) === id ? lastVariation : findVariation(input.closest('form'), id);

  if (variation) {
    applyVariation(variation, onChange);

    return;
  }

  if (replacesRange()) {
    showVariationPrice(id);
  }
}

/**
 * Look a variation up in the data WooCommerce prints on its form.
 *
 * @since 6.0.0
 * @param {Element|null} form - The variations form.
 * @param {string} id - Variation ID.
 * @return {object|null} The variation data, or null when it is not there.
 */
function findVariation(form, id) {
  const raw = form?.getAttribute('data-product_variations');

  if (!raw || 'false' === raw) {
    return null;
  }

  try {
    const list = JSON.parse(raw);

    return Array.isArray(list) ? list.find((item) => String(item?.variation_id) === id) || null : null;
  } catch (error) {
    return null;
  }
}

/**
 * Swap the price group for the chosen variation's own price.
 *
 * @since 6.0.0
 * @param {number|string} variationId - The chosen variation.
 * @return {void}
 */
export function showVariationPrice(variationId) {
  const container = one('.woo-custom-installments-price-container');
  const item = one(`#wci-variation-prices .wci-variation-item[data-variation-id="${String(variationId).replace(/[^0-9A-Za-z_-]/g, '')}"]`);

  if (!container || !item) {
    return;
  }

  container.innerHTML = item.innerHTML;
  container.classList.add('active');
  container.classList.remove('d-none');

  preventDuplicatePrices();
}

/**
 * Hide the page-level group while the chosen variation's price stands in for it.
 *
 * Two price groups on screen at once is the failure this prevents. The
 * page-level group is a sibling of the container, not a child: the template
 * prints the group's `<div>` inside the container's `<p>`, and the HTML parser
 * closes the paragraph before it. Only siblings are touched, so the group just
 * swapped into the container is never the one hidden.
 *
 * @since 6.0.0
 * @return {void}
 */
export function preventDuplicatePrices() {
  const container = one('.woo-custom-installments-price-container');

  if (!container) {
    return;
  }

  const active = container.classList.contains('active') && container.innerHTML.trim() !== '';

  if (!active) {
    container.classList.remove('active');
  }

  Array.from(container.parentElement?.children || [])
    .filter((element) => element !== container && element.classList.contains('woo-custom-installments-group'))
    .forEach((group) => group.classList.toggle('d-none', active));
}

/**
 * Put the range back when the customer clears their choice.
 *
 * @since 6.0.0
 * @return {void}
 */
export function clearVariationPrice() {
  const container = one('.woo-custom-installments-price-container');

  if (!container) {
    return;
  }

  container.classList.remove('active');
  container.classList.add('d-none');
  container.innerHTML = '';

  preventDuplicatePrices();
}

/**
 * The prices the runtime started with.
 *
 * @since 6.0.0
 * @return {{regular: number, current: number}} The unit prices.
 */
export function currentPrices() {
  const product = params.product || {};

  return {
    regular: Number(product.regular_price) || 0,
    current: Number(product.current_price) || 0,
  };
}
