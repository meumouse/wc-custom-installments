<script setup>
/**
 * BaseInput.vue - a single-line text or numeric input.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
  inputmode: { type: String, default: '' },
  autocomplete: { type: String, default: '' },
  min: { type: [String, Number], default: null },
  max: { type: [String, Number], default: null },
  step: { type: [String, Number], default: null },
  ariaLabel: { type: String, default: '' },
  monospace: { type: Boolean, default: false },
  // Inside BaseInputGroup the group draws the border and the focus ring.
  bare: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <input
    :id="id || undefined"
    v-model="model"
    :type="type"
    :name="name || undefined"
    :disabled="disabled"
    :required="required || undefined"
    :placeholder="placeholder || undefined"
    :inputmode="inputmode || undefined"
    :autocomplete="autocomplete || undefined"
    :min="min ?? undefined"
    :max="max ?? undefined"
    :step="step ?? undefined"
    :aria-label="ariaLabel || undefined"
    class="w-full min-w-0 bg-white px-4 py-3 text-[14px] leading-5 text-slate-700 transition-colors disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
    :class="[
      bare
        ? 'border-0'
        : 'rounded-lg border border-slate-200 focus:border-primary focus:ring-1 focus:ring-primary',
      monospace ? 'font-mono' : '',
    ]"
  />
</template>
