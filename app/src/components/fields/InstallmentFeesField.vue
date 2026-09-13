<script setup>
/**
 * InstallmentFeesField.vue - one interest rate per installment count.
 *
 * The stored shape is the 5.x one: { 2: { amount: '1.99' } }. An empty row is
 * left out rather than stored as zero, because zero means "charge no interest"
 * and blank means "use the global rate" - two different instructions.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseInput from '../base/BaseInput.vue';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  field: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
  maxInstallments: { type: [String, Number], default: 12 },
});

const emit = defineEmits(['update:modelValue']);

const rows = computed(() => {
  const max = Math.max(1, Math.min(48, Number(props.maxInstallments) || 12));

  return Array.from({ length: max }, (_, index) => index + 1);
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
  <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
    <label
      v-for="installment in rows"
      :key="installment"
      class="flex items-center gap-2 rounded-lg border border-shell-100 px-2 py-1.5"
    >
      <span class="w-10 shrink-0 text-xs font-semibold text-muted">
        {{ sprintf(__('%dx'), installment) }}
      </span>

      <BaseInput
        :model-value="amount(installment)"
        type="number"
        inputmode="decimal"
        step="0.01"
        min="0"
        placeholder="—"
        :disabled="disabled"
        :aria-label="sprintf(__('Interest for %d installments'), installment)"
        @update:model-value="update(installment, $event)"
      />
    </label>
  </div>
</template>
