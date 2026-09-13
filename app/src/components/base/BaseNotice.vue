<script setup>
/**
 * BaseNotice.vue - a callout inside a page: a note, a success, a danger zone.
 *
 * Not a WordPress admin notice. Those are rendered by PHP through
 * wp_admin_notice(); this one lives inside the app and follows its design.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseIcon from './BaseIcon.vue';

const props = defineProps({
  tone: { type: String, default: 'neutral' },
  title: { type: String, default: '' },
  icon: { type: String, default: '' },
  // Errors are announced as they appear; everything else waits to be read.
  live: { type: Boolean, default: false },
});

const TONES = {
  neutral: { box: 'border-slate-200 bg-slate-50/80', icon: 'text-slate-500', title: 'text-slate-800' },
  info: { box: 'border-primary-200 bg-primary-50/80', icon: 'text-primary-700', title: 'text-slate-800' },
  success: { box: 'border-emerald-200 bg-emerald-50/80', icon: 'text-emerald-600', title: 'text-emerald-800' },
  warning: { box: 'border-amber-200 bg-amber-50/80', icon: 'text-amber-600', title: 'text-amber-800' },
  danger: { box: 'border-rose-200 bg-rose-50/80', icon: 'text-rose-600', title: 'text-rose-700' },
};

const DEFAULT_ICONS = {
  info: 'info-circle',
  success: 'check-circle',
  warning: 'alert-triangle',
  danger: 'alert-circle',
};

const style = computed(() => TONES[props.tone] || TONES.neutral);
const iconName = computed(() => props.icon || DEFAULT_ICONS[props.tone] || '');
</script>

<template>
  <div
    class="flex gap-3 rounded-lg border p-4 text-[13px] leading-5 text-slate-600"
    :class="style.box"
    :role="live ? 'alert' : undefined"
  >
    <BaseIcon v-if="iconName" :name="iconName" :size="20" :class="style.icon" />

    <div class="min-w-0 flex-1">
      <p v-if="title" class="mb-1 text-[14px] font-semibold" :class="style.title">{{ title }}</p>
      <slot />

      <div v-if="$slots.actions" class="mt-3 flex flex-wrap items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
