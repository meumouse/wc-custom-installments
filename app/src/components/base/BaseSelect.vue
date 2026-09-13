<script setup>
/**
 * BaseSelect.vue - a dropdown bound to a list of {value, label} options.
 *
 * A listbox rather than a native <select>, so it can be styled like every other
 * control and searched when the list is long. The panel is teleported into the
 * portal and positioned against the trigger: rendered in place it would be
 * clipped by the sticky save bar and by any card that scrolls.
 *
 * Keyboard: Enter, Space or the arrows open it; the arrows, Home and End move;
 * Enter picks; Escape and Tab close it.
 *
 * @since 6.0.0
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import BaseIcon from './BaseIcon.vue';
import { ensurePortal } from '../../utils/portal';
import { __ } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  id: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  // null decides by length: a list longer than eight gets a search box.
  searchable: { type: Boolean, default: null },
  // Inside BaseInputGroup the group draws the border.
  bare: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const portal = ensurePortal();
const uid = `wcci-select-${Math.random().toString(36).slice(2, 9)}`;

const trigger = ref(null);
const panel = ref(null);
const search = ref(null);
const isOpen = ref(false);
const query = ref('');
const activeIndex = ref(-1);
const position = ref({ top: 0, left: 0, width: 0, placement: 'bottom' });

const isSearchable = computed(() =>
  props.searchable === null ? props.options.length > 8 : props.searchable,
);

const selected = computed(
  () => props.options.find((option) => String(option.value) === String(props.modelValue)) || null,
);

const filtered = computed(() => {
  const term = query.value.trim().toLowerCase();

  if (!term) {
    return props.options;
  }

  return props.options.filter((option) => String(option.label).toLowerCase().includes(term));
});

/**
 * Place the panel under the trigger, or above it when there is no room below.
 *
 * @since 6.0.0
 * @return {void}
 */
function place() {
  const rect = trigger.value?.getBoundingClientRect();

  if (!rect) {
    return;
  }

  const panelHeight = Math.min(320, (panel.value?.offsetHeight || 280) + 8);
  const below = window.innerHeight - rect.bottom;
  const placement = below < panelHeight && rect.top > below ? 'top' : 'bottom';

  position.value = {
    top: placement === 'bottom' ? rect.bottom + 6 : rect.top - 6,
    left: rect.left,
    width: rect.width,
    placement,
  };
}

/**
 * Open the panel with the current value highlighted.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function open() {
  if (props.disabled || isOpen.value) {
    return;
  }

  query.value = '';
  activeIndex.value = Math.max(0, filtered.value.indexOf(selected.value));
  isOpen.value = true;

  place();
  await nextTick();
  place();

  (isSearchable.value ? search.value : panel.value)?.focus({ preventScroll: true });
  scrollActiveIntoView();
}

/**
 * Close the panel, returning focus to the trigger when asked.
 *
 * @since 6.0.0
 * @param {boolean} [refocus] - Whether to move focus back to the trigger.
 * @return {void}
 */
function close(refocus = false) {
  isOpen.value = false;

  if (refocus) {
    trigger.value?.focus({ preventScroll: true });
  }
}

/**
 * Pick an option.
 *
 * @since 6.0.0
 * @param {{value: string|number}} option - The chosen option.
 * @return {void}
 */
function choose(option) {
  if (option) {
    emit('update:modelValue', option.value);
  }

  close(true);
}

/**
 * Keep the highlighted option visible while moving through a long list.
 *
 * @since 6.0.0
 * @return {void}
 */
function scrollActiveIntoView() {
  nextTick(() => {
    panel.value?.querySelector(`#${uid}-option-${activeIndex.value}`)?.scrollIntoView({ block: 'nearest' });
  });
}

/**
 * Move the highlight.
 *
 * @since 6.0.0
 * @param {number} index - Target index, clamped to the list.
 * @return {void}
 */
function highlight(index) {
  const last = filtered.value.length - 1;

  activeIndex.value = last < 0 ? -1 : Math.min(last, Math.max(0, index));
  scrollActiveIntoView();
}

/**
 * Keys on the closed trigger.
 *
 * @since 6.0.0
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onTriggerKeydown(event) {
  if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
    event.preventDefault();
    open();
  }
}

/**
 * Keys inside the open panel.
 *
 * @since 6.0.0
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onPanelKeydown(event) {
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault();
      highlight(activeIndex.value + 1);
      break;

    case 'ArrowUp':
      event.preventDefault();
      highlight(activeIndex.value - 1);
      break;

    case 'Home':
      if (!isSearchable.value) {
        event.preventDefault();
        highlight(0);
      }
      break;

    case 'End':
      if (!isSearchable.value) {
        event.preventDefault();
        highlight(filtered.value.length - 1);
      }
      break;

    case 'Enter':
      event.preventDefault();
      choose(filtered.value[activeIndex.value]);
      break;

    case 'Escape':
      event.preventDefault();
      close(true);
      break;

    case 'Tab':
      close(false);
      break;

    default:
      break;
  }
}

watch(query, () => highlight(0));

onClickOutside(panel, () => close(false), { ignore: [trigger] });

/**
 * Follow the trigger while the page scrolls or resizes under an open panel.
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

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onViewportChange, true);
  window.removeEventListener('resize', onViewportChange);
});
</script>

<template>
  <div class="w-full min-w-0">
    <button
      :id="id || undefined"
      ref="trigger"
      type="button"
      aria-haspopup="listbox"
      :aria-expanded="isOpen"
      :aria-controls="`${uid}-listbox`"
      :aria-label="ariaLabel || undefined"
      :disabled="disabled"
      class="flex w-full min-w-0 items-center justify-between gap-3 bg-white px-4 py-3 text-left text-[14px] leading-5 text-slate-700 transition-colors disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
      :class="
        bare
          ? 'border-0 focus:outline-none'
          : [
              'rounded-[8px] border',
              isOpen ? 'border-primary-700 ring-4 ring-primary-100' : 'border-slate-200',
              'focus:border-primary-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100',
            ]
      "
      @click="isOpen ? close(false) : open()"
      @keydown="onTriggerKeydown"
    >
      <span class="truncate" :class="selected ? '' : 'text-slate-400'">
        {{ selected ? selected.label : placeholder || __('Select an option') }}
      </span>

      <BaseIcon
        name="chevron-down"
        :size="18"
        class="text-slate-400 transition-transform duration-200"
        :class="isOpen ? 'rotate-180' : ''"
      />
    </button>

    <Teleport :to="portal">
      <div
        v-if="isOpen"
        ref="panel"
        class="fixed z-[100010] overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-dropdown"
        :class="position.placement === 'top' ? '-translate-y-full' : ''"
        :style="{
          top: `${position.top}px`,
          left: `${position.left}px`,
          width: `${position.width}px`,
          minWidth: '12rem',
        }"
        tabindex="-1"
        @keydown="onPanelKeydown"
      >
        <div v-if="isSearchable" class="border-b border-slate-100 p-2">
          <div class="relative">
            <BaseIcon
              name="search"
              :size="16"
              class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
            />
            <input
              ref="search"
              v-model="query"
              type="text"
              spellcheck="false"
              role="combobox"
              aria-autocomplete="list"
              :aria-controls="`${uid}-listbox`"
              :aria-activedescendant="activeIndex >= 0 ? `${uid}-option-${activeIndex}` : undefined"
              :aria-label="__('Search')"
              :placeholder="__('Search…')"
              class="w-full rounded-md border border-slate-200 bg-white py-2 pl-9 pr-3 text-[13px] text-slate-700 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
            />
          </div>
        </div>

        <ul
          :id="`${uid}-listbox`"
          role="listbox"
          :aria-label="ariaLabel || undefined"
          :aria-activedescendant="!isSearchable && activeIndex >= 0 ? `${uid}-option-${activeIndex}` : undefined"
          class="max-h-64 overflow-y-auto p-1.5"
        >
          <li
            v-for="(option, index) in filtered"
            :id="`${uid}-option-${index}`"
            :key="option.value"
            role="option"
            :aria-selected="selected === option"
            class="flex cursor-pointer items-center justify-between gap-3 rounded-[8px] px-3 py-2 text-[14px]"
            :class="[
              index === activeIndex ? 'bg-primary-50' : '',
              selected === option ? 'font-semibold text-primary-700' : 'text-slate-700',
            ]"
            @mouseenter="activeIndex = index"
            @mousedown.prevent
            @click="choose(option)"
          >
            <span class="truncate">{{ option.label }}</span>
            <BaseIcon v-if="selected === option" name="check" :size="16" />
          </li>

          <li v-if="!filtered.length" class="px-3 py-2 text-[13px] text-slate-400">
            {{ __('Nothing matches your search.') }}
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
