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
    <BaseNotice :title="__('Faça backup ou transfira sua configuração')">
      {{ __('Baixe todas as configurações em um arquivo JSON ou carregue um exportado de outra loja.') }}

      <template #actions>
        <BaseButton variant="secondary" icon="download" @click="emit('export')">
          {{ __('Exportar configurações') }}
        </BaseButton>

        <BaseFileInput accept="application/json,.json" @select="onFileChosen">
          {{ __('Importar configurações') }}
        </BaseFileInput>
      </template>
    </BaseNotice>

    <BaseNotice tone="danger" :title="__('Restaurar os padrões')">
      {{ __('Todas as configurações voltam ao padrão original. Isso não pode ser desfeito, então exporte antes se quiser recuperá-las.') }}

      <template #actions>
        <BaseButton variant="danger-outline" icon="refresh" :loading="resetting" @click="confirmingReset = true">
          {{ resetting ? __('Restaurando...') : __('Restaurar os padrões') }}
        </BaseButton>
      </template>
    </BaseNotice>

    <BaseConfirmDialog
      :open="confirmingReset"
      :title="__('Restaurar os padrões?')"
      :description="__('Todas as configurações voltam ao padrão original. Isso não pode ser desfeito, então exporte antes se quiser recuperá-las.')"
      :confirm-label="__('Sim, restaurar os padrões')"
      :busy="resetting"
      @confirm="onConfirmReset"
      @cancel="confirmingReset = false"
    />
  </div>
</template>
