/**
 * i18n.ts
 *
 * Thin wrapper around the WordPress `wp.i18n` runtime, falling back to the
 * untranslated text when it is unavailable. Every user-facing string in the app
 * goes through these helpers so the .pot extractor can find it.
 *
 * @since 6.0.0
 */
const textDomain = 'wc-custom-installments';

/**
 * Return the WordPress i18n runtime, when present.
 *
 * @since 6.0.0
 * @returns {Object|null} The `wp.i18n` object, or null.
 */
function getWpI18n(): any {
  return (globalThis as any)?.wp?.i18n || null;
}

/**
 * Translate a string.
 *
 * @since 6.0.0
 * @param {string} text The text to translate.
 * @param {string} [domain] The text domain.
 * @returns {string} The translated string, or the original.
 */
export function __(text: string, domain = textDomain): string {
  const translator = getWpI18n()?.__;

  return typeof translator === 'function' ? translator(text, domain) : text;
}

/**
 * Translate and pluralize a string.
 *
 * @since 6.0.0
 * @param {string} single The singular form.
 * @param {string} plural The plural form.
 * @param {number} number The number deciding which form to use.
 * @param {string} [domain] The text domain.
 * @returns {string} The translated form.
 */
export function _n(single: string, plural: string, number: number, domain = textDomain): string {
  const translator = getWpI18n()?._n;

  if (typeof translator === 'function') {
    return translator(single, plural, number, domain);
  }

  return number === 1 ? single : plural;
}

/**
 * Format a string through the WordPress sprintf implementation.
 *
 * @since 6.0.0
 * @param {string} message The format string.
 * @param {...unknown} args The substitution arguments.
 * @returns {string} The formatted string, or the original.
 */
export function sprintf(message: string, ...args: unknown[]): string {
  const formatter = getWpI18n()?.sprintf;

  return typeof formatter === 'function' ? formatter(message, ...args) : message;
}

export { textDomain };
