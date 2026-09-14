<script setup>
/**
 * InstallmentFeesField.vue - the interest of each installment count.
 *
 * Each count takes a percentage of the price, a fixed amount, or both - the
 * way some acquirers quote it ("R$ 2,99 + 3%" for 4x). The fixed amount is
 * added once to the total, not to every installment.
 *
 * The stored shape is the 5.x one, with the fixed amount beside it:
 * { 4: { amount: '3', fixed: '2.99' } }. A row with neither is left out rather
 * than stored as zero; either way that count is offered interest-free, as in
 * 5.x.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseInput from '../base/BaseInput.vue';
import BaseInputGroup from '../base/BaseInputGroup.vue';
import { __, sprintf } from '../../utils/i18n';
import { formatMoney, roundCents } from '../../utils/money';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  field: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
  maxInstallments: { type: [String, Number], default: 12 },
  // The interest-free installments charge nothing, so the table starts after them.
  firstInstallment: { type: [String, Number], default: 1 },
  // The store's currency formatting, for the symbol and the worked example.
  environment: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

// The made-up price the worked example is calculated on.
const EXAMPLE_PRICE = 100;

const rows = computed(() => {
  const max = Math.max(1, Math.min(48, Number(props.maxInstallments) || 12));
  const first = Math.max(1, Math.min(max, Number(props.firstInstallment) || 1));

  return Array.from({ length: max - first + 1 }, (_, index) => first + index);
});

const symbol = computed(() => props.environment.currency_symbol || 'R$');

/**
 * Read one half of an installment count's interest.
 *
 * @since 6.0.0
 * @param {number} installment - Installment count.
 * @param {string} key - Either 'amount' (the percentage) or 'fixed'.
 * @return {string} The stored value, or an empty string.
 */
function value(installment, key) {
  return props.modelValue?.[installment]?.[key] ?? '';
}

/**
 * Write one half, dropping the row when both halves are empty.
 *
 * @since 6.0.0
 * @param {number} installment - Installment count.
 * @param {string} key - Either 'amount' or 'fixed'.
 * @param {string} input - The new value.
 * @return {void}
 */
function update(installment, key, input) {
  if (props.disabled) {
    return;
  }

  const next = { ...(props.modelValue || {}) };
  const row = { amount: value(installment, 'amount'), fixed: value(installment, 'fixed'), [key]: input };
  const amount = String(row.amount).trim();
  const fixed = String(row.fixed).trim();

  if (amount === '' && fixed === '') {
    delete next[installment];
  } else {
    next[installment] = fixed === '' ? { amount } : { amount, fixed };
  }

  emit('update:modelValue', next);
}

/**
 * Parse a stored number, commas included.
 *
 * @since 6.0.0
 * @param {string|number} input - The stored value.
 * @return {number} The number, or 0.
 */
function number(input) {
  return Math.max(0, parseFloat(String(input ?? '').replace(',', '.')) || 0);
}

// The last count with interest, worked through on a made-up price, so the
// reader sees what the two halves add up to - the calculation the server runs.
const example = computed(() => {
  const installment = [...rows.value]
    .reverse()
    .find((count) => number(value(count, 'amount')) > 0 || number(value(count, 'fixed')) > 0);

  if (!installment) {
    return '';
  }

  const total = EXAMPLE_PRICE + (EXAMPLE_PRICE * number(value(installment, 'amount'))) / 100 + number(value(installment, 'fixed'));
  const each = roundCents(total / installment);

  return sprintf(
    __('Exemplo: um produto de %s em %dx fica em %dx de %s, total de %s.'),
    formatMoney(EXAMPLE_PRICE, props.environment),
    installment,
    installment,
    formatMoney(each, props.environment),
    formatMoney(each * installment, props.environment),
  );
});
</script>

<template>
  <div>
    <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
      <div
        v-for="installment in rows"
        :key="installment"
        class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2"
      >
        <span class="w-10 shrink-0 text-[13px] font-semibold text-slate-600">
          {{ sprintf(__('%dx'), installment) }}
        </span>

        <BaseInputGroup class="min-w-0 flex-1" :disabled="disabled">
          <template #prefix>
            <span class="px-3 text-[13px]">{{ symbol }}</span>
          </template>

          <BaseInput
            :model-value="value(installment, 'fixed')"
            type="number"
            inputmode="decimal"
            step="0.01"
            min="0"
            placeholder="0"
            :disabled="disabled"
            :aria-label="sprintf(__('Valor fixo para %d parcelas'), installment)"
            bare
            @update:model-value="update(installment, 'fixed', $event)"
          />
        </BaseInputGroup>

        <span class="shrink-0 text-[13px] font-semibold text-slate-400" aria-hidden="true">+</span>

        <BaseInputGroup class="min-w-0 flex-1" :disabled="disabled">
          <BaseInput
            :model-value="value(installment, 'amount')"
            type="number"
            inputmode="decimal"
            step="0.01"
            min="0"
            placeholder="0"
            :disabled="disabled"
            :aria-label="sprintf(__('Juros para %d parcelas'), installment)"
            bare
            @update:model-value="update(installment, 'amount', $event)"
          />

          <template #suffix>
            <span class="px-3 text-[13px]">%</span>
          </template>
        </BaseInputGroup>
      </div>
    </div>

    <p v-if="example" class="mt-3 text-[13px] leading-5 text-slate-500">{{ example }}</p>
  </div>
</template>
