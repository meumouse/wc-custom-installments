<script setup>
/**
 * StepIndicator.vue - where the reader is, and how much is left.
 *
 * @since 1.0.0
 */
defineProps({
  steps: { type: Array, default: () => [] },
  index: { type: Number, default: 0 },
});

const emit = defineEmits(['select']);
</script>

<template>
  <ol class="m-0 flex list-none flex-wrap gap-1.5 p-0">
    <li v-for="(step, position) in steps" :key="step.id">
      <button
        type="button"
        class="rounded-full px-3 py-1 text-xs font-semibold transition-colors"
        :class="[
          position === index ? 'bg-primary text-white' : '',
          position < index ? 'bg-primary/10 text-primary-800 hover:bg-primary/20' : '',
          position > index ? 'bg-shell-100 text-muted' : '',
        ]"
        :disabled="position > index"
        @click="emit('select', position)"
      >
        {{ step.title }}
      </button>
    </li>
  </ol>
</template>
