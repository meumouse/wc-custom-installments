<script setup>
/**
 * SettingsPage.vue - the settings screen.
 *
 * Everything rendered here comes from the schema the bootstrap returned. The
 * page knows nothing about individual settings: adding one is a backend change.
 *
 * @since 1.0.0
 */
import { computed, ref } from 'vue';
import SectionTabs from './components/SectionTabs.vue';
import SettingsActionBar from './components/SettingsActionBar.vue';
import TransferCard from './components/cards/TransferCard.vue';
import SectionCard from '../../components/cards/SectionCard.vue';
import FieldRenderer from '../../components/fields/FieldRenderer.vue';
import ProUpsellModal from '../../components/pro/ProUpsellModal.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import { isVisible, useSettings } from '../../composables/useSettings';
import { useToasts } from '../../composables/useToasts';
import { __ } from '../../utils/i18n';

const props = defineProps({
  bootstrap: { type: Object, required: true },
});

const {
  schema,
  settings,
  isDirty,
  isSaving,
  isResetting,
  save,
  reset,
  exportSettings,
  importSettings,
} = useSettings(props.bootstrap);

const { toasts, push, dismiss } = useToasts();

const storeUrl = computed(() => props.bootstrap.pro?.store_url || '');
const activeSection = ref(schema.value[0]?.id || '');
const upsellField = ref(null);

const currentSection = computed(
  () => schema.value.find((section) => section.id === activeSection.value) || schema.value[0] || null,
);

const visibleCards = computed(() =>
  (currentSection.value?.cards || []).filter((card) => isVisible(card, settings)),
);

// The interest table renders one row per installment the store offers, so it
// has to follow that setting as the reader changes it.
const fieldContext = computed(() => ({ maxInstallments: settings.max_qtd_installments }));

/**
 * Report the outcome of an action.
 *
 * @since 1.0.0
 * @param {{ok: boolean, message: string}} result - What the action returned.
 * @return {void}
 */
function report(result) {
  push(result.message, result.ok ? 'success' : 'error');
}

/**
 * Save, and say what happened.
 *
 * @since 1.0.0
 * @return {Promise<void>}
 */
async function onSave() {
  report(await save());
}

/**
 * Restore the defaults, and say what happened.
 *
 * @since 1.0.0
 * @return {Promise<void>}
 */
async function onReset() {
  report(await reset());
}

/**
 * Throw away the unsaved changes by reloading the screen.
 *
 * @since 1.0.0
 * @return {void}
 */
function onDiscard() {
  window.location.reload();
}

/**
 * Download the current configuration as a JSON file.
 *
 * @since 1.0.0
 * @return {Promise<void>}
 */
async function onExport() {
  try {
    const document_ = await exportSettings();
    const blob = new Blob([JSON.stringify(document_, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `wc-custom-installments-settings-${document_.version || '1'}.json`;
    link.click();

    URL.revokeObjectURL(url);
  } catch (error) {
    push(error.message || __('Could not export the settings.'), 'error');
  }
}

/**
 * Apply a chosen export file.
 *
 * @since 1.0.0
 * @param {object|null} document_ - The parsed file, or null when it would not parse.
 * @return {Promise<void>}
 */
async function onImport(document_) {
  if (!document_) {
    push(__('That file could not be read as settings.'), 'error');

    return;
  }

  report(await importSettings(document_));
}
</script>

<template>
  <div class="pb-24">
    <header class="flex flex-wrap items-end justify-between gap-4 pb-6">
      <div>
        <h1 class="m-0 text-2xl font-bold text-ink">{{ __('Custom Installments') }}</h1>
        <p class="mt-1 text-sm text-muted">
          {{ __('Show installment plans, discounts and payment methods on your store.') }}
        </p>
      </div>
    </header>

    <SectionTabs :sections="schema" :active="activeSection" @select="activeSection = $event" />

    <section v-if="currentSection" class="mt-6 flex flex-col gap-5">
      <p v-if="currentSection.description" class="text-sm text-muted">
        {{ currentSection.description }}
      </p>

      <SectionCard
        v-for="card in visibleCards"
        :key="card.id"
        :title="card.title"
        :description="card.description"
        :locked="Boolean(card.locked)"
      >
        <TransferCard
          v-if="card.component === 'settings-transfer'"
          :resetting="isResetting"
          @export="onExport"
          @import="onImport"
          @reset="onReset"
        />

        <template v-else>
          <FieldRenderer
            v-for="field in (card.fields || []).filter((one) => isVisible(one, settings))"
            :key="field.key"
            v-model="settings[field.key]"
            :unit-value="field.unit_key ? settings[field.unit_key] : ''"
            :field="field"
            :context="fieldContext"
            :show-lock-badge="!card.locked"
            @update:unit-value="field.unit_key ? (settings[field.unit_key] = $event) : null"
            @locked-click="upsellField = $event"
          />
        </template>
      </SectionCard>
    </section>

    <SettingsActionBar :dirty="isDirty" :saving="isSaving" @save="onSave" @discard="onDiscard" />

    <ProUpsellModal :field="upsellField" :store-url="storeUrl" @close="upsellField = null" />

    <ToastStack :toasts="toasts" @dismiss="dismiss" />
  </div>
</template>
