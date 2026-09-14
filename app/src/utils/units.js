/**
 * units.js - the CSS units a measurement can be expressed in.
 *
 * One list for every unit selector on the screen, in the same order as the
 * server's `Registry::UNITS`, so the text size, the spacing boxes and the popup
 * measurements all offer the same choices under the same names.
 *
 * @since 6.0.0
 */
import { __ } from './i18n';

export const UNITS = ['rem', 'em', 'px', '%'];

/**
 * How a unit reads in a selector.
 *
 * @since 6.0.0
 * @param {string} unit - CSS unit.
 * @return {string} Its label.
 */
export function unitLabel(unit) {
  const labels = { rem: 'REM', em: 'EM', px: __('Pixel'), '%': '%' };

  return labels[unit] ?? String(unit);
}

/**
 * Options for a unit selector.
 *
 * @since 6.0.0
 * @param {string[]} [units] - Units the field accepts; every unit when empty.
 * @return {{value: string, label: string}[]} Select options.
 */
export function unitOptions(units) {
  return (Array.isArray(units) && units.length ? units : UNITS).map((unit) => ({ value: unit, label: unitLabel(unit) }));
}
