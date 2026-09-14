/**
 * color.js
 *
 * Reading, writing and converting the colours the settings store.
 *
 * A colour setting holds whatever `Settings\Repository::sanitize_color()`
 * accepts: `#rgb`, `#rrggbb`, `#rrggbbaa`, `rgb()`, `rgba()` and `transparent`.
 * The 5.x picker saved rgb() and rgba() as often as hex, and the element design
 * defaults ship rgba() backgrounds, so every one of them has to read back.
 *
 * Opacity is carried as a whole percentage (0–100) rather than a fraction, so
 * the value written back is `0.15`, never `0.15000000000000002`.
 *
 * @since 6.0.0
 */

// No four-digit `#rgba`: the settings refuse it, so it is not read either.
const HEX_PATTERN = /^#([a-f0-9]{3}|[a-f0-9]{6}|[a-f0-9]{8})$/i;
const NUMBER = '\\s*(-?\\d*\\.?\\d+)(%?)\\s*';
const RGB_PATTERN = new RegExp(`^rgba?\\(${NUMBER},${NUMBER},${NUMBER}(?:,${NUMBER})?\\)$`, 'i');

/**
 * Keep a number between two others.
 *
 * @since 6.0.0
 * @param {number} value - The number.
 * @param {number} min - Lowest allowed.
 * @param {number} max - Highest allowed.
 * @return {number}
 */
export function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

/**
 * Read a colour.
 *
 * @since 6.0.0
 * @param {string} value - A stored colour.
 * @return {{r: number, g: number, b: number, a: number}|null} Channels 0–255 and opacity 0–100, or null when it is not a colour.
 */
export function parseColor(value) {
  const text = String(value || '').trim().toLowerCase();

  if (text === 'transparent') {
    return { r: 0, g: 0, b: 0, a: 0 };
  }

  const hex = HEX_PATTERN.exec(text);

  if (hex) {
    let digits = hex[1];

    if (digits.length === 3) {
      digits = digits.replace(/./g, '$&$&');
    }

    const channel = (index) => parseInt(digits.slice(index * 2, index * 2 + 2), 16);

    return {
      r: channel(0),
      g: channel(1),
      b: channel(2),
      a: digits.length === 8 ? Math.round((channel(3) / 255) * 100) : 100,
    };
  }

  const rgb = RGB_PATTERN.exec(text);

  if (!rgb) {
    return null;
  }

  const channel = (index) => {
    const number = Number(rgb[index * 2 + 1]);

    return Math.round(clamp(rgb[index * 2 + 2] ? (number / 100) * 255 : number, 0, 255));
  };

  let alpha = 100;

  if (rgb[7] !== undefined) {
    const number = Number(rgb[7]);

    alpha = Math.round(clamp(rgb[8] ? number : number * 100, 0, 100));
  }

  return { r: channel(0), g: channel(1), b: channel(2), a: alpha };
}

/**
 * Write the red, green and blue channels as `#rrggbb`.
 *
 * @since 6.0.0
 * @param {{r: number, g: number, b: number}} color - The colour.
 * @return {string}
 */
export function toHex({ r, g, b }) {
  return `#${[r, g, b].map((part) => part.toString(16).padStart(2, '0')).join('')}`;
}

/**
 * Write a colour the way the settings store it.
 *
 * Fully opaque is hex; anything less is `rgba()`, the notation the element
 * design defaults already use.
 *
 * @since 6.0.0
 * @param {{r: number, g: number, b: number, a: number}} color - Channels 0–255 and opacity 0–100.
 * @return {string}
 */
export function formatColor(color) {
  if (color.a >= 100) {
    return toHex(color);
  }

  return `rgba(${color.r}, ${color.g}, ${color.b}, ${color.a / 100})`;
}

/**
 * A colour as a CSS value, whatever notation it was stored in.
 *
 * @since 6.0.0
 * @param {{r: number, g: number, b: number, a: number}} color - The colour.
 * @return {string}
 */
export function cssColor(color) {
  return `rgba(${color.r}, ${color.g}, ${color.b}, ${color.a / 100})`;
}

/**
 * Convert red, green and blue to hue, saturation and value.
 *
 * @since 6.0.0
 * @param {{r: number, g: number, b: number}} color - Channels 0–255.
 * @return {{h: number, s: number, v: number}} Hue 0–360, saturation and value 0–100.
 */
export function rgbToHsv({ r, g, b }) {
  const red = r / 255;
  const green = g / 255;
  const blue = b / 255;
  const max = Math.max(red, green, blue);
  const delta = max - Math.min(red, green, blue);

  let hue = 0;

  if (delta !== 0) {
    if (max === red) {
      hue = ((green - blue) / delta) % 6;
    } else if (max === green) {
      hue = (blue - red) / delta + 2;
    } else {
      hue = (red - green) / delta + 4;
    }
  }

  return {
    h: (hue * 60 + 360) % 360,
    s: max === 0 ? 0 : (delta / max) * 100,
    v: max * 100,
  };
}

/**
 * Convert hue, saturation and value to red, green and blue.
 *
 * @since 6.0.0
 * @param {{h: number, s: number, v: number}} color - Hue 0–360, saturation and value 0–100.
 * @return {{r: number, g: number, b: number}} Channels 0–255.
 */
export function hsvToRgb({ h, s, v }) {
  const saturation = s / 100;
  const value = v / 100;
  const channel = (offset) => {
    const k = (offset + h / 60) % 6;

    return Math.round((value - value * saturation * Math.max(0, Math.min(k, 4 - k, 1))) * 255);
  };

  return { r: channel(5), g: channel(3), b: channel(1) };
}
