<script setup>
/**
 * ElementPreview.vue - one element of the price group, drawn the way the store
 * prints it.
 *
 * The icon comes first, as a Font Awesome class or an image depending on the
 * store's icon format, then the copy. The product price is drawn in its most
 * involved shape, a variable product whose cheapest variation is on sale:
 * "starting from", the struck-out regular price, the discounted price and the
 * sale badge. That way every style set on the price has something to show on.
 *
 * The rules mirror the generated stylesheet and `storefront.css`: the price's
 * typography reaches every amount, the struck-out one is 0.3rem smaller on a
 * computer, and the discounted price only overrides what was set on it.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  // A resolved element: { key, icon, styles }.
  element: { type: Object, required: true },
  // The resolved discounted price, drawn inside the product price.
  sale: { type: Object, default: null },
  device: { type: String, default: 'desktop' },
  iconFormat: { type: String, default: 'class' },
  // Copy of an element other than the product price.
  text: { type: String, default: '' },
  startingFrom: { type: String, default: '' },
  saleBadge: { type: Boolean, default: false },
  // Fade everything but the discounted price, while that is what is edited.
  focusSale: { type: Boolean, default: false },
});

const isPrice = computed(() => props.element.key === 'price');

const styles = computed(() => props.element.styles?.[props.device] || {});

const saleStyles = computed(() => props.sale?.styles?.[props.device] || {});

const iconClass = computed(() => (props.iconFormat === 'class' ? String(props.element.icon?.class || '').trim() : ''));

const iconImage = computed(() => (props.iconFormat !== 'class' ? String(props.element.icon?.image || '').trim() : ''));

// Store copy may carry simple markup; a preview shows its words.
const prefix = computed(() => props.startingFrom.replace(/<[^>]*>/g, '').trim());

/**
 * Whether a style value was set.
 *
 * @since 6.0.0
 * @param {*} value - Style value.
 * @return {boolean} False for an empty value, which inherits.
 */
function filled(value) {
  return value !== undefined && value !== null && String(value).trim() !== '';
}

/**
 * Format a four-sided box as CSS.
 *
 * @since 6.0.0
 * @param {object} box - Top, right, bottom, left and the unit.
 * @return {string} CSS shorthand, or an empty string when no side is set.
 */
function boxCss(box) {
  const sides = ['top', 'right', 'bottom', 'left'];

  if (!sides.some((side) => filled(box?.[side]))) {
    return '';
  }

  const unit = box?.unit || 'rem';

  return sides.map((side) => `${filled(box?.[side]) ? box[side] : 0}${unit}`).join(' ');
}

/**
 * The size an element's text is set in.
 *
 * @since 6.0.0
 * @param {object} values - Styles of one device.
 * @return {string} CSS length, or an empty string when it inherits.
 */
function fontSize(values) {
  return filled(values.font_size) ? `${values.font_size}${values.font_unit || 'rem'}` : '';
}

/**
 * The inline style of a set of element styles, leaving out what is not set.
 *
 * @since 6.0.0
 * @param {object} values - Styles of one device.
 * @return {object} Style object.
 */
function boxStyle(values) {
  const style = {};

  if (fontSize(values)) {
    style.fontSize = fontSize(values);
  }

  if (filled(values.font_weight)) {
    style.fontWeight = values.font_weight;
  }

  if (filled(values.font_color)) {
    style.color = values.font_color;
  }

  if (filled(values.background_color)) {
    style.backgroundColor = values.background_color;
  }

  [
    ['margin', 'margin'],
    ['padding', 'padding'],
    ['border_radius', 'borderRadius'],
  ].forEach(([property, css]) => {
    const value = boxCss(values[property]);

    if (value) {
      style[css] = value;
    }
  });

  return style;
}

const wrapperStyle = computed(() => ({
  display: 'inline-flex',
  flexWrap: 'wrap',
  alignItems: 'center',
  gap: isPrice.value ? '0' : '0.25rem',
  maxWidth: '100%',
  ...boxStyle(styles.value),
}));

// The struck-out price: secondary, and smaller on a computer.
const struckStyle = computed(() => {
  const size = fontSize(styles.value);

  return {
    marginRight: '0.5rem',
    textDecoration: 'line-through',
    opacity: 0.75,
    fontSize: size && props.device === 'desktop' ? `calc(${size} - 0.3rem)` : undefined,
  };
});

const saleStyle = computed(() => ({
  display: 'inline-flex',
  alignItems: 'center',
  ...boxStyle(saleStyles.value),
}));

const fadedStyle = computed(() => (props.focusSale ? { opacity: 0.35 } : {}));

// What the storefront rounds R$120,00 down to R$97,00 to.
/* translators: %s: discount percentage, e.g. "20%" */
const badge = computed(() => sprintf(__('%s OFF'), '19%'));
</script>

<template>
  <span :style="wrapperStyle">
    <i v-if="iconClass" :class="iconClass" aria-hidden="true" :style="{ marginRight: '0.25rem', ...fadedStyle }" />
    <img
      v-else-if="iconImage"
      :src="iconImage"
      alt=""
      :style="{ width: '1.325rem', height: 'auto', marginRight: '0.25rem', ...fadedStyle }"
    />

    <template v-if="isPrice">
      <span v-if="prefix" :style="{ marginRight: '0.5rem', fontWeight: 500, ...fadedStyle }">{{ prefix }}</span>
      <span :style="{ ...struckStyle, ...(focusSale ? { opacity: 0.3 } : {}) }">{{ __('R$120,00') }}</span>
      <span :style="saleStyle">
        {{ __('R$97,00') }}
        <span
          v-if="saleBadge"
          :style="{
            marginLeft: '0.5rem',
            padding: '0.25rem 0.65rem',
            borderRadius: '10rem',
            fontSize: '0.785rem',
            fontWeight: 600,
            color: 'rgba(38, 171, 91, 1)',
            backgroundColor: 'rgba(34, 197, 94, 0.15)',
            ...fadedStyle,
          }"
        >
          {{ badge }}
        </span>
      </span>
    </template>

    <span v-else>{{ text }}</span>
  </span>
</template>
