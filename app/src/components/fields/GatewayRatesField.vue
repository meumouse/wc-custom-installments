<script setup>
/**
 * GatewayRatesField.vue - an amount and a type per payment gateway.
 *
 * The stored shape is the 5.x one: { gateway_id: { amount, type } }. A gateway
 * with no amount is simply absent, so disabling a gateway in WooCommerce never
 * leaves a rate silently applying.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseInput from '../base/BaseInput.vue';
import BaseSelect from '../base/BaseSelect.vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  field: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const gateways = computed(() => props.field.gateways || []);
const methods = computed(() => props.field.methods || []);

/**
 * Read the stored rate of one gateway.
 *
 * @since 6.0.0
 * @param {string} id - Gateway id.
 * @param {string} key - Either 'amount' or 'type'.
 * @return {string} The stored value, or a sensible blank.
 */
function rate(id, key) {
  const stored = props.modelValue?.[id];

  if (!stored) {
    return key === 'type' ? 'percentage' : '';
  }

  return stored[key] ?? (key === 'type' ? 'percentage' : '');
}

/**
 * Write one half of a gateway rate, dropping the row when it empties.
 *
 * @since 6.0.0
 * @param {string} id - Gateway id.
 * @param {string} key - Either 'amount' or 'type'.
 * @param {string} value - The new value.
 * @return {void}
 */
function update(id, key, value) {
  if (props.disabled) {
    return;
  }

  const next = { ...(props.modelValue || {}) };
  const row = { amount: rate(id, 'amount'), type: rate(id, 'type'), ...(next[id] || {}) };

  row[key] = value;

  if (String(row.amount).trim() === '') {
    delete next[id];
  } else {
    next[id] = row;
  }

  emit('update:modelValue', next);
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <p v-if="!gateways.length" class="text-sm text-muted">
      {{ __('No payment gateway is enabled in WooCommerce yet.') }}
    </p>

    <div
      v-for="gateway in gateways"
      :key="gateway.id"
      class="flex flex-wrap items-center gap-3 rounded-lg border border-shell-100 px-3 py-2"
    >
      <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink">{{ gateway.label }}</span>

      <div class="w-32">
        <BaseInput
          :model-value="rate(gateway.id, 'amount')"
          type="number"
          inputmode="decimal"
          step="0.01"
          min="0"
          placeholder="0"
          :disabled="disabled"
          :aria-label="gateway.label"
          @update:model-value="update(gateway.id, 'amount', $event)"
        />
      </div>

      <div class="w-40">
        <BaseSelect
          :model-value="rate(gateway.id, 'type')"
          :options="methods"
          :disabled="disabled"
          :aria-label="gateway.label"
          @update:model-value="update(gateway.id, 'type', $event)"
        />
      </div>
    </div>
  </div>
</template>
