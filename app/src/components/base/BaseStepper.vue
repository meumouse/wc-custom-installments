<script setup>
/**
 * BaseStepper.vue - where the reader is in a sequence, and how much is left.
 *
 * Steps already done can be revisited; steps ahead cannot be skipped to.
 *
 * @since 6.0.0
 */
import BaseIcon from './BaseIcon.vue';

defineProps({
  steps: { type: Array, default: () => [] },
  index: { type: Number, default: 0 },
  ariaLabel: { type: String, default: '' },
});

const emit = defineEmits(['select']);
</script>

<template>
  <ol class="flex flex-wrap gap-1.5" :aria-label="ariaLabel || undefined">
    <li v-for="(step, position) in steps" :key="step.id">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[12px] font-semibold transition-colors focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100"
        :class="[
          position === index ? 'bg-primary-700 text-white shadow-sm' : '',
          position < index ? 'bg-primary-50 text-primary-800 hover:bg-primary-100' : '',
          position > index ? 'cursor-default bg-slate-100 text-slate-400' : '',
        ]"
        :disabled="position > index"
        :aria-current="position === index ? 'step' : undefined"
        @click="emit('select', position)"
      >
        <BaseIcon v-if="position < index" name="check" :size="14" />
        {{ step.title }}
      </button>
    </li>
  </ol>
</template>
