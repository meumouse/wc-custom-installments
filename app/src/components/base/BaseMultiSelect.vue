<script setup>
/**
 * BaseMultiSelect.vue - pick several items from a list searched as you type.
 *
 * The chosen items show as removable chips in front of a search box; the
 * matches open in a panel teleported into the portal, as BaseSelect's does, so
 * a card or the sticky save bar never clips it. `search` is the caller's: a
 * function taking the typed text and resolving to [{ id, label }], which is
 * how the same control lists products from one endpoint and categories from
 * another. `labels` names the IDs already chosen, so the chips read right
 * before anything has been searched.
 *
 * Keyboard: typing searches, the arrows move through the matches, Enter picks,
 * Escape closes, and Backspace in an empty box removes the last chip.
 *
 * @since 6.0.0
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import BaseIcon from './BaseIcon.vue';
import { ensurePortal } from '../../utils/portal';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  labels: { type: Object, default: () => ({}) },
  search: { type: Function, required: true },
  id: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const portal = ensurePortal();
const uid = `wcci-multi-${Math.random().toString(36).slice(2, 9)}`;

const box = ref(null);
const input = ref(null);
const panel = ref(null);
const query = ref('');
const results = ref([]);
const known = ref({});
const isOpen = ref(false);
const loading = ref(false);
const failed = ref(false);
const activeIndex = ref(-1);
const position = ref({ top: 0, left: 0, width: 0 });

let timer = null;
let requestId = 0;

const chips = computed(() =>
  props.modelValue.map((value) => ({
    id: value,
    label: known.value[value] || props.labels[value] || sprintf(__('#%s'), value),
  })),
);

const available = computed(() => results.value.filter((item) => !props.modelValue.includes(item.id)));

/**
 * Place the panel under the box.
 *
 * @since 6.0.0
 * @return {void}
 */
function place() {
  const rect = box.value?.getBoundingClientRect();

  if (rect) {
    position.value = { top: rect.bottom + 6, left: rect.left, width: rect.width };
  }
}

/**
 * Ask for matches, keeping only the answer to the latest question.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function runSearch() {
  const current = ++requestId;

  loading.value = true;
  failed.value = false;

  try {
    const found = await props.search(query.value.trim());

    if (current !== requestId) {
      return;
    }

    results.value = Array.isArray(found) ? found : [];

    results.value.forEach((item) => {
      known.value[item.id] = item.label;
    });

    activeIndex.value = available.value.length ? 0 : -1;
  } catch (error) {
    if (current === requestId) {
      failed.value = true;
      results.value = [];
    }
  } finally {
    if (current === requestId) {
      loading.value = false;
    }
  }
}

/**
 * Open the panel and search for what is typed so far.
 *
 * @since 6.0.0
 * @return {void}
 */
function open() {
  if (props.disabled) {
    return;
  }

  if (!isOpen.value) {
    isOpen.value = true;
    place();
    runSearch();
  }
}

/**
 * Close the panel.
 *
 * @since 6.0.0
 * @return {void}
 */
function close() {
  isOpen.value = false;
}

/**
 * Add an item to the choice.
 *
 * @since 6.0.0
 * @param {{id: number, label: string}} item - The match picked.
 * @return {void}
 */
function pick(item) {
  if (!item || props.disabled) {
    return;
  }

  known.value[item.id] = item.label;
  emit('update:modelValue', [...props.modelValue, item.id]);
  query.value = '';

  nextTick(() => {
    place();
    input.value?.focus({ preventScroll: true });
  });
}

/**
 * Take an item out of the choice.
 *
 * @since 6.0.0
 * @param {number} value - The item's ID.
 * @return {void}
 */
function remove(value) {
  if (!props.disabled) {
    emit(
      'update:modelValue',
      props.modelValue.filter((item) => item !== value),
    );
    nextTick(place);
  }
}

/**
 * Keys in the search box.
 *
 * @since 6.0.0
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onKeydown(event) {
  const last = available.value.length - 1;

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault();
      open();
      activeIndex.value = Math.min(last, activeIndex.value + 1);
      break;

    case 'ArrowUp':
      event.preventDefault();
      activeIndex.value = Math.max(0, activeIndex.value - 1);
      break;

    case 'Enter':
      if (isOpen.value) {
        event.preventDefault();
        pick(available.value[activeIndex.value]);
      }
      break;

    case 'Escape':
      close();
      break;

    case 'Backspace':
      if ('' === query.value && props.modelValue.length) {
        remove(props.modelValue[props.modelValue.length - 1]);
      }
      break;

    case 'Tab':
      close();
      break;

    default:
      break;
  }
}

watch(query, () => {
  open();
  clearTimeout(timer);
  timer = setTimeout(runSearch, 250);
});

// A different search - another scope - makes the old matches meaningless.
watch(
  () => props.search,
  () => {
    results.value = [];

    if (isOpen.value) {
      runSearch();
    }
  },
);

onClickOutside(panel, close, { ignore: [box] });

/**
 * Follow the box while the page scrolls or resizes under an open panel.
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
  clearTimeout(timer);
  window.removeEventListener('scroll', onViewportChange, true);
  window.removeEventListener('resize', onViewportChange);
});
</script>

<template>
  <div class="w-full min-w-0">
    <div
      ref="box"
      class="flex min-h-[46px] w-full flex-wrap items-center gap-1.5 rounded-lg border bg-white px-2 py-1.5 transition-colors"
      :class="[
        disabled ? 'cursor-not-allowed bg-slate-50' : 'cursor-text',
        invalid ? 'border-rose-400' : isOpen ? 'border-primary ring-1 ring-primary' : 'border-slate-200',
      ]"
      @click="input?.focus()"
    >
      <span
        v-for="chip in chips"
        :key="chip.id"
        class="inline-flex max-w-full items-center gap-1 rounded-md bg-primary-50 py-1 pl-2.5 pr-1 text-[13px] font-medium text-primary-800"
      >
        <span class="truncate">{{ chip.label }}</span>
        <button
          type="button"
          class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-primary-700 hover:bg-primary-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-200 disabled:cursor-not-allowed"
          :disabled="disabled"
          :aria-label="sprintf(__('Remover %s'), chip.label)"
          @click.stop="remove(chip.id)"
        >
          <BaseIcon name="x" :size="14" />
        </button>
      </span>

      <input
        :id="id || undefined"
        ref="input"
        v-model="query"
        type="text"
        spellcheck="false"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="isOpen"
        :aria-controls="`${uid}-listbox`"
        :aria-activedescendant="isOpen && activeIndex >= 0 ? `${uid}-option-${activeIndex}` : undefined"
        :aria-label="ariaLabel || undefined"
        :placeholder="chips.length ? '' : placeholder || __('Buscar…')"
        :disabled="disabled"
        class="min-w-[8rem] flex-1 border-0 bg-transparent px-1.5 py-1.5 text-[14px] text-slate-700 focus:outline-none focus:ring-0 disabled:cursor-not-allowed"
        @focus="open"
        @keydown="onKeydown"
      />
    </div>

    <Teleport :to="portal">
      <div
        v-if="isOpen"
        ref="panel"
        class="fixed z-[100070] overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-dropdown"
        :style="{ top: `${position.top}px`, left: `${position.left}px`, width: `${position.width}px`, minWidth: '14rem' }"
      >
        <ul :id="`${uid}-listbox`" role="listbox" :aria-label="ariaLabel || undefined" class="max-h-64 overflow-y-auto p-1.5">
          <li
            v-for="(item, index) in available"
            :id="`${uid}-option-${index}`"
            :key="item.id"
            role="option"
            :aria-selected="false"
            class="cursor-pointer truncate rounded-[8px] px-3 py-2 text-[14px] text-slate-700"
            :class="index === activeIndex ? 'bg-primary-50' : ''"
            @mouseenter="activeIndex = index"
            @mousedown.prevent
            @click="pick(item)"
          >
            {{ item.label }}
          </li>

          <li v-if="loading" class="px-3 py-2 text-[13px] text-slate-400">{{ __('Buscando…') }}</li>
          <li v-else-if="failed" class="px-3 py-2 text-[13px] text-rose-600">
            {{ __('A busca falhou. Tente novamente.') }}
          </li>
          <li v-else-if="!available.length" class="px-3 py-2 text-[13px] text-slate-400">
            {{ __('Nada corresponde à sua busca.') }}
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
