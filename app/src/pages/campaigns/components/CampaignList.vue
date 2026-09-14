<script setup>
/**
 * CampaignList.vue - every campaign, filtered by where it stands.
 *
 * Tabs by state (running, scheduled, ended, inactive, trash), a search on the
 * name, a switch per row, and bulk actions over the rows ticked. Filtering and
 * searching happen in the browser, over the list the server sent.
 *
 * @since 6.0.0
 */
import { computed, ref, watch } from 'vue';
import BaseBadge from '../../../components/base/BaseBadge.vue';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseCheckbox from '../../../components/base/BaseCheckbox.vue';
import BaseConfirmDialog from '../../../components/base/BaseConfirmDialog.vue';
import BaseIcon from '../../../components/base/BaseIcon.vue';
import BaseIconButton from '../../../components/base/BaseIconButton.vue';
import BaseInput from '../../../components/base/BaseInput.vue';
import BaseInputGroup from '../../../components/base/BaseInputGroup.vue';
import BaseNotice from '../../../components/base/BaseNotice.vue';
import BaseSwitch from '../../../components/base/BaseSwitch.vue';
import BaseTabs from '../../../components/base/BaseTabs.vue';
import PageHeader from '../../../components/layout/PageHeader.vue';
import SettingsPanel from '../../../components/layout/SettingsPanel.vue';
import { describeEffects, describePeriod, describeTarget } from '../../../composables/useCampaigns';
import { __, _n, sprintf } from '../../../utils/i18n';

const props = defineProps({
  campaigns: { type: Array, default: () => [] },
  bootstrap: { type: Object, required: true },
  locked: { type: Boolean, default: false },
});

const emit = defineEmits(['create', 'edit', 'action', 'duplicate']);

// `label` is the badge on one campaign; `tab` names the filter over many.
const STATES = {
  running: { label: __('Em andamento'), tab: __('Em andamento'), tone: 'success' },
  scheduled: { label: __('Agendada'), tab: __('Agendadas'), tone: 'info' },
  ended: { label: __('Encerrada'), tab: __('Encerradas'), tone: 'neutral' },
  inactive: { label: __('Inativa'), tab: __('Inativas'), tone: 'neutral' },
  trash: { label: __('Na lixeira'), tab: __('Lixeira'), tone: 'danger' },
};

const tab = ref('all');
const query = ref('');
const selected = ref([]);
const deleting = ref([]);

const alive = computed(() => props.campaigns.filter((campaign) => campaign.state !== 'trash'));

const counts = computed(() => {
  const result = { all: alive.value.length };

  Object.keys(STATES).forEach((state) => {
    result[state] = props.campaigns.filter((campaign) => campaign.state === state).length;
  });

  return result;
});

const tabs = computed(() => [
  { id: 'all', title: sprintf(__('Todas (%d)'), counts.value.all) },
  ...Object.entries(STATES)
    .filter(([state]) => state !== 'trash' || counts.value.trash > 0)
    .map(([state, { tab: title }]) => ({ id: state, title: sprintf(__('%s (%d)'), title, counts.value[state]) })),
]);

const visible = computed(() => {
  const term = query.value.trim().toLowerCase();
  const pool = tab.value === 'all' ? alive.value : props.campaigns.filter((campaign) => campaign.state === tab.value);

  return term ? pool.filter((campaign) => campaign.title.toLowerCase().includes(term)) : pool;
});

const inTrash = computed(() => tab.value === 'trash');
const allSelected = computed(() => visible.value.length > 0 && visible.value.every((campaign) => selected.value.includes(campaign.id)));
const licenseUrl = computed(() => props.bootstrap.links?.license || '');
const storeUrl = computed(() => props.bootstrap.pro?.store_url || '');

// A selection belongs to what is on screen.
watch([tab, query], () => {
  selected.value = [];
});

watch(
  () => props.campaigns,
  (campaigns) => {
    const ids = campaigns.map((campaign) => campaign.id);

    selected.value = selected.value.filter((id) => ids.includes(id));

    // The trash tab goes away once emptied; do not leave the reader on it.
    if (tab.value === 'trash' && !counts.value.trash) {
      tab.value = 'all';
    }
  },
);

/**
 * Tick or untick one row.
 *
 * @since 6.0.0
 * @param {number} id - Campaign ID.
 * @return {void}
 */
function toggle(id) {
  selected.value = selected.value.includes(id) ? selected.value.filter((item) => item !== id) : [...selected.value, id];
}

/**
 * Tick or untick every row on screen.
 *
 * @since 6.0.0
 * @return {void}
 */
function toggleAll() {
  selected.value = allSelected.value ? [] : visible.value.map((campaign) => campaign.id);
}

/**
 * Ask the page to apply an action.
 *
 * @since 6.0.0
 * @param {number[]} ids - Campaign IDs.
 * @param {string} action - The action.
 * @return {void}
 */
function act(ids, action) {
  if (action === 'delete') {
    deleting.value = ids;

    return;
  }

  emit('action', { ids, action });
}

/**
 * Delete for good, once confirmed.
 *
 * @since 6.0.0
 * @return {void}
 */
function confirmDelete() {
  emit('action', { ids: deleting.value, action: 'delete' });
  deleting.value = [];
}

/**
 * The line under a campaign's name: what it targets and what it changes.
 *
 * @since 6.0.0
 * @param {object} campaign - The campaign.
 * @return {string}
 */
function summary(campaign) {
  return [describeTarget(campaign.rules.target), ...describeEffects(campaign.rules, props.bootstrap.environment || {})].join(' · ');
}
</script>

<template>
  <div>
    <PageHeader
      :title="__('Campanhas')"
      :description="__('Descontos e parcelamentos com data para começar e terminar, para uma categoria, uma marca ou uma lista de produtos.')"
    >
      <template #actions>
        <BaseButton icon="plus" @click="emit('create')">{{ __('Nova campanha') }}</BaseButton>
      </template>
    </PageHeader>

    <BaseNotice v-if="locked" tone="info" icon="crown" class="mt-8" :title="__('Campanhas fazem parte do Pro')">
      {{
        bootstrap.pro?.active
          ? __('Ative sua licença para criar, editar e ativar campanhas. As campanhas já salvas continuam guardadas e podem ser desativadas ou excluídas.')
          : __('Com o Parcelas Customizadas Pro você cria campanhas de desconto e parcelamento com início e fim. As campanhas já salvas continuam guardadas.')
      }}

      <template #actions>
        <BaseButton v-if="bootstrap.pro?.active && licenseUrl" size="sm" icon="key" :href="licenseUrl">
          {{ __('Ativar licença') }}
        </BaseButton>
        <BaseButton v-else-if="storeUrl" size="sm" icon="crown" :href="storeUrl" external>
          {{ __('Conhecer o Pro') }}
        </BaseButton>
      </template>
    </BaseNotice>

    <SettingsPanel class="mt-8" :padded="false">
      <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-5 sm:px-8 xl:flex-row xl:items-center xl:justify-between">
        <BaseTabs v-model="tab" variant="pills" :tabs="tabs" :aria-label="__('Filtrar campanhas')" id-prefix="wcci-campaign-tab" />

        <div class="w-full xl:w-72">
          <BaseInputGroup>
            <template #prefix>
              <span class="px-3"><BaseIcon name="search" :size="18" /></span>
            </template>

            <BaseInput v-model="query" type="search" :aria-label="__('Buscar campanhas')" :placeholder="__('Buscar campanhas')" bare />
          </BaseInputGroup>
        </div>
      </div>

      <div
        v-if="selected.length"
        class="flex flex-wrap items-center gap-3 border-b border-slate-100 bg-primary-50/50 px-6 py-3 sm:px-8"
        aria-live="polite"
      >
        <span class="text-[13px] font-semibold text-slate-700">
          {{ sprintf(_n('%d selecionada', '%d selecionadas', selected.length), selected.length) }}
        </span>

        <template v-if="inTrash">
          <BaseButton size="sm" variant="secondary" icon="undo" @click="act(selected, 'restore')">{{ __('Restaurar') }}</BaseButton>
          <BaseButton size="sm" variant="danger-outline" icon="trash" @click="act(selected, 'delete')">
            {{ __('Excluir permanentemente') }}
          </BaseButton>
        </template>

        <template v-else>
          <BaseButton size="sm" variant="secondary" @click="act(selected, 'activate')">{{ __('Ativar') }}</BaseButton>
          <BaseButton size="sm" variant="secondary" @click="act(selected, 'deactivate')">{{ __('Desativar') }}</BaseButton>
          <BaseButton size="sm" variant="danger-outline" icon="trash" @click="act(selected, 'trash')">
            {{ __('Mover para a lixeira') }}
          </BaseButton>
        </template>

        <BaseButton size="sm" variant="ghost" @click="selected = []">{{ __('Limpar seleção') }}</BaseButton>
      </div>

      <div v-if="!alive.length && tab === 'all' && !query" class="flex flex-col items-center px-6 py-16 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-700">
          <BaseIcon name="megaphone" :size="28" />
        </span>
        <h2 class="mt-5 text-lg font-semibold text-ink">{{ __('Nenhuma campanha ainda') }}</h2>
        <p class="mt-2 max-w-md text-[14px] leading-6 text-slate-500">
          {{ __('Crie uma campanha para dar um desconto ou mudar o parcelamento de uma categoria, uma marca ou uma lista de produtos, com data para começar e terminar.') }}
        </p>
        <BaseButton class="mt-6" icon="plus" @click="emit('create')">{{ __('Criar a primeira campanha') }}</BaseButton>
      </div>

      <p v-else-if="!visible.length" class="px-6 py-12 text-center text-[14px] text-slate-500 sm:px-8">
        {{ __('Nenhuma campanha corresponde a este filtro.') }}
      </p>

      <div v-else>
        <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-3 sm:px-8">
          <BaseCheckbox :model-value="allSelected" :true-value="true" :false-value="false" @update:model-value="toggleAll">
            <span class="text-[13px] font-semibold text-slate-500">{{ __('Selecionar todas') }}</span>
          </BaseCheckbox>
        </div>

        <ul class="divide-y divide-slate-100">
          <li
            v-for="campaign in visible"
            :key="campaign.id"
            class="flex flex-wrap items-center gap-x-4 gap-y-3 px-6 py-5 transition-colors hover:bg-slate-50/70 sm:px-8"
          >
            <BaseCheckbox
              :model-value="selected.includes(campaign.id)"
              :true-value="true"
              :false-value="false"
              @update:model-value="toggle(campaign.id)"
            >
              <span class="sr-only">{{ sprintf(__('Selecionar %s'), campaign.title) }}</span>
            </BaseCheckbox>

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <BaseButton
                  v-if="campaign.state !== 'trash'"
                  variant="link"
                  class="!text-[15px] font-semibold"
                  @click="emit('edit', campaign.id)"
                >
                  {{ campaign.title }}
                </BaseButton>
                <span v-else class="text-[15px] font-semibold text-slate-700">{{ campaign.title }}</span>

                <BaseBadge :tone="STATES[campaign.state]?.tone || 'neutral'">
                  {{ STATES[campaign.state]?.label || campaign.state }}
                </BaseBadge>
              </div>

              <p class="mt-1 truncate text-[13px] text-slate-600">{{ summary(campaign) }}</p>

              <p class="mt-1 flex items-center gap-1.5 text-[13px] text-slate-400">
                <BaseIcon name="calendar" :size="14" />
                <span>{{ describePeriod(campaign.rules) }}</span>
              </p>
            </div>

            <div class="flex items-center gap-1">
              <template v-if="campaign.state !== 'trash'">
                <BaseSwitch
                  class="mr-2"
                  :model-value="campaign.status === 'publish'"
                  :true-value="true"
                  :false-value="false"
                  :aria-label="sprintf(__('Campanha %s ativa'), campaign.title)"
                  @update:model-value="act([campaign.id], $event ? 'activate' : 'deactivate')"
                />
                <BaseIconButton icon="edit" :label="__('Editar')" @click="emit('edit', campaign.id)" />
                <BaseIconButton icon="copy" :label="__('Duplicar')" @click="emit('duplicate', campaign.id)" />
                <BaseIconButton icon="trash" tone="danger" :label="__('Mover para a lixeira')" @click="act([campaign.id], 'trash')" />
              </template>

              <template v-else>
                <BaseIconButton icon="undo" :label="__('Restaurar')" @click="act([campaign.id], 'restore')" />
                <BaseIconButton
                  icon="trash"
                  tone="danger"
                  :label="__('Excluir permanentemente')"
                  @click="act([campaign.id], 'delete')"
                />
              </template>
            </div>
          </li>
        </ul>
      </div>
    </SettingsPanel>

    <BaseConfirmDialog
      :open="deleting.length > 0"
      :title="__('Excluir permanentemente?')"
      :description="
        sprintf(
          _n(
            '%d campanha será excluída e não poderá ser recuperada.',
            '%d campanhas serão excluídas e não poderão ser recuperadas.',
            deleting.length,
          ),
          deleting.length,
        )
      "
      :confirm-label="__('Excluir permanentemente')"
      @confirm="confirmDelete"
      @cancel="deleting = []"
    />
  </div>
</template>
