<script setup>
/**
 * BaseBadge.vue - a small pill: a status, a count, the Pro mark.
 *
 * @since 6.0.0
 */
import BaseIcon from './BaseIcon.vue';

defineProps({
  tone: { type: String, default: 'neutral' },
  icon: { type: String, default: '' },
});

const TONES = {
  neutral: 'bg-slate-100 text-slate-700',
  info: 'bg-primary-100 text-primary-800',
  success: 'bg-emerald-100 text-emerald-700',
  warning: 'bg-amber-100 text-amber-800',
  danger: 'bg-rose-100 text-rose-700',
  pro: 'bg-primary-700 text-white',
};
</script>

<template>
  <span
    class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase leading-none tracking-wide"
    :class="TONES[tone] || TONES.neutral"
  >
    <BaseIcon v-if="icon" :name="icon" :size="12" />
    <slot />
  </span>
</template>
