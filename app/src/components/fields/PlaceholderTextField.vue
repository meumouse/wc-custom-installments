<script setup>
/**
 * PlaceholderTextField.vue - a text field that can insert its own tokens.
 *
 * The tokens are what make these fields hard to use from memory, so they are
 * offered as buttons that append at the end. Appending rather than inserting at
 * the caret is deliberate: it needs no focus tracking and is never surprising.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseButton from '../base/BaseButton.vue';
import BaseInput from '../base/BaseInput.vue';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: String, default: '' },
  field: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

/**
 * Append a token to the current value.
 *
 * @since 6.0.0
 * @param {string} token - The token to append.
 * @return {void}
 */
function append(token) {
  if (props.disabled) {
    return;
  }

  const current = String(props.modelValue || '');
  const separator = current.length && !current.endsWith(' ') ? ' ' : '';

  emit('update:modelValue', `${current}${separator}${token}`);
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <BaseInput v-model="model" :disabled="disabled" :aria-label="field.label" />

    <div v-if="(field.tokens || []).length" class="flex flex-wrap gap-1.5">
      <BaseButton
        v-for="token in field.tokens"
        :key="token"
        variant="secondary"
        size="sm"
        class="font-mono !text-[12px]"
        :disabled="disabled"
        :aria-label="sprintf(__('Inserir %s'), token)"
        @click="append(token)"
      >
        {{ token }}
      </BaseButton>
    </div>
  </div>
</template>
