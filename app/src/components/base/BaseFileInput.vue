<script setup>
/**
 * BaseFileInput.vue - a button that opens the file chooser.
 *
 * The native input stays hidden; the visible control is a BaseButton, so it
 * matches every other action. Emits the chosen File and clears the input, so
 * choosing the same file twice still fires.
 *
 * @since 6.0.0
 */
import { ref } from 'vue';
import BaseButton from './BaseButton.vue';

defineProps({
  accept: { type: String, default: '' },
  variant: { type: String, default: 'secondary' },
  icon: { type: String, default: 'upload' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const input = ref(null);

/**
 * Hand the chosen file up and reset the input.
 *
 * @since 6.0.0
 * @param {Event} event - The change event.
 * @return {void}
 */
function onChange(event) {
  const file = event.target?.files?.[0];

  event.target.value = '';

  if (file) {
    emit('select', file);
  }
}
</script>

<template>
  <span class="inline-flex">
    <BaseButton :variant="variant" :icon="icon" :disabled="disabled" @click="input?.click()">
      <slot />
    </BaseButton>

    <input
      ref="input"
      type="file"
      tabindex="-1"
      aria-hidden="true"
      :accept="accept || undefined"
      class="hidden"
      @change="onChange"
    />
  </span>
</template>
