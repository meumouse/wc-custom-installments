<script setup>
/**
 * CheckboxGroupField.vue - one setting holding several of the values it offers.
 *
 * The value is an array of option ids, and it is rebuilt from the schema's own
 * order on every change rather than appended to, so what the browser sends
 * matches what the server stores and the field never looks dirty for having
 * been ticked in a different sequence.
 *
 * @since 6.1.0
 */
import { computed } from 'vue';
import BaseCheckbox from '../base/BaseCheckbox.vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  field: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const options = computed(() => (Array.isArray(props.field?.options) ? props.field.options : []));
const chosen = computed(() => (Array.isArray(props.modelValue) ? props.modelValue.map(String) : []));

/**
 * Whether an option is ticked.
 *
 * @since 6.1.0
 * @param {string} value - Option id.
 * @return {boolean} True when it is part of the value.
 */
function isChecked(value) {
  return chosen.value.includes(String(value));
}

/**
 * Tick or untick one option.
 *
 * @since 6.1.0
 * @param {string} value - Option id.
 * @return {void}
 */
function toggle(value) {
  if (props.disabled) {
    return;
  }

  const target = String(value);

  const next = options.value
    .map((option) => String(option.value))
    .filter((option) => (option === target ? !isChecked(target) : isChecked(option)));

  emit('update:modelValue', next);
}
</script>

<template>
  <div role="group" :aria-label="ariaLabel" class="grid gap-2 sm:grid-cols-2">
    <BaseCheckbox
      v-for="option in options"
      :key="option.value"
      tile
      :disabled="disabled"
      :model-value="isChecked(option.value)"
      :true-value="true"
      :false-value="false"
      @update:model-value="toggle(option.value)"
    >
      <span class="block text-[14px] font-medium text-slate-800">{{ option.label }}</span>
      <span v-if="option.description" class="mt-0.5 block text-[13px] text-slate-500">
        {{ option.description }}
      </span>
    </BaseCheckbox>
  </div>
</template>
