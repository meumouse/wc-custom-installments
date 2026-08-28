<script setup>
/**
 * BaseInput.vue - a single-line text or numeric input.
 *
 * @since 1.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
  inputmode: { type: String, default: '' },
  min: { type: [String, Number], default: null },
  max: { type: [String, Number], default: null },
  step: { type: [String, Number], default: null },
  ariaLabel: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <input
    v-model="model"
    :type="type"
    :disabled="disabled"
    :placeholder="placeholder || undefined"
    :inputmode="inputmode || undefined"
    :min="min ?? undefined"
    :max="max ?? undefined"
    :step="step ?? undefined"
    :aria-label="ariaLabel || undefined"
    class="w-full rounded-lg border border-shell-200 bg-white px-3 py-2 text-sm text-ink transition-colors placeholder:text-muted/70 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-shell-50"
  />
</template>
