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
import { __ } from '../../../../utils/i18n';

const props = defineProps({
  resetting: { type: Boolean, default: false },
});

const emit = defineEmits(['export', 'import', 'reset']);

const fileInput = ref(null);
const confirmingReset = ref(false);

/**
 * Read a chosen file and hand its parsed contents up.
 *
 * @since 1.0.0
 * @param {Event} event - The file input change event.
 * @return {Promise<void>}
 */
async function onFileChosen(event) {
  const file = event.target?.files?.[0];

  if (!file) {
    return;
  }

  const text = await file.text();

  event.target.value = '';

  try {
    emit('import', JSON.parse(text));
  } catch (error) {
    emit('import', null);
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap gap-2">
      <BaseButton variant="secondary" @click="emit('export')">
        {{ __('Export settings') }}
      </BaseButton>

      <BaseButton variant="secondary" @click="fileInput?.click()">
        {{ __('Import settings') }}
      </BaseButton>

      <input
        ref="fileInput"
        type="file"
        accept="application/json,.json"
        class="hidden"
        @change="onFileChosen"
      />
    </div>

    <div class="rounded-lg border border-danger/20 bg-danger/5 p-4">
      <p class="m-0 text-sm font-semibold text-ink">{{ __('Restore the defaults') }}</p>

      <p class="mt-1 text-sm leading-relaxed text-muted">
        {{ __('Every setting goes back to how it shipped. This cannot be undone, so export first if you might want it back.') }}
      </p>

      <div class="mt-3 flex flex-wrap gap-2">
        <template v-if="confirmingReset">
          <BaseButton variant="danger" :disabled="resetting" @click="emit('reset'); confirmingReset = false">
            {{ resetting ? __('Restoring...') : __('Yes, restore the defaults') }}
          </BaseButton>

          <BaseButton variant="ghost" @click="confirmingReset = false">
            {{ __('Cancel') }}
          </BaseButton>
        </template>

        <BaseButton v-else variant="danger" @click="confirmingReset = true">
          {{ __('Restore the defaults') }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>
