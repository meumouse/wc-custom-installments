<script setup>
/**
 * BaseTextarea.vue - a multi-line text input.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
  rows: { type: Number, default: 3 },
  monospace: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <textarea
    v-model="model"
    :disabled="disabled"
    :placeholder="placeholder || undefined"
    :rows="rows"
    :aria-label="ariaLabel || undefined"
    class="w-full rounded-lg border border-shell-200 bg-white px-3 py-2 leading-relaxed text-ink transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-shell-50"
    :class="monospace ? 'font-mono text-xs' : 'text-sm'"
  />
</template>
