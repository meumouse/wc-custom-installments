<script setup>
/**
 * ToggleSwitch.vue — an on/off control bound to the stored 'yes'/'no' strings.
 *
 * @since 1.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Boolean], default: 'no' },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
  trueValue: { type: [String, Boolean], default: 'yes' },
  falseValue: { type: [String, Boolean], default: 'no' },
});

const emit = defineEmits(['update:modelValue']);

const isOn = computed(() => props.modelValue === props.trueValue || props.modelValue === true);

/**
 * Flip the value, unless the control is disabled.
 *
 * @since 1.0.0
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
    type="button"
    role="switch"
    :aria-checked="isOn"
    :aria-label="ariaLabel || undefined"
    :disabled="disabled"
    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
    :class="isOn ? 'bg-primary' : 'bg-shell-200'"
    @click="toggle"
  >
    <span
      class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
      :class="isOn ? 'translate-x-5' : 'translate-x-0'"
    />
  </button>
</template>
