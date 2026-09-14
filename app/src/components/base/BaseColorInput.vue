<script>
/**
 * Module state, shared by every colour input on the page.
 *
 * @since 6.0.0
 */
import { ref } from 'vue';
import { parseColor } from '../../utils/color';

const RECENT_KEY = 'wcci-recent-colors';
const RECENT_LIMIT = 8;

// The storefront's own colours: the text, the popup button, the Pix badge,
// the warning badge and the danger red.
const PRESETS = ['#343a40', '#000000', '#ffffff', '#008aff', '#22c55e', '#ffba08', '#ef4444'];

// Under a colour with any transparency, so the transparency shows.
const CHECKERBOARD = 'repeating-conic-gradient(#e2e8f0 0% 25%, #ffffff 0% 50%) 0 0 / 10px 10px';

/**
 * Read what this browser remembers of the colours picked lately.
 *
 * @since 6.0.0
 * @return {string[]}
 */
function loadRecent() {
  try {
    const stored = JSON.parse(window.localStorage.getItem(RECENT_KEY) || '[]');

    return Array.isArray(stored) ? stored.filter((value) => parseColor(value)).slice(0, RECENT_LIMIT) : [];
  } catch (error) {
    return [];
  }
}

// The colours picked lately, so one chosen in a picker is offered in the next.
const recent = ref(typeof window === 'undefined' ? [] : loadRecent());
</script>

<script setup>
/**
 * BaseColorInput.vue - a colour swatch paired with its value, and a picker.
 *
 * The text field is the one that matters: a store owner pasting a brand hex
 * from a style guide should not have to find it on a colour wheel. The swatch
 * opens a picker of our own - never the browser's, which looks different in
 * every browser and system - in a panel teleported into the portal, so a card
 * or the sticky save bar never clips it: a saturation and brightness area, a
 * hue slider, an opacity slider, the hex code and a few swatches.
 *
 * Every colour setting accepts rgba() (`Settings\Repository::sanitize_color()`)
 * and the element design defaults ship with it, so opacity is on by default:
 * a colour below full opacity is written as `rgba(r, g, b, a)`, a solid one as
 * `#rrggbb`. `alpha: false` keeps a field to hex.
 *
 * Keyboard: the arrows move the area and the sliders (Shift for steps of ten),
 * Home and End go to the ends of a slider, the arrows move between swatches,
 * and Escape closes.
 *
 * `fluid` lets the text field take whatever width the column has, for a grid
 * too narrow for the fixed one; the `append` slot sits beside it.
 *
 * @since 6.0.0
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { ensurePortal } from '../../utils/portal';
import { clamp, cssColor, formatColor, hsvToRgb, parseColor, rgbToHsv, toHex } from '../../utils/color';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: String, default: '#000000' },
  id: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  fluid: { type: Boolean, default: false },
  // Offers the opacity slider and writes rgba() below full opacity.
  alpha: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const portal = ensurePortal();
const uid = `wcci-color-${Math.random().toString(36).slice(2, 9)}`;

const trigger = ref(null);
const panel = ref(null);
const area = ref(null);
const isOpen = ref(false);
const position = ref({ top: 0, left: 0, placement: 'bottom' });

// The picker keeps hue and saturation of its own: a grey has no hue and black
// no saturation, and reading them back from the value would snap the handles.
const hsv = ref({ h: 0, s: 0, v: 0 });
const opacity = ref(100);

// The last value this component wrote, to tell its own echo from an outside change.
let written = null;
let openedWith = '';

const model = computed({
  get: () => props.modelValue || '',
  set: (value) => emit('update:modelValue', value),
});

const current = computed(() => parseColor(props.modelValue));
const rgb = computed(() => hsvToRgb(hsv.value));
const hex = computed(() => toHex(rgb.value));

const swatchStyle = computed(() => (
  current.value
    ? { background: `linear-gradient(${cssColor(current.value)}, ${cssColor(current.value)}), ${CHECKERBOARD}` }
    : { background: '#ffffff' }
));

const triggerLabel = computed(() => (
  props.ariaLabel ? `${props.ariaLabel} — ${__('Escolher uma cor')}` : __('Escolher uma cor')
));

// What the handles show, opacity included, and the same colour with none of it.
const picked = computed(() => cssColor({ ...rgb.value, a: props.alpha ? opacity.value : 100 }));
const clear = computed(() => cssColor({ ...rgb.value, a: 0 }));

/**
 * Point the picker at a colour.
 *
 * @since 6.0.0
 * @param {{r: number, g: number, b: number, a: number}} color - The colour.
 * @return {void}
 */
function show(color) {
  const next = rgbToHsv(color);

  hsv.value = {
    h: next.s === 0 || next.v === 0 ? hsv.value.h : next.h,
    s: next.v === 0 ? hsv.value.s : next.s,
    v: next.v,
  };
  opacity.value = props.alpha ? color.a : 100;
}

watch(
  () => props.modelValue,
  (value) => {
    const color = parseColor(value);

    // Half a hex being typed is not a colour yet: the handles stay where they are.
    if (value !== written && color) {
      show(color);
    }
  },
  { immediate: true },
);

/**
 * Write the colour the handles are at.
 *
 * @since 6.0.0
 * @return {void}
 */
function write() {
  written = formatColor({ ...rgb.value, a: props.alpha ? opacity.value : 100 });
  emit('update:modelValue', written);
}

/**
 * Move the hue, saturation or brightness.
 *
 * Starting from a fully transparent colour, the new one is made solid:
 * otherwise nothing would seem to happen.
 *
 * @since 6.0.0
 * @param {object} change - Any of `h`, `s` and `v`.
 * @return {void}
 */
function setHsv(change) {
  hsv.value = {
    h: clamp(change.h ?? hsv.value.h, 0, 360),
    s: clamp(change.s ?? hsv.value.s, 0, 100),
    v: clamp(change.v ?? hsv.value.v, 0, 100),
  };

  if (opacity.value === 0) {
    opacity.value = 100;
  }

  write();
}

/**
 * Move the opacity.
 *
 * @since 6.0.0
 * @param {number} value - 0 to 100.
 * @return {void}
 */
function setOpacity(value) {
  opacity.value = Math.round(clamp(value, 0, 100));
  write();
}

/**
 * Take a colour whole: a swatch, or a code typed in the panel.
 *
 * `transparent` is written as the word, the way the element defaults store it.
 *
 * @since 6.0.0
 * @param {string} value - The colour.
 * @return {void}
 */
function apply(value) {
  const color = parseColor(value);

  if (!color) {
    return;
  }

  show(color);

  if (value === 'transparent') {
    written = value;
    emit('update:modelValue', value);
  } else {
    write();
  }
}

// Dragging on the area or a slider.
const dragging = ref('');

/**
 * Follow the pointer across a surface.
 *
 * @since 6.0.0
 * @param {'area'|'hue'|'alpha'} surface - What is being dragged.
 * @param {PointerEvent} event - Pointer event.
 * @return {void}
 */
function moveTo(surface, event) {
  const rect = event.currentTarget.getBoundingClientRect();
  const x = clamp((event.clientX - rect.left) / rect.width, 0, 1);
  const y = clamp((event.clientY - rect.top) / rect.height, 0, 1);

  if (surface === 'area') {
    setHsv({ s: x * 100, v: (1 - y) * 100 });
  } else if (surface === 'hue') {
    setHsv({ h: x * 360 });
  } else {
    setOpacity(x * 100);
  }
}

/**
 * Start dragging a surface.
 *
 * @since 6.0.0
 * @param {'area'|'hue'|'alpha'} surface - What is being dragged.
 * @param {PointerEvent} event - Pointer event.
 * @return {void}
 */
function startDrag(surface, event) {
  if (event.button !== 0) {
    return;
  }

  event.preventDefault();
  event.currentTarget.setPointerCapture?.(event.pointerId);
  event.currentTarget.focus({ preventScroll: true });
  dragging.value = surface;
  moveTo(surface, event);
}

/**
 * Keep following the pointer while it drags.
 *
 * @since 6.0.0
 * @param {'area'|'hue'|'alpha'} surface - What is being dragged.
 * @param {PointerEvent} event - Pointer event.
 * @return {void}
 */
function drag(surface, event) {
  if (dragging.value === surface) {
    moveTo(surface, event);
  }
}

/**
 * Keys on the saturation and brightness area.
 *
 * @since 6.0.0
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onAreaKeydown(event) {
  const step = event.shiftKey ? 10 : 1;
  const moves = {
    ArrowLeft: { s: hsv.value.s - step },
    ArrowRight: { s: hsv.value.s + step },
    ArrowUp: { v: hsv.value.v + step },
    ArrowDown: { v: hsv.value.v - step },
    PageUp: { v: hsv.value.v + 10 },
    PageDown: { v: hsv.value.v - 10 },
  };

  if (moves[event.key]) {
    event.preventDefault();
    setHsv(moves[event.key]);
  }
}

/**
 * Keys on a slider.
 *
 * @since 6.0.0
 * @param {'hue'|'alpha'} slider - Which slider.
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onSliderKeydown(slider, event) {
  const max = slider === 'hue' ? 360 : 100;
  const now = slider === 'hue' ? hsv.value.h : opacity.value;
  const step = event.shiftKey ? 10 : 1;
  const next = {
    ArrowLeft: now - step,
    ArrowDown: now - step,
    ArrowRight: now + step,
    ArrowUp: now + step,
    PageDown: now - 10,
    PageUp: now + 10,
    Home: 0,
    End: max,
  }[event.key];

  if (next === undefined) {
    return;
  }

  event.preventDefault();

  if (slider === 'hue') {
    setHsv({ h: Math.round(next) });
  } else {
    setOpacity(next);
  }
}

// The hex and opacity fields of the panel, while they are being typed in.
const hexDraft = ref('');
const opacityDraft = ref('');
const editing = ref('');

watch(
  [hex, opacity],
  () => {
    if (editing.value !== 'hex') {
      hexDraft.value = hex.value.toUpperCase();
    }

    if (editing.value !== 'opacity') {
      opacityDraft.value = String(opacity.value);
    }
  },
  { immediate: true },
);

/**
 * Apply a hex code as soon as it is a whole one.
 *
 * @since 6.0.0
 * @param {string} value - What was typed.
 * @return {void}
 */
function onHexInput(value) {
  editing.value = 'hex';
  hexDraft.value = value;

  const code = `#${String(value).replace(/[^a-f0-9]/gi, '')}`;

  // Three digits are also the start of six: they wait for the field to be left.
  if (code.length === 7 || (props.alpha && code.length === 9)) {
    apply(code);
  }
}

/**
 * Take a short hex code and tidy the field once it is left.
 *
 * @since 6.0.0
 * @return {void}
 */
function onHexCommit() {
  const code = `#${String(hexDraft.value).replace(/[^a-f0-9]/gi, '')}`;

  if (code.length === 4) {
    apply(code);
  }

  editing.value = '';
  hexDraft.value = hex.value.toUpperCase();
}

/**
 * Apply an opacity as it is typed.
 *
 * @since 6.0.0
 * @param {string} value - What was typed.
 * @return {void}
 */
function onOpacityInput(value) {
  const digits = String(value).replace(/\D/g, '').slice(0, 3);

  editing.value = 'opacity';
  opacityDraft.value = digits;

  if (digits !== '') {
    setOpacity(Number(digits));
  }
}

/**
 * Tidy the opacity field once it is left.
 *
 * @since 6.0.0
 * @return {void}
 */
function onOpacityCommit() {
  editing.value = '';
  opacityDraft.value = String(opacity.value);
}

/**
 * The arrows step the opacity field.
 *
 * @since 6.0.0
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onOpacityKeydown(event) {
  const step = { ArrowUp: 1, ArrowDown: -1 }[event.key];

  if (event.key === 'Enter') {
    event.preventDefault();
    onOpacityCommit();
  } else if (step) {
    event.preventDefault();
    setOpacity(opacity.value + step * (event.shiftKey ? 10 : 1));
    opacityDraft.value = String(opacity.value);
  }
}

const presets = computed(() => (props.alpha ? [...PRESETS, 'transparent'] : [...PRESETS, '#0ea5e9']));
const recentShown = computed(() => (
  props.alpha ? recent.value : recent.value.filter((value) => parseColor(value)?.a === 100)
));

/**
 * A colour's notation, for comparing two of them.
 *
 * @since 6.0.0
 * @param {string} value - A colour.
 * @return {string}
 */
function normalized(value) {
  const color = parseColor(value);

  return color ? formatColor(color) : '';
}

/**
 * The accessible name of a swatch.
 *
 * @since 6.0.0
 * @param {string} value - The swatch's colour.
 * @return {string}
 */
function swatchLabel(value) {
  /* translators: %s: a colour, as a hex code such as #22C55E, rgba() or "Transparente" */
  return sprintf(__('Usar a cor %s'), swatchName(value));
}

/**
 * A swatch's colour as the code field shows it: hex in capitals, rgba() as is.
 *
 * @since 6.0.0
 * @param {string} value - The swatch's colour.
 * @return {string}
 */
function swatchName(value) {
  if (value === 'transparent') {
    return __('Transparente');
  }

  const color = normalized(value);

  return color.startsWith('#') ? color.toUpperCase() : color;
}

/**
 * The CSS background of a swatch.
 *
 * @since 6.0.0
 * @param {string} value - The swatch's colour.
 * @return {string}
 */
function swatchBackground(value) {
  const color = cssColor(parseColor(value));

  return `linear-gradient(${color}, ${color}), ${CHECKERBOARD}`;
}

// The swatch holding the keyboard in each group.
const swatchFocus = ref({ presets: 0, recent: 0 });

/**
 * The arrows move between the swatches of a group.
 *
 * @since 6.0.0
 * @param {'presets'|'recent'} group - Which group.
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onSwatchKeydown(group, event) {
  const buttons = Array.from(event.currentTarget.querySelectorAll('button'));
  const index = buttons.indexOf(document.activeElement);
  const next = {
    ArrowLeft: index - 1,
    ArrowUp: index - 1,
    ArrowRight: index + 1,
    ArrowDown: index + 1,
    Home: 0,
    End: buttons.length - 1,
  }[event.key];

  if (next === undefined || index === -1) {
    return;
  }

  event.preventDefault();

  const target = (next + buttons.length) % buttons.length;

  swatchFocus.value = { ...swatchFocus.value, [group]: target };
  buttons[target].focus();
}

/**
 * Remember a colour among the recent ones.
 *
 * @since 6.0.0
 * @param {string} value - The colour.
 * @return {void}
 */
function remember(value) {
  const color = normalized(value);

  if (!color || PRESETS.includes(color) || value === 'transparent') {
    return;
  }

  recent.value = [color, ...recent.value.filter((item) => normalized(item) !== color)].slice(0, RECENT_LIMIT);

  try {
    window.localStorage.setItem(RECENT_KEY, JSON.stringify(recent.value));
  } catch (error) {
    // Private windows and blocked storage: the list lasts as long as the page.
  }
}

/**
 * Place the panel under the swatch, or above it when there is no room below.
 *
 * On a short screen where neither side has room, the panel is kept inside the
 * viewport and covers the swatch instead of running off the edge.
 *
 * @since 6.0.0
 * @return {void}
 */
function place() {
  const rect = trigger.value?.getBoundingClientRect();

  if (!rect) {
    return;
  }

  const height = (panel.value?.offsetHeight || 420) + 8;
  const below = window.innerHeight - rect.bottom;
  const placement = below < height && rect.top > below ? 'top' : 'bottom';
  const width = panel.value?.offsetWidth || 272;

  // `top` is the panel's top edge when it opens below, its bottom edge when above.
  const top = placement === 'bottom'
    ? Math.max(8, Math.min(rect.bottom + 6, window.innerHeight - height))
    : Math.min(window.innerHeight - 8, Math.max(rect.top - 6, height));

  position.value = {
    top,
    left: Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)),
    placement,
  };
}

/**
 * Open the picker on the current colour, or on the one the placeholder shows.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function open() {
  if (props.disabled || isOpen.value) {
    return;
  }

  const color = current.value || parseColor(props.placeholder);

  if (color) {
    show(color);
  }

  openedWith = props.modelValue;
  swatchFocus.value = { presets: 0, recent: 0 };
  isOpen.value = true;
  place();
  await nextTick();
  area.value?.focus({ preventScroll: true });
  place();
}

/**
 * Close the picker, remembering the colour it left behind.
 *
 * @since 6.0.0
 * @param {boolean} [refocus] - Whether to move focus back to the swatch.
 * @return {void}
 */
function close(refocus = false) {
  if (!isOpen.value) {
    return;
  }

  isOpen.value = false;
  dragging.value = '';
  editing.value = '';

  if (props.modelValue !== openedWith) {
    remember(props.modelValue);
  }

  if (refocus) {
    trigger.value?.focus({ preventScroll: true });
  }
}

/**
 * Escape closes from anywhere in the panel.
 *
 * @since 6.0.0
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onPanelKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    close(true);
  }
}

/**
 * Close when focus leaves the panel for somewhere other than the swatch.
 *
 * @since 6.0.0
 * @param {FocusEvent} event - Focus event.
 * @return {void}
 */
function onPanelFocusout(event) {
  const next = event.relatedTarget;

  if (next && !panel.value?.contains(next) && next !== trigger.value) {
    close(false);
  }
}

onClickOutside(panel, () => close(false), { ignore: [trigger] });

/**
 * Follow the swatch while the page scrolls or resizes under an open panel.
 *
 * @since 6.0.0
 * @return {void}
 */
function onViewportChange() {
  if (isOpen.value) {
    place();
  }
}

watch(isOpen, (value) => {
  const method = value ? 'addEventListener' : 'removeEventListener';

  window[method]('scroll', onViewportChange, true);
  window[method]('resize', onViewportChange);
});

watch(() => props.disabled, (value) => {
  if (value) {
    close(false);
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onViewportChange, true);
  window.removeEventListener('resize', onViewportChange);
});

const areaText = computed(() => sprintf(
  /* translators: 1: saturation, as a percentage such as 40%; 2: brightness, likewise */
  __('Saturação %1$s, brilho %2$s'),
  `${Math.round(hsv.value.s)}%`,
  `${Math.round(hsv.value.v)}%`,
));
</script>

<template>
  <div class="flex items-center gap-3" :class="fluid ? 'w-full min-w-0' : ''">
    <button
      ref="trigger"
      type="button"
      :disabled="disabled"
      aria-haspopup="dialog"
      :aria-expanded="isOpen"
      :aria-controls="`${uid}-panel`"
      :aria-label="triggerLabel"
      class="relative h-11 w-11 shrink-0 overflow-hidden rounded-[8px] border shadow-sm transition focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100 disabled:cursor-not-allowed disabled:opacity-60"
      :class="isOpen ? 'border-primary-700 ring-1 ring-primary-700' : 'border-slate-200'"
      :style="swatchStyle"
      @click="isOpen ? close(false) : open()"
    />

    <input
      :id="id || undefined"
      v-model="model"
      type="text"
      spellcheck="false"
      autocomplete="off"
      :disabled="disabled"
      :aria-label="ariaLabel || undefined"
      :placeholder="placeholder || undefined"
      class="rounded-[8px] border border-slate-200 bg-white px-4 py-3 font-mono text-[14px] uppercase leading-5 text-slate-700 transition-colors placeholder:text-slate-400 focus:border-primary-700 focus:outline-none focus:ring-1 focus:ring-primary-700 disabled:cursor-not-allowed disabled:bg-slate-50"
      :class="fluid ? 'min-w-0 flex-1' : 'w-56 max-w-full'"
    />

    <slot name="append" />

    <Teleport :to="portal">
      <div
        v-if="isOpen"
        :id="`${uid}-panel`"
        ref="panel"
        role="dialog"
        :aria-label="ariaLabel || __('Escolher uma cor')"
        class="fixed z-[100070] w-[17rem] rounded-[8px] border border-slate-200 bg-white p-3 shadow-dropdown"
        :class="position.placement === 'top' ? '-translate-y-full' : ''"
        :style="{ top: `${position.top}px`, left: `${position.left}px` }"
        @keydown="onPanelKeydown"
        @focusout="onPanelFocusout"
      >
        <div
          ref="area"
          role="slider"
          tabindex="0"
          :aria-label="__('Saturação e brilho')"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-valuenow="Math.round(hsv.s)"
          :aria-valuetext="areaText"
          class="relative h-36 w-full cursor-crosshair touch-none select-none rounded-[8px] focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100"
          :style="{
            backgroundColor: `hsl(${hsv.h}, 100%, 50%)`,
            backgroundImage: 'linear-gradient(to top, #000000, transparent), linear-gradient(to right, #ffffff, transparent)',
          }"
          @pointerdown="startDrag('area', $event)"
          @pointermove="drag('area', $event)"
          @pointerup="dragging = ''"
          @pointercancel="dragging = ''"
          @keydown="onAreaKeydown"
        >
          <span
            class="pointer-events-none absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgba(15,23,42,0.35),0_1px_3px_rgba(15,23,42,0.3)]"
            :style="{ left: `${hsv.s}%`, top: `${100 - hsv.v}%`, backgroundColor: hex }"
          />
        </div>

        <div class="mt-3 flex items-center gap-3">
          <span
            class="h-9 w-9 shrink-0 rounded-full border border-slate-200"
            :style="{ background: `linear-gradient(${picked}, ${picked}), ${CHECKERBOARD}` }"
            aria-hidden="true"
          />

          <div class="flex min-w-0 flex-1 flex-col gap-2.5">
            <div
              role="slider"
              tabindex="0"
              :aria-label="__('Matiz')"
              aria-valuemin="0"
              aria-valuemax="360"
              :aria-valuenow="Math.round(hsv.h)"
              class="relative h-3 cursor-pointer touch-none select-none rounded-full focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100"
              style="background: linear-gradient(to right, #ff0000 0%, #ffff00 16.66%, #00ff00 33.33%, #00ffff 50%, #0000ff 66.66%, #ff00ff 83.33%, #ff0000 100%)"
              @pointerdown="startDrag('hue', $event)"
              @pointermove="drag('hue', $event)"
              @pointerup="dragging = ''"
              @pointercancel="dragging = ''"
              @keydown="onSliderKeydown('hue', $event)"
            >
              <span
                class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgba(15,23,42,0.35),0_1px_3px_rgba(15,23,42,0.3)]"
                :style="{ left: `${(hsv.h / 360) * 100}%`, backgroundColor: `hsl(${hsv.h}, 100%, 50%)` }"
              />
            </div>

            <div
              v-if="alpha"
              role="slider"
              tabindex="0"
              :aria-label="__('Opacidade')"
              aria-valuemin="0"
              aria-valuemax="100"
              :aria-valuenow="opacity"
              :aria-valuetext="`${opacity}%`"
              class="relative h-3 cursor-pointer touch-none select-none rounded-full focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100"
              :style="{ background: `linear-gradient(to right, ${clear}, ${hex}), ${CHECKERBOARD}` }"
              @pointerdown="startDrag('alpha', $event)"
              @pointermove="drag('alpha', $event)"
              @pointerup="dragging = ''"
              @pointercancel="dragging = ''"
              @keydown="onSliderKeydown('alpha', $event)"
            >
              <span
                class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgba(15,23,42,0.35),0_1px_3px_rgba(15,23,42,0.3)]"
                :style="{ left: `${opacity}%`, background: `linear-gradient(${picked}, ${picked}), ${CHECKERBOARD}` }"
              />
            </div>
          </div>
        </div>

        <div class="mt-3 flex items-center gap-2">
          <input
            :value="hexDraft"
            type="text"
            spellcheck="false"
            autocomplete="off"
            maxlength="9"
            :aria-label="__('Código hexadecimal')"
            class="min-w-0 flex-1 rounded-[8px] border border-slate-200 bg-white px-3 py-2 font-mono text-[14px] uppercase leading-5 text-slate-700 focus:border-primary-700 focus:outline-none focus:ring-1 focus:ring-primary-700"
            @focus="editing = 'hex'"
            @input="onHexInput($event.target.value)"
            @blur="onHexCommit"
            @keydown.enter.prevent="onHexCommit"
          />

          <div
            v-if="alpha"
            class="flex w-[4.5rem] shrink-0 items-center rounded-[8px] border border-slate-200 bg-white pr-2.5 transition-colors focus-within:border-primary-700 focus-within:ring-1 focus-within:ring-primary-700"
          >
            <input
              :value="opacityDraft"
              type="text"
              inputmode="numeric"
              maxlength="3"
              autocomplete="off"
              :aria-label="__('Opacidade em porcentagem')"
              class="w-full min-w-0 rounded-[8px] border-0 bg-transparent py-2 pl-3 text-right text-[14px] tabular-nums leading-5 text-slate-700 focus:outline-none"
              @focus="editing = 'opacity'"
              @input="onOpacityInput($event.target.value)"
              @blur="onOpacityCommit"
              @keydown="onOpacityKeydown"
            />
            <span class="pl-0.5 text-[13px] text-slate-400" aria-hidden="true">%</span>
          </div>
        </div>

        <div class="mt-3 border-t border-slate-100 pt-3">
          <p :id="`${uid}-presets`" class="mb-1.5 text-[11px] font-semibold uppercase text-slate-400">
            {{ __('Cores sugeridas') }}
          </p>

          <div
            role="group"
            :aria-labelledby="`${uid}-presets`"
            class="grid grid-cols-8 gap-1.5"
            @keydown="onSwatchKeydown('presets', $event)"
          >
            <button
              v-for="(value, index) in presets"
              :key="value"
              type="button"
              :tabindex="index === swatchFocus.presets ? 0 : -1"
              :aria-label="swatchLabel(value)"
              :aria-pressed="normalized(value) === normalized(modelValue)"
              :title="swatchName(value)"
              class="aspect-square w-full rounded-md border transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-1"
              :class="normalized(value) === normalized(modelValue) ? 'border-primary-700 ring-1 ring-primary-700' : 'border-slate-200 hover:border-slate-400'"
              :style="{ background: swatchBackground(value) }"
              @click="apply(value)"
              @focus="swatchFocus = { ...swatchFocus, presets: index }"
            />
          </div>

          <template v-if="recentShown.length">
            <p :id="`${uid}-recent`" class="mb-1.5 mt-3 text-[11px] font-semibold uppercase text-slate-400">
              {{ __('Usadas recentemente') }}
            </p>

            <div
              role="group"
              :aria-labelledby="`${uid}-recent`"
              class="grid grid-cols-8 gap-1.5"
              @keydown="onSwatchKeydown('recent', $event)"
            >
              <button
                v-for="(value, index) in recentShown"
                :key="value"
                type="button"
                :tabindex="index === swatchFocus.recent ? 0 : -1"
                :aria-label="swatchLabel(value)"
                :aria-pressed="normalized(value) === normalized(modelValue)"
                :title="swatchName(value)"
                class="aspect-square w-full rounded-md border transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-1"
                :class="normalized(value) === normalized(modelValue) ? 'border-primary-700 ring-1 ring-primary-700' : 'border-slate-200 hover:border-slate-400'"
                :style="{ background: swatchBackground(value) }"
                @click="apply(value)"
                @focus="swatchFocus = { ...swatchFocus, recent: index }"
              />
            </div>
          </template>
        </div>
      </div>
    </Teleport>
  </div>
</template>
