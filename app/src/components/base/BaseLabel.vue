<script setup>
/**
 * BaseLabel.vue - a field label.
 *
 * Renders a real <label> when it names a control by id, and a plain block
 * otherwise: a label pointing at nothing is announced as a stray string.
 *
 * @since 6.0.0
 */
defineProps({
  for: { type: String, default: '' },
  size: { type: String, default: 'md' },
});
</script>

<template>
  <component
    :is="$props.for ? 'label' : 'span'"
    :for="$props.for || undefined"
    class="block font-semibold text-slate-800"
    :class="size === 'sm' ? 'text-[13px]' : 'text-[15px] leading-6'"
  >
    <slot />
  </component>
</template>
