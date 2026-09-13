<script setup>
/**
 * TransferCard.vue - export, import and reset.
 *
 * The reset asks first. It is the one control here that destroys work, and an
 * accidental click would cost a store its whole configuration.
 *
 * @since 6.0.0
 */
import { ref } from 'vue';
import BaseButton from '../../../../components/base/BaseButton.vue';
import BaseConfirmDialog from '../../../../components/base/BaseConfirmDialog.vue';
import BaseFileInput from '../../../../components/base/BaseFileInput.vue';
import BaseNotice from '../../../../components/base/BaseNotice.vue';
import { __ } from '../../../../utils/i18n';

defineProps({
  resetting: { type: Boolean, default: false },
});

const emit = defineEmits(['export', 'import', 'reset']);

const confirmingReset = ref(false);

/**
 * Read a chosen file and hand its parsed contents up.
 *
 * @since 6.0.0
 * @param {File} file - The chosen file.
 * @return {Promise<void>}
 */
async function onFileChosen(file) {
  const text = await file.text();

  try {
    emit('import', JSON.parse(text));
  } catch (error) {
    emit('import', null);
  }
}

/**
 * Reset, once confirmed.
 *
 * @since 6.0.0
 * @return {void}
 */
function onConfirmReset() {
  emit('reset');
  confirmingReset.value = false;
}
</script>

<template>
  <div class="flex flex-col gap-4 py-6">
    <BaseNotice :title="__('Back up or move your configuration')">
      {{ __('Download every setting as a JSON file, or load one exported from another store.') }}

      <template #actions>
        <BaseButton variant="secondary" icon="download" @click="emit('export')">
          {{ __('Export settings') }}
        </BaseButton>

        <BaseFileInput accept="application/json,.json" @select="onFileChosen">
          {{ __('Import settings') }}
        </BaseFileInput>
      </template>
    </BaseNotice>

    <BaseNotice tone="danger" :title="__('Restore the defaults')">
      {{ __('Every setting goes back to how it shipped. This cannot be undone, so export first if you might want it back.') }}

      <template #actions>
        <BaseButton variant="danger-outline" icon="refresh" :loading="resetting" @click="confirmingReset = true">
          {{ resetting ? __('Restoring...') : __('Restore the defaults') }}
        </BaseButton>
      </template>
    </BaseNotice>

    <BaseConfirmDialog
      :open="confirmingReset"
      :title="__('Restore the defaults?')"
      :description="__('Every setting goes back to how it shipped. This cannot be undone, so export first if you might want it back.')"
      :confirm-label="__('Yes, restore the defaults')"
      :busy="resetting"
      @confirm="onConfirmReset"
      @cancel="confirmingReset = false"
    />
  </div>
</template>
