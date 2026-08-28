<script setup>
/**
 * BaseSelect.vue - a dropdown bound to a list of {value, label} options.
 *
 * @since 1.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <select
    v-model="model"
    :disabled="disabled"
    :aria-label="ariaLabel || undefined"
    class="w-full rounded-lg border border-shell-200 bg-white px-3 py-2 text-sm text-ink transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-shell-50"
  >
    <option v-for="option in options" :key="option.value" :value="option.value">
      {{ option.label }}
    </option>
  </select>
</template>
