/**
 * dom.js - the handful of DOM helpers the runtime needs.
 *
 * @since 6.0.0
 */

/**
 * Find every match of a selector.
 *
 * @since 6.0.0
 * @param {string} selector - CSS selector.
 * @param {ParentNode} [scope] - Where to look.
 * @return {Element[]} The matches.
 */
export function all(selector, scope = document) {
  return Array.from(scope.querySelectorAll(selector));
}

/**
 * Find the first match of a selector.
 *
 * @since 6.0.0
 * @param {string} selector - CSS selector.
 * @param {ParentNode} [scope] - Where to look.
 * @return {Element|null} The match, or null.
 */
export function one(selector, scope = document) {
  return scope.querySelector(selector);
}

/**
 * Whether a string is a selector the browser can parse.
 *
 * Selectors typed into a settings field reach the runtime unchecked, and one
 * typo must not stop every listener registered after it.
 *
 * @since 6.0.0
 * @param {string} selector - CSS selector.
 * @return {boolean} Whether it parses.
 */
export function isSelector(selector) {
  try {
    document.createDocumentFragment().querySelector(selector);

    return true;
  } catch (error) {
    return false;
  }
}

/**
 * Listen for an event on everything matching a selector, now and later.
 *
 * Delegation rather than direct binding: the price group is re-rendered by
 * WooCommerce on every variation change, and a listener bound to the old node
 * would be listening to something no longer on the page.
 *
 * @since 6.0.0
 * @param {string} selector - CSS selector to match against.
 * @param {string} type - Event type.
 * @param {Function} handler - Called with (event, matchedElement).
 * @param {EventTarget} [root] - Where to listen.
 * @return {void}
 */
export function delegate(selector, type, handler, root = document) {
  root.addEventListener(
    type,
    (event) => {
      const match = event.target instanceof Element ? event.target.closest(selector) : null;

      if (match) {
        handler(event, match);
      }
    },
    type === 'touchstart' ? { passive: true } : false,
  );
}

/**
 * Write markup into every `.amount` inside each match.
 *
 * @since 6.0.0
 * @param {Element[]} elements - Where to write.
 * @param {string} markup - The formatted price.
 * @return {void}
 */
export function setAmount(elements, markup) {
  elements.forEach((element) => {
    all('.amount', element).forEach((amount) => {
      amount.outerHTML = markup;
    });
  });
}
