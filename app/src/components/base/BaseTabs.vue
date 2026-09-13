<script setup>
/**
 * BaseTabs.vue - a row of tabs, segmented or as pills.
 *
 * Follows the WAI-ARIA tabs pattern: one tab in the tab order, the arrows move
 * between them, Home and End jump to the ends. Selecting follows focus, since
 * switching a settings section costs nothing.
 *
 * @since 6.0.0
 */
import { nextTick, ref } from 'vue';
import BaseIcon from './BaseIcon.vue';

const props = defineProps({
  tabs: { type: Array, default: () => [] },
  modelValue: { type: String, default: '' },
  variant: { type: String, default: 'segmented' },
  ariaLabel: { type: String, default: '' },
  idPrefix: { type: String, default: 'wcci-tab' },
});

const emit = defineEmits(['update:modelValue']);

const list = ref(null);

/**
 * Select a tab and move focus onto it.
 *
 * @since 6.0.0
 * @param {number} index - Tab index, wrapped around the ends.
 * @return {Promise<void>}
 */
async function focusTab(index) {
  const count = props.tabs.length;

  if (!count) {
    return;
  }

  const tab = props.tabs[(index + count) % count];

  emit('update:modelValue', tab.id);
  await nextTick();

  list.value?.querySelector(`#${props.idPrefix}-${tab.id}`)?.focus();
}

/**
 * Arrow-key navigation.
 *
 * @since 6.0.0
 * @param {KeyboardEvent} event - Key event.
 * @param {number} index - Index of the focused tab.
 * @return {void}
 */
function onKeydown(event, index) {
  const moves = {
    ArrowRight: index + 1,
    ArrowLeft: index - 1,
    Home: 0,
    End: props.tabs.length - 1,
  };

  if (event.key in moves) {
    event.preventDefault();
    focusTab(moves[event.key]);
  }
}
</script>

<template>
  <div class="max-w-full overflow-x-auto [scrollbar-width:thin]">
    <div
      ref="list"
      role="tablist"
      :aria-label="ariaLabel || undefined"
      :class="
        variant === 'segmented'
          ? 'flex w-fit overflow-hidden rounded-[8px] bg-[#e7edf5] p-0.5'
          : 'flex flex-wrap gap-2'
      "
    >
      <button
        v-for="(tab, index) in tabs"
        :id="`${idPrefix}-${tab.id}`"
        :key="tab.id"
        type="button"
        role="tab"
        :aria-selected="tab.id === modelValue"
        :aria-controls="`${idPrefix}-panel-${tab.id}`"
        :tabindex="tab.id === modelValue ? 0 : -1"
        class="flex shrink-0 items-center justify-center gap-2 font-semibold transition-colors focus:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-primary-200"
        :class="
          variant === 'segmented'
            ? [
                'uppercase tracking-wide',
                // Joinotify's roomy tabs fit five or six across a wp-admin
                // screen. Past that — an add-on appends its own — they tighten
                // until the screen is wide enough for the full size.
                tabs.length > 6
                  ? 'px-4 py-4 text-[13px] 2xl:min-w-[150px] 2xl:px-6 2xl:py-5 2xl:text-[15px]'
                  : 'min-w-[150px] px-6 py-5 text-[14px] xl:min-w-[165px] xl:text-[15px]',
                index > 0 ? 'border-l border-[#dbdee1]' : '',
                index === 0 ? 'rounded-l-[8px]' : '',
                index === tabs.length - 1 ? 'rounded-r-[8px]' : '',
                tab.id === modelValue
                  ? 'bg-primary-700 text-white shadow-sm'
                  : 'text-slate-600 hover:bg-[#d0dce9] hover:text-slate-800',
              ]
            : [
                'rounded-full border px-4 py-2 text-sm',
                tab.id === modelValue
                  ? 'border-primary-200 bg-primary-50 text-primary-800'
                  : 'border-slate-200 bg-white text-slate-600 hover:text-slate-800',
              ]
        "
        @click="emit('update:modelValue', tab.id)"
        @keydown="onKeydown($event, index)"
      >
        <BaseIcon v-if="tab.icon" :name="tab.icon" :size="20" />
        <span>{{ tab.title }}</span>
      </button>
    </div>
  </div>
</template>
