<script setup>
/**
 * InstallmentFeesField.vue - one interest rate per installment count.
 *
 * The stored shape is the 5.x one: { 2: { amount: '1.99' } }. An empty row is
 * left out rather than stored as zero; either way that count is offered
 * interest-free, as in 5.x.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseInput from '../base/BaseInput.vue';
import BaseInputGroup from '../base/BaseInputGroup.vue';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  field: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
  maxInstallments: { type: [String, Number], default: 12 },
  // The interest-free installments charge nothing, so the table starts after them.
  firstInstallment: { type: [String, Number], default: 1 },
});

const emit = defineEmits(['update:modelValue']);

const rows = computed(() => {
  const max = Math.max(1, Math.min(48, Number(props.maxInstallments) || 12));
  const first = Math.max(1, Math.min(max, Number(props.firstInstallment) || 1));

  return Array.from({ length: max - first + 1 }, (_, index) => first + index);
});

/**
 * Read the stored rate of one installment count.
 *
 * @since 6.0.0
 * @param {number} installment - Installment count.
 * @return {string} The stored amount, or an empty string.
 */
function amount(installment) {
  return props.modelValue?.[installment]?.amount ?? '';
}

/**
 * Write one rate, dropping the row when it empties.
 *
 * @since 6.0.0
 * @param {number} installment - Installment count.
 * @param {string} value - The new amount.
 * @return {void}
 */
function update(installment, value) {
  if (props.disabled) {
    return;
  }

  const next = { ...(props.modelValue || {}) };

  if (String(value).trim() === '') {
    delete next[installment];
  } else {
    next[installment] = { amount: value };
  }

  emit('update:modelValue', next);
}
</script>

<template>
  <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
    <BaseInputGroup v-for="installment in rows" :key="installment" :disabled="disabled">
      <template #prefix>
        <span class="w-14 text-center text-[13px] font-semibold text-slate-500">
          {{ sprintf(__('%dx'), installment) }}
        </span>
      </template>

      <BaseInput
        :model-value="amount(installment)"
        type="number"
        inputmode="decimal"
        step="0.01"
        min="0"
        placeholder="—"
        :disabled="disabled"
        :aria-label="sprintf(__('Juros para %d parcelas'), installment)"
        bare
        @update:model-value="update(installment, $event)"
      />

      <template #suffix>
        <span class="px-3 text-[13px]">%</span>
      </template>
    </BaseInputGroup>
  </div>
</template>
