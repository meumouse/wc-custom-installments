<script setup>
/**
 * DesignBoxControl.vue - a four-sided measurement: margin, padding or radius.
 *
 * Top, right, bottom and left share one unit, the way the 5.x design panel
 * stored them: { top, right, bottom, left, unit }.
 *
 * A side can be empty when the box inherits (see `placeholder`): it is shown
 * empty rather than as a zero nobody typed.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseInput from '../base/BaseInput.vue';
import BaseLabel from '../base/BaseLabel.vue';
import BaseSelect from '../base/BaseSelect.vue';
import { UNITS, unitOptions as buildUnitOptions } from '../../utils/units';
import { __ } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  label: { type: String, default: '' },
  units: { type: Array, default: () => UNITS },
  disabled: { type: Boolean, default: false },
  // Shown in an empty side, e.g. the value it inherits.
  placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const SIDES = [
  { key: 'top', label: __('Superior') },
  { key: 'right', label: __('Direita') },
  { key: 'bottom', label: __('Inferior') },
  { key: 'left', label: __('Esquerda') },
];

const unitOptions = computed(() => buildUnitOptions(props.units));

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

    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-[repeat(4,minmax(0,1fr))_minmax(0,7.5rem)]">
      <div v-for="side in SIDES" :key="side.key" class="flex min-w-0 flex-col gap-1">
        <span class="text-[12px] text-slate-500">{{ side.label }}</span>
        <BaseInput
          :model-value="modelValue?.[side.key] ?? '0'"
          type="number"
          inputmode="decimal"
          step="0.05"
          :placeholder="placeholder"
          :disabled="disabled"
          :aria-label="`${label} — ${side.label}`"
          @update:model-value="update(side.key, $event)"
        />
      </div>

      <div class="col-span-2 flex min-w-0 flex-col gap-1 sm:col-span-1">
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
