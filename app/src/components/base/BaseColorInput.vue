<script setup>
/**
 * BaseColorInput.vue - a colour swatch paired with its hex value.
 *
 * The text field is the one that matters: a store owner pasting a brand hex
 * from a style guide should not have to find it on a colour wheel.
 *
 * @since 1.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '#000000' },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const model = computed({
  get: () => props.modelValue || '',
  set: (value) => emit('update:modelValue', value),
});

const swatch = computed({
  get: () => (/^#([A-Fa-f0-9]{3}){1,2}$/.test(props.modelValue) ? props.modelValue : '#000000'),
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <div class="flex items-center gap-2">
    <input
      v-model="swatch"
      type="color"
      :disabled="disabled"
      :aria-label="ariaLabel || undefined"
      class="h-9 w-10 shrink-0 cursor-pointer rounded-lg border border-shell-200 bg-white p-1 disabled:cursor-not-allowed"
    />

    <input
      v-model="model"
      type="text"
      spellcheck="false"
      :disabled="disabled"
      class="w-full rounded-lg border border-shell-200 bg-white px-3 py-2 font-mono text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-shell-50"
    />
  </div>
</template>
