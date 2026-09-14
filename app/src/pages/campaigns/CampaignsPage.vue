<script setup>
/**
 * CampaignsPage.vue - the campaigns screen: the list, and the editor.
 *
 * One screen, two views, like Joinotify's workflows: the list, and a campaign
 * opened for editing. The address follows the view (`&campaign=12`, or
 * `&campaign=new`), so a campaign can be linked to, reloaded and reached with
 * the browser's back button.
 *
 * @since 6.0.0
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CampaignEditor from './components/CampaignEditor.vue';
import CampaignList from './components/CampaignList.vue';
import ProUpsellModal from '../../components/pro/ProUpsellModal.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import { useCampaigns } from '../../composables/useCampaigns';
import { useToasts } from '../../composables/useToasts';
import { __ } from '../../utils/i18n';

const props = defineProps({
  bootstrap: { type: Object, required: true },
});

const { campaigns, save, apply, duplicate, search, preview } = useCampaigns(props.bootstrap);
const { toasts, push, dismiss } = useToasts();

const locked = computed(() => Boolean(props.bootstrap.locked));
const upsell = ref(null);

/**
 * The campaign the address asks for: an ID, 'new', or nothing for the list.
 *
 * @since 6.0.0
 * @return {string}
 */
function requestedCampaign() {
  return new URLSearchParams(window.location.search).get('campaign') || '';
}

const current = ref(requestedCampaign());

const editing = computed(() => {
  if (!current.value || current.value === 'new') {
    return null;
  }

  return campaigns.value.find((campaign) => String(campaign.id) === current.value) || null;
});

// A link to a campaign that is gone lands on the list, not on an empty editor.
const view = computed(() => {
  if (current.value === 'new') {
    return 'editor';
  }

  return editing.value ? 'editor' : 'list';
});

/**
 * Move between the list and a campaign, keeping the address in step.
 *
 * @since 6.0.0
 * @param {string|number} campaign - A campaign ID, 'new', or '' for the list.
 * @param {boolean} [replace] - Replace the history entry instead of adding one.
 * @return {void}
 */
function go(campaign, replace = false) {
  const url = new URL(window.location.href);

  if (campaign) {
    url.searchParams.set('campaign', String(campaign));
  } else {
    url.searchParams.delete('campaign');
  }

  window.history[replace ? 'replaceState' : 'pushState']({}, '', url);
  current.value = String(campaign || '');
  window.scrollTo({ top: 0 });
}

/**
 * Follow the browser's back and forward buttons.
 *
 * @since 6.0.0
 * @return {void}
 */
function onPopState() {
  current.value = requestedCampaign();
}

onMounted(() => window.addEventListener('popstate', onPopState));
onBeforeUnmount(() => window.removeEventListener('popstate', onPopState));

/**
 * Explain what campaigns are, for a store without the licence.
 *
 * @since 6.0.0
 * @return {void}
 */
function showUpsell() {
  upsell.value = {
    label: __('Campanhas'),
    description: __('Descontos e parcelamentos com início e fim, para uma categoria, uma marca ou uma lista de produtos.'),
  };
}

/**
 * Open a new campaign, or explain why it cannot be.
 *
 * @since 6.0.0
 * @return {void}
 */
function create() {
  if (locked.value) {
    showUpsell();

    return;
  }

  go('new');
}

/**
 * Save the campaign being edited, and report it.
 *
 * @since 6.0.0
 * @param {object} campaign - What the editor holds.
 * @return {Promise<object>} What save() answered, for the editor's own errors.
 */
async function onSave(campaign) {
  const result = await save(campaign);

  push(result.message, result.ok ? 'success' : 'error');

  if (result.ok && current.value === 'new') {
    go(result.campaign.id, true);
  }

  return result;
}

/**
 * Apply a status action, and report it.
 *
 * @since 6.0.0
 * @param {{ids: number[], action: string}} request - What to do, to which campaigns.
 * @return {Promise<void>}
 */
async function onAction({ ids, action }) {
  if (action === 'activate' && locked.value) {
    showUpsell();

    return;
  }

  const result = await apply(ids, action);

  push(result.message, result.ok ? 'success' : 'error');
}

/**
 * Copy a campaign, and report it.
 *
 * @since 6.0.0
 * @param {number} id - Campaign ID.
 * @return {Promise<void>}
 */
async function onDuplicate(id) {
  if (locked.value) {
    showUpsell();

    return;
  }

  const result = await duplicate(id);

  push(result.message, result.ok ? 'success' : 'error');
}
</script>

<template>
  <div class="pb-10 pt-4">
    <CampaignList
      v-if="view === 'list'"
      :campaigns="campaigns"
      :bootstrap="bootstrap"
      :locked="locked"
      @create="create"
      @edit="go"
      @action="onAction"
      @duplicate="onDuplicate"
    />

    <CampaignEditor
      v-else
      :key="current"
      :campaign="editing"
      :bootstrap="bootstrap"
      :locked="locked"
      :save="onSave"
      :search="search"
      :preview="preview"
      @back="go('')"
      @upsell="showUpsell"
    />

    <ProUpsellModal :field="upsell" :store-url="bootstrap.pro?.store_url || ''" @close="upsell = null" />

    <ToastStack :toasts="toasts" @dismiss="dismiss" />
  </div>
</template>
