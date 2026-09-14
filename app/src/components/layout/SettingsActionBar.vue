<script setup>
/**
 * SettingsActionBar.vue - the save bar at the foot of a panel.
 *
 * It lives inside the panel and sticks to the bottom of the viewport while the
 * panel runs past it, so it never covers the WordPress menu the way a bar fixed
 * across the whole window would. Save stays visible and simply disabled until
 * there is something to save; Discard only appears when there is something to
 * throw away. The settings screen and the campaign editor both use it.
 *
 * @since 6.0.0
 */
import BaseButton from '../base/BaseButton.vue';
import { __ } from '../../utils/i18n';

defineProps({
  dirty: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  // What the save button says when it is not saving.
  saveLabel: { type: String, default: '' },
});

const emit = defineEmits(['save', 'discard']);
</script>

<template>
  <div
    class="sticky inset-x-0 bottom-0 z-[20] flex flex-wrap items-center justify-between gap-4 rounded-b-[8px] border-t border-black/10 bg-white/80 px-6 py-5 backdrop-blur-[5px] sm:px-12 sm:py-6"
  >
    <p class="text-[14px] text-slate-500" aria-live="polite">
      {{ dirty ? __('Você tem alterações não salvas.') : __('Tudo está salvo.') }}
    </p>

    <div class="flex flex-wrap items-center gap-3">
      <BaseButton v-if="dirty" variant="secondary" size="lg" :disabled="saving" @click="emit('discard')">
        {{ __('Descartar') }}
      </BaseButton>

      <BaseButton size="lg" icon="save" :loading="saving" :disabled="!dirty" @click="emit('save')">
        {{ saving ? __('Salvando...') : saveLabel || __('Salvar alterações') }}
      </BaseButton>
    </div>
  </div>
</template>
