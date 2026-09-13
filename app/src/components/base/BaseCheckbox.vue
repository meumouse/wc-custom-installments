<script setup>
/**
 * BaseCheckbox.vue - a checkbox, optionally with its label beside it.
 *
 * Built on a button with the checkbox role rather than a native input: wp-admin
 * paints its own tick into `input[type=checkbox]:checked::before`, and fighting
 * that stylesheet on every release costs more than owning the control.
 *
 * Bound to 'yes'/'no' by default, like every stored toggle; pass booleans as
 * `trueValue`/`falseValue` for local state.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseIcon from './BaseIcon.vue';

const props = defineProps({
  modelValue: { type: [String, Boolean], default: 'no' },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  trueValue: { type: [String, Boolean], default: 'yes' },
  falseValue: { type: [String, Boolean], default: 'no' },
  // Draws the label inside a bordered tile, for grids of options.
  tile: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const isChecked = computed(() => props.modelValue === props.trueValue || props.modelValue === true);

/**
 * Flip the value, unless the control is disabled.
 *
 * @since 6.0.0
 * @return {void}
 */
function toggle() {
  if (!props.disabled) {
    emit('update:modelValue', isChecked.value ? props.falseValue : props.trueValue);
  }
}
</script>

<template>
  <button
    type="button"
    role="checkbox"
    :aria-checked="isChecked"
    :disabled="disabled"
    class="group inline-flex items-center gap-3 text-left text-[14px] text-slate-700 transition-colors focus:outline-none disabled:opacity-60"
    :class="
      tile
        ? [
            'w-full rounded-lg border px-4 py-3',
            isChecked ? 'border-primary-200 bg-primary-50/60' : 'border-slate-200 bg-white hover:border-slate-300',
          ]
        : ''
    "
    @click="toggle"
  >
    <span
      class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition-colors group-focus-visible:ring-4 group-focus-visible:ring-primary-100"
      :class="isChecked ? 'border-primary-700 bg-primary-700 text-white' : 'border-slate-300 bg-white text-transparent'"
    >
      <BaseIcon name="check" :size="16" />
    </span>

    <span v-if="label || $slots.default" class="min-w-0">
      <slot>{{ label }}</slot>
    </span>
  </button>
</template>
