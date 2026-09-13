<script setup>
/**
 * BaseConfirmDialog.vue - "are you sure?", for an action that cannot be undone.
 *
 * Cancel is the first control, so Enter on an accidental open does nothing
 * destructive.
 *
 * @since 6.0.0
 */
import BaseButton from './BaseButton.vue';
import BaseModal from './BaseModal.vue';
import { __ } from '../../utils/i18n';

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  confirmLabel: { type: String, default: '' },
  cancelLabel: { type: String, default: '' },
  tone: { type: String, default: 'danger' },
  busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
  <BaseModal
    :open="open"
    :eyebrow="__('Confirmation')"
    :title="title"
    :description="description"
    size="sm"
    :dismissible="!busy"
    @close="emit('cancel')"
  >
    <slot />

    <template #actions>
      <BaseButton variant="secondary" :disabled="busy" @click="emit('cancel')">
        {{ cancelLabel || __('Cancel') }}
      </BaseButton>

      <BaseButton :variant="tone === 'danger' ? 'danger' : 'primary'" :loading="busy" @click="emit('confirm')">
        {{ confirmLabel || __('Confirm') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
