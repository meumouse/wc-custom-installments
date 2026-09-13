<script setup>
/**
 * BaseTextarea.vue - a multi-line text input.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  id: { type: String, default: '' },
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
    :id="id || undefined"
    v-model="model"
    :disabled="disabled"
    :placeholder="placeholder || undefined"
    :rows="rows"
    :aria-label="ariaLabel || undefined"
    class="block w-full resize-y rounded-lg border border-slate-200 bg-white px-4 py-3 leading-6 text-slate-700 transition-colors focus:border-primary-700 focus:ring-4 focus:ring-primary-100 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
    :class="monospace ? 'font-mono text-[13px]' : 'text-[14px]'"
  />
</template>
