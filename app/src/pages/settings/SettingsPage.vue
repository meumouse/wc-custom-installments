<script setup>
/**
 * SettingsPage.vue — the settings screen.
 *
 * Everything rendered here comes from the schema the REST bootstrap returned.
 * The page knows nothing about individual settings: adding one is a backend
 * change.
 *
 * @since 1.0.0
 */
import { computed, reactive, ref } from 'vue';
import FieldRenderer from '../../components/FieldRenderer.vue';
import ProUpsellModal from '../../components/ProUpsellModal.vue';
import { createApiClient } from '../../utils/api';
import { __ } from '../../utils/i18n';

const props = defineProps({
  bootstrap: { type: Object, required: true },
});

const api = createApiClient(props.bootstrap);

const schema = computed(() => props.bootstrap.schema || []);
const storeUrl = computed(() => props.bootstrap.pro?.store_url || '');

const settings = reactive({ ...(props.bootstrap.settings || {}) });
const activeSection = ref(schema.value[0]?.id || '');
const upsellField = ref(null);
const isSaving = ref(false);
const notice = ref(null);

const currentSection = computed(
  () => schema.value.find((section) => section.id === activeSection.value) || schema.value[0] || null,
);

/**
 * Persist the settings and rehydrate from what the server actually stored.
 *
 * @since 1.0.0
 * @return {Promise<void>}
 */
async function save() {
  isSaving.value = true;
  notice.value = null;

  try {
    const response = await api.post('admin/settings', { settings: { ...settings } });

    Object.assign(settings, response.settings || {});
    notice.value = { type: 'success', message: response.message || __('Settings saved.') };
  } catch (error) {
    notice.value = { type: 'error', message: error.message || __('Could not save the settings.') };
  } finally {
    isSaving.value = false;
  }
}
</script>

<template>
  <div class="wcci-settings">
    <header class="flex flex-wrap items-end justify-between gap-4 pb-6">
      <div>
        <h1 class="m-0 text-2xl font-bold text-ink">{{ __('Custom Installments') }}</h1>
        <p class="mt-1 text-sm text-muted">
          {{ __('Show installment plans, discounts and payment methods on your store.') }}
        </p>
      </div>

      <button
        type="button"
        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-800 disabled:opacity-60"
        :disabled="isSaving"
        @click="save"
      >
        {{ isSaving ? __('Saving…') : __('Save changes') }}
      </button>
    </header>

    <div
      v-if="notice"
      class="mb-4 rounded-lg px-4 py-3 text-sm"
      :class="notice.type === 'success' ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'"
      role="status"
    >
      {{ notice.message }}
    </div>

    <nav class="flex flex-wrap gap-2 border-b border-shell-200 pb-3">
      <button
        v-for="section in schema"
        :key="section.id"
        type="button"
        class="rounded-lg px-4 py-2 text-sm font-semibold transition-colors"
        :class="section.id === activeSection ? 'bg-primary text-white' : 'text-ink hover:bg-shell-100'"
        @click="activeSection = section.id"
      >
        {{ section.title }}
      </button>
    </nav>

    <section v-if="currentSection" class="mt-6 flex flex-col gap-5">
      <p v-if="currentSection.description" class="text-sm text-muted">
        {{ currentSection.description }}
      </p>

      <article
        v-for="card in currentSection.cards || []"
        :key="card.id"
        class="rounded-2xl border border-shell-100 bg-panel p-6 shadow-soft"
      >
        <h2 class="m-0 text-base font-semibold text-ink">{{ card.title }}</h2>
        <p v-if="card.description" class="mt-1 text-sm text-muted">{{ card.description }}</p>

        <div class="mt-4">
          <FieldRenderer
            v-for="field in card.fields || []"
            :key="field.key"
            v-model="settings[field.key]"
            :field="field"
            @locked-click="upsellField = $event"
          />
        </div>
      </article>
    </section>

    <ProUpsellModal :field="upsellField" :store-url="storeUrl" @close="upsellField = null" />
  </div>
</template>
