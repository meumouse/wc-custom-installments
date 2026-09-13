<script setup>
/**
 * BaseIconButton.vue - a button that is only an icon, such as a close control.
 *
 * The label is required: with no text on screen it is the only thing a screen
 * reader has to announce.
 *
 * @since 6.0.0
 */
import BaseIcon from './BaseIcon.vue';

defineProps({
  icon: { type: String, required: true },
  label: { type: String, required: true },
  size: { type: Number, default: 20 },
  tone: { type: String, default: 'muted' },
  disabled: { type: Boolean, default: false },
});

const TONES = {
  muted: 'text-slate-400 hover:bg-slate-100 hover:text-slate-700',
  inverse: 'text-white/80 hover:bg-white/10 hover:text-white',
};
</script>

<template>
  <button
    type="button"
    :aria-label="label"
    :title="label"
    :disabled="disabled"
    class="inline-flex shrink-0 items-center justify-center rounded-[8px] p-1.5 transition-colors focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100 disabled:opacity-60"
    :class="TONES[tone] || TONES.muted"
  >
    <BaseIcon :name="icon" :size="size" />
  </button>
</template>
