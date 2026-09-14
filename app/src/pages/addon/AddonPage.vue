<script setup>
/**
 * AddonPage.vue - a screen of the plugin's menu that an add-on fills.
 *
 * The host draws what every internal page shares - the header with the logo
 * and the white panel - and hands the panel's content to the add-on registered
 * under the page's `component` (Admin\Menu::get_pages()). The add-on mounts its
 * own app there, exactly as it would into a settings tab.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import PageHeader from '../../components/layout/PageHeader.vue';
import SettingsPanel from '../../components/layout/SettingsPanel.vue';
import ExternalSection from '../../components/sections/ExternalSection.vue';

const props = defineProps({
  bootstrap: { type: Object, required: true },
});

const addon = computed(() => props.bootstrap.addon || {});

// What the add-on needs to talk to the REST API on its own.
const context = computed(() => ({ rest: props.bootstrap.rest || {}, page: addon.value.slug || '' }));
</script>

<template>
  <div class="pb-10 pt-4">
    <PageHeader :title="addon.title || ''" :description="addon.description || ''" />

    <SettingsPanel class="mt-10">
      <ExternalSection :name="addon.component || ''" :context="context" />
    </SettingsPanel>
  </div>
</template>
