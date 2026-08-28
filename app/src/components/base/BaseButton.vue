<script setup>
/**
 * BaseButton.vue - the action button, in the weights the admin needs.
 *
 * @since 1.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  variant: { type: String, default: 'primary' },
  disabled: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
});

const variantClass = computed(() => {
  const variants = {
    primary: 'bg-primary text-white hover:bg-primary-800',
    secondary: 'border border-shell-200 bg-white text-ink hover:bg-shell-50',
    ghost: 'text-muted hover:bg-shell-100 hover:text-ink',
    danger: 'border border-danger/30 bg-white text-danger hover:bg-danger/5',
  };

  return variants[props.variant] || variants.primary;
});
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
    :class="variantClass"
  >
    <slot />
  </button>
</template>
