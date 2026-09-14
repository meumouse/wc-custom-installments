<script setup>
/**
 * BaseModal.vue - a dialog over the page.
 *
 * Escape and a click on the backdrop close it. While it is open, Tab cycles
 * inside it, and when it closes focus goes back to whatever opened it: a
 * keyboard user should end up where they were, not at the top of the page.
 *
 * Only the content scrolls. The title, the way out and the actions stay in
 * place, so a long form never hides the button that closes it.
 *
 * @since 6.0.0
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useResizeObserver } from '@vueuse/core';
import BaseIconButton from './BaseIconButton.vue';
import { ensurePortal } from '../../utils/portal';
import { __ } from '../../utils/i18n';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  eyebrow: { type: String, default: '' },
  description: { type: String, default: '' },
  size: { type: String, default: 'md' },
  dismissible: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const portal = ensurePortal();
const uid = `wcci-modal-${Math.random().toString(36).slice(2, 9)}`;
const panel = ref(null);
const body = ref(null);
const content = ref(null);
const overflowing = ref(false);

let returnFocus = null;

const SIZES = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl', '2xl': 'max-w-5xl' };

/**
 * Note whether the content is taller than the room it has.
 *
 * The actions get a divider only then: on a short dialog there is nothing
 * underneath for it to separate them from.
 *
 * @since 6.0.0
 * @return {void}
 */
function measure() {
  overflowing.value = Boolean(body.value && body.value.scrollHeight > body.value.clientHeight + 1);
}

useResizeObserver([body, content], measure);

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Close, when the dialog allows it.
 *
 * @since 6.0.0
 * @return {void}
 */
function requestClose() {
  if (props.dismissible) {
    emit('close');
  }
}

/**
 * Escape closes; Tab wraps around the dialog's own controls.
 *
 * @since 6.0.0
 * @param {KeyboardEvent} event - Key event.
 * @return {void}
 */
function onKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    requestClose();

    return;
  }

  if (event.key !== 'Tab' || !panel.value) {
    return;
  }

  const focusable = Array.from(panel.value.querySelectorAll(FOCUSABLE));

  if (!focusable.length) {
    event.preventDefault();

    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      returnFocus = document.activeElement;
      await nextTick();

      // The first control that is not the close button: the dialog's own
      // primary action, usually, rather than the way out of it.
      const controls = Array.from(panel.value?.querySelectorAll(FOCUSABLE) || []);
      const target = controls.find((element) => !element.dataset.modalClose) || controls[0] || panel.value;

      target?.focus({ preventScroll: true });
    } else if (returnFocus && typeof returnFocus.focus === 'function') {
      returnFocus.focus({ preventScroll: true });
      returnFocus = null;
    }
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  returnFocus = null;
});
</script>

<template>
  <Teleport :to="portal">
    <div
      v-if="open"
      class="wcci-fade-in fixed inset-0 z-[100050] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
      @mousedown.self="requestClose"
      @keydown="onKeydown"
    >
      <div
        ref="panel"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="title ? `${uid}-title` : undefined"
        :aria-describedby="description ? `${uid}-description` : undefined"
        tabindex="-1"
        class="wcci-pop-in relative flex max-h-[calc(100dvh-4rem)] w-full flex-col overflow-hidden rounded-lg bg-white shadow-soft focus:outline-none"
        :class="SIZES[size] || SIZES.md"
      >
        <div
          class="flex shrink-0 items-start justify-between gap-4 px-6 pt-6"
          :class="$slots.default || $slots.actions ? '' : 'pb-6'"
        >
          <div class="min-w-0">
            <p
              v-if="eyebrow || $slots.eyebrow"
              class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-shell-500"
            >
              <slot name="eyebrow">{{ eyebrow }}</slot>
            </p>

            <h2 v-if="title" :id="`${uid}-title`" class="text-xl font-semibold text-ink">{{ title }}</h2>

            <p v-if="description" :id="`${uid}-description`" class="mt-2 text-[14px] leading-6 text-slate-500">
              {{ description }}
            </p>
          </div>

          <BaseIconButton
            v-if="dismissible"
            icon="x"
            :label="__('Fechar')"
            data-modal-close="true"
            class="-mr-2 -mt-2"
            @click="requestClose"
          />
        </div>

        <div
          v-if="$slots.default"
          ref="body"
          class="min-h-0 flex-1 overflow-y-auto px-6 pt-4 text-[14px] leading-6 text-slate-600"
          :class="!$slots.actions ? 'pb-6' : overflowing ? 'pb-4' : ''"
        >
          <div ref="content">
            <slot />
          </div>
        </div>

        <div
          v-if="$slots.actions"
          class="flex shrink-0 flex-wrap items-center justify-end gap-3 px-6 pb-6"
          :class="overflowing ? 'border-t border-slate-100 pt-4' : 'pt-6'"
        >
          <slot name="actions" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
