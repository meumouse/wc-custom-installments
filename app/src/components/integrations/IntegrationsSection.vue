<script setup>
/**
 * IntegrationsSection.vue - the integrations tab, as a grid of cards.
 *
 * The cards are the section's schema cards: the same shape every other section
 * carries, so the field definitions, the defaults and the Pro locks are
 * resolved in PHP exactly as everywhere else. What changes here is only how
 * they are drawn - an integration is recognised by its name and its mark, and
 * a grid finds it faster than reading down a column of groups.
 *
 * @since 6.0.0
 */
import { computed, ref } from 'vue';
import IntegrationCard from './IntegrationCard.vue';
import IntegrationSettingsModal from './IntegrationSettingsModal.vue';

const props = defineProps({
  cards: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
  context: { type: Object, default: () => ({}) },
  isVisible: { type: Function, default: () => () => true },
});

const emit = defineEmits(['locked-click']);

const openSlug = ref('');

/** The fields of one card, minus the master switch the card itself carries. */
function configurableFields(card) {
  return (card.fields || []).filter(
    (field) => field.key !== card.primary_key && props.isVisible(field, props.settings)
  );
}

const openCard = computed(() => props.cards.find((card) => card.slug === openSlug.value) || null);
const openFields = computed(() => (openCard.value ? configurableFields(openCard.value) : []));

function onConfigure(slug) {
  openSlug.value = slug;
}

function onClose() {
  openSlug.value = '';
}
</script>

<template>
  <div>
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <IntegrationCard
        v-for="card in cards"
        :key="card.slug"
        v-model="settings[card.primary_key]"
        :card="card"
        :configurable-count="configurableFields(card).length"
        @configure="onConfigure"
      />
    </div>

    <IntegrationSettingsModal
      :open="Boolean(openCard)"
      :card="openCard"
      :fields="openFields"
      :settings="settings"
      :context="context"
      @close="onClose"
      @locked-click="emit('locked-click', $event)"
    />
  </div>
</template>
