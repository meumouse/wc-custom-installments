<script setup>
/**
 * ProUpsellModal.vue - explain one locked feature and point at the trial.
 *
 * Both actions are plain links opened in a new tab. Nothing leaves the site
 * from this dialog: the trial form lives in the setup wizard, behind an
 * explicit consent step.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseBadge from '../base/BaseBadge.vue';
import BaseButton from '../base/BaseButton.vue';
import BaseModal from '../base/BaseModal.vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
  field: { type: Object, default: null },
  storeUrl: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const isOpen = computed(() => Boolean(props.field));
</script>

<template>
  <BaseModal :open="isOpen" :title="field?.label || ''" :description="field?.description || ''" @close="emit('close')">
    <template #eyebrow>
      <BaseBadge tone="pro" icon="crown">{{ __('Pro') }}</BaseBadge>
    </template>

    <p>{{ __('Isto faz parte do complemento Pro. Teste grátis por 7 dias, sem cartão.') }}</p>

    <template #actions>
      <BaseButton variant="secondary" @click="emit('close')">
        {{ __('Talvez depois') }}
      </BaseButton>

      <BaseButton :href="storeUrl" external icon="crown">
        {{ __('Começar o teste grátis') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
