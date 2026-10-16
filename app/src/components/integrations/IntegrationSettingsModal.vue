<script setup>
/**
 * IntegrationSettingsModal.vue - what one integration decides.
 *
 * The fields are the schema's, rendered by the same `FieldRenderer` every
 * other settings field goes through, and they write straight into the page's
 * settings object. So the dialog has no save of its own: the action bar that
 * saves the rest of the screen saves these too, and closing the dialog leaves
 * the screen dirty exactly as changing any other field does.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseModal from '../base/BaseModal.vue';
import FieldRenderer from '../fields/FieldRenderer.vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
  open: { type: Boolean, default: false },
  card: { type: Object, default: null },
  fields: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
  context: { type: Object, default: () => ({}) },
});

defineEmits(['close', 'locked-click']);

const title = computed(() => props.card?.modal?.title || props.card?.title || __('Integração'));
const description = computed(() => props.card?.modal?.description || '');
</script>

<template>
  <BaseModal
    :open="open"
    :title="title"
    :description="description"
    size="lg"
    @close="$emit('close')"
  >
    <div v-if="fields.length" class="divide-y divide-slate-100">
      <FieldRenderer
        v-for="field in fields"
        :key="field.key"
        v-model="settings[field.key]"
        :unit-value="field.unit_key ? settings[field.unit_key] : ''"
        :field="field"
        :context="context"
        @update:unit-value="field.unit_key ? (settings[field.unit_key] = $event) : null"
        @locked-click="$emit('locked-click', $event)"
      />
    </div>

    <p v-else class="rounded-[8px] bg-slate-50 px-4 py-3 text-[14px] leading-6 text-slate-500">
      {{ __('Esta integração não tem nada a configurar além de ligá-la.') }}
    </p>
  </BaseModal>
</template>
