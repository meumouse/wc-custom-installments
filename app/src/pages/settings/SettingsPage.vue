<script setup>
/**
 * SettingsPage.vue - the settings screen.
 *
 * Everything rendered here comes from the schema the bootstrap returned. The
 * page knows nothing about individual settings: adding one is a backend change.
 *
 * Layout, as in Joinotify: the page header with the logo, one segmented tab per
 * section, and a single panel holding the active section, with the save bar at
 * its foot.
 *
 * @since 6.0.0
 */
import { computed, ref, watch } from 'vue';
import SettingsActionBar from './components/SettingsActionBar.vue';
import TransferCard from './components/cards/TransferCard.vue';
import BaseBadge from '../../components/base/BaseBadge.vue';
import BaseButton from '../../components/base/BaseButton.vue';
import BaseTabs from '../../components/base/BaseTabs.vue';
import FieldGroup from '../../components/fields/FieldGroup.vue';
import FieldRenderer from '../../components/fields/FieldRenderer.vue';
import PageHeader from '../../components/layout/PageHeader.vue';
import SettingsPanel from '../../components/layout/SettingsPanel.vue';
import ProUpsellModal from '../../components/pro/ProUpsellModal.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import { isVisible, useSettings } from '../../composables/useSettings';
import { useToasts } from '../../composables/useToasts';
import { __, sprintf } from '../../utils/i18n';

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

// Remembered per browser, so a reload or a save lands back on the same tab.
const STORAGE_KEY = 'wc-custom-installments-settings-section';

const storeUrl = computed(() => props.bootstrap.pro?.store_url || '');
const docsUrl = computed(() => props.bootstrap.pro?.docs_url || '');
const version = computed(() => props.bootstrap.version || '');
const upsellField = ref(null);

const tabs = computed(() =>
  schema.value.map((section) => ({ id: section.id, title: section.title, icon: section.icon || '' })),
);

/**
 * Read the remembered tab, if the browser allows storage.
 *
 * @since 6.0.0
 * @return {string} Section id, or an empty string.
 */
function readStoredSection() {
  try {
    return window.localStorage.getItem(STORAGE_KEY) || '';
  } catch (error) {
    return '';
  }
}

const storedSection = readStoredSection();

const activeSection = ref(
  schema.value.some((section) => section.id === storedSection) ? storedSection : schema.value[0]?.id || '',
);

watch(activeSection, (id) => {
  try {
    window.localStorage.setItem(STORAGE_KEY, id);
  } catch (error) {
    // Private windows and locked-down browsers refuse storage; the tab simply
    // is not remembered.
  }
});

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
 * @since 6.0.0
 * @param {{ok: boolean, message: string}} result - What the action returned.
 * @return {void}
 */
function report(result) {
  push(result.message, result.ok ? 'success' : 'error');
}

/**
 * Save, and say what happened.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function onSave() {
  report(await save());
}

/**
 * Restore the defaults, and say what happened.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function onReset() {
  report(await reset());
}

/**
 * Throw away the unsaved changes by reloading the screen.
 *
 * @since 6.0.0
 * @return {void}
 */
function onDiscard() {
  window.location.reload();
}

/**
 * Download the current configuration as a JSON file.
 *
 * @since 6.0.0
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
 * @since 6.0.0
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
  <div class="pb-10 pt-4">
    <PageHeader :title="__('Settings')">
      <template #description>
        {{ __('Show installment plans, discounts and payment methods on your store.') }}
        <BaseButton v-if="docsUrl" variant="link" :href="docsUrl" external class="ml-1 font-semibold underline">
          {{ __('Read the documentation') }}
        </BaseButton>
      </template>

      <template #actions>
        <BaseBadge v-if="version" tone="neutral">{{ sprintf(__('Version %s'), version) }}</BaseBadge>
      </template>
    </PageHeader>

    <BaseTabs
      v-model="activeSection"
      class="mt-10"
      :tabs="tabs"
      :aria-label="__('Settings sections')"
      id-prefix="wcci-section"
    />

    <SettingsPanel
      v-if="currentSection"
      :id="`wcci-section-panel-${currentSection.id}`"
      :labelled-by="`wcci-section-${currentSection.id}`"
      class="mt-8"
    >
      <p v-if="currentSection.description" class="mb-10 max-w-3xl text-[15px] leading-6 text-slate-500">
        {{ currentSection.description }}
      </p>

      <div class="flex flex-col gap-4">
        <FieldGroup
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
        </FieldGroup>
      </div>

      <template #footer>
        <SettingsActionBar :dirty="isDirty" :saving="isSaving" @save="onSave" @discard="onDiscard" />
      </template>
    </SettingsPanel>

    <ProUpsellModal :field="upsellField" :store-url="storeUrl" @close="upsellField = null" />

    <ToastStack :toasts="toasts" @dismiss="dismiss" />
  </div>
</template>
