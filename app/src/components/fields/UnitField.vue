<script setup>
/**
 * UnitField.vue - a measurement: an amount and the unit it is expressed in.
 *
 * Two settings behind one control, which is why it emits an object rather than
 * a scalar.
 *
 * @since 1.0.0
 */
import { computed } from 'vue';
import BaseInput from '../base/BaseInput.vue';
import BaseSelect from '../base/BaseSelect.vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  unitValue: { type: String, default: 'rem' },
  field: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'update:unitValue']);

const unitOptions = computed(() =>
  (props.field.units || []).map((unit) => ({ value: unit, label: unit })),
);

const amount = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const unit = computed({
  get: () => props.unitValue,
  set: (value) => emit('update:unitValue', value),
});
</script>

<template>
  <div class="flex items-center gap-2">
    <BaseInput
      v-model="amount"
      type="number"
      inputmode="decimal"
      :step="field.step || '0.05'"
      :disabled="disabled"
      :aria-label="field.label"
    />

    <div class="w-24 shrink-0">
      <BaseSelect v-model="unit" :options="unitOptions" :disabled="disabled" :aria-label="field.label" />
    </div>
  </div>
</template>
