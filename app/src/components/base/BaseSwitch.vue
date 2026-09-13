<script setup>
/**
 * BaseSwitch.vue - an on/off control bound to the stored 'yes'/'no' strings.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Boolean], default: 'no' },
  id: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
  trueValue: { type: [String, Boolean], default: 'yes' },
  falseValue: { type: [String, Boolean], default: 'no' },
  size: { type: String, default: 'md' },
});

const emit = defineEmits(['update:modelValue']);

const isOn = computed(() => props.modelValue === props.trueValue || props.modelValue === true);

const SIZES = {
  sm: { track: 'h-5 w-9', knob: 'h-4 w-4', shift: 'translate-x-4' },
  md: { track: 'h-6 w-11', knob: 'h-5 w-5', shift: 'translate-x-5' },
};

const dimensions = computed(() => SIZES[props.size] || SIZES.md);

/**
 * Flip the value, unless the control is disabled.
 *
 * @since 6.0.0
 * @return {void}
 */
function toggle() {
  if (props.disabled) {
    return;
  }

  emit('update:modelValue', isOn.value ? props.falseValue : props.trueValue);
}
</script>

<template>
  <button
    :id="id || undefined"
    type="button"
    role="switch"
    :aria-checked="isOn"
    :aria-label="ariaLabel || undefined"
    :disabled="disabled"
    class="relative inline-flex shrink-0 cursor-pointer items-center rounded-full border border-transparent p-0.5 transition-colors duration-200 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100 disabled:cursor-not-allowed disabled:opacity-60"
    :class="[dimensions.track, isOn ? 'bg-primary-700' : 'bg-slate-300']"
    @click="toggle"
  >
    <span
      class="pointer-events-none inline-block transform rounded-full bg-white shadow-sm transition-transform duration-200"
      :class="[dimensions.knob, isOn ? dimensions.shift : 'translate-x-0']"
    />
  </button>
</template>
