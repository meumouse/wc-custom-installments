<script setup>
/**
 * BaseProgress.vue - a thin progress bar.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  value: { type: Number, default: 0 },
  label: { type: String, default: '' },
});

const clamped = computed(() => Math.min(100, Math.max(0, Math.round(props.value))));
</script>

<template>
  <div
    role="progressbar"
    :aria-valuenow="clamped"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-label="label || undefined"
    class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200/70"
  >
    <div class="h-full rounded-full bg-primary-700 transition-all duration-300" :style="{ width: `${clamped}%` }" />
  </div>
</template>
