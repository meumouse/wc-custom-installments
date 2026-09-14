<script setup>
/**
 * DesignBoxControl.vue - a four-sided measurement: margin, padding or radius.
 *
 * Top, right, bottom and left share one unit, the way the 5.x design panel
 * stored them: { top, right, bottom, left, unit }.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseInput from '../base/BaseInput.vue';
import BaseLabel from '../base/BaseLabel.vue';
import BaseSelect from '../base/BaseSelect.vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  label: { type: String, default: '' },
  units: { type: Array, default: () => ['px', 'em', 'rem', '%'] },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const SIDES = [
  { key: 'top', label: __('Superior') },
  { key: 'right', label: __('Direita') },
  { key: 'bottom', label: __('Inferior') },
  { key: 'left', label: __('Esquerda') },
];

const unitOptions = computed(() => props.units.map((unit) => ({ value: unit, label: unit })));

/**
 * Write one side, or the unit, keeping the rest of the box.
 *
 * @since 6.0.0
 * @param {string} key - Side name or `unit`.
 * @param {string} value - New value.
 * @return {void}
 */
function update(key, value) {
  if (props.disabled) {
    return;
  }

  emit('update:modelValue', { ...(props.modelValue || {}), [key]: value });
}
</script>

<template>
  <div>
    <BaseLabel size="sm">{{ label }}</BaseLabel>

    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-5">
      <div v-for="side in SIDES" :key="side.key" class="flex flex-col gap-1">
        <span class="text-[12px] text-slate-500">{{ side.label }}</span>
        <BaseInput
          :model-value="modelValue?.[side.key] ?? '0'"
          type="number"
          inputmode="decimal"
          step="0.05"
          :disabled="disabled"
          :aria-label="`${label} — ${side.label}`"
          @update:model-value="update(side.key, $event)"
        />
      </div>

      <div class="flex flex-col gap-1">
        <span class="text-[12px] text-slate-500">{{ __('Unidade') }}</span>
        <BaseSelect
          :model-value="modelValue?.unit || 'rem'"
          :options="unitOptions"
          :disabled="disabled"
          :aria-label="`${label} — ${__('Unidade')}`"
          @update:model-value="update('unit', $event)"
        />
      </div>
    </div>
  </div>
</template>
