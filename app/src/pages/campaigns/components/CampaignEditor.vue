<script setup>
/**
 * CampaignEditor.vue - one campaign, opened for editing or being created.
 *
 * The form on the left, in the settings screen's groups and rows; the summary
 * on the right, following it. Nothing is written until Save, and the server
 * has the last word: what it refuses comes back beside the group it concerns.
 *
 * @since 6.0.0
 */
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import CampaignSummary from './CampaignSummary.vue';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseConfirmDialog from '../../../components/base/BaseConfirmDialog.vue';
import BaseDatePicker from '../../../components/base/BaseDatePicker.vue';
import BaseInput from '../../../components/base/BaseInput.vue';
import BaseInputGroup from '../../../components/base/BaseInputGroup.vue';
import BaseMultiSelect from '../../../components/base/BaseMultiSelect.vue';
import BaseNotice from '../../../components/base/BaseNotice.vue';
import BaseSelect from '../../../components/base/BaseSelect.vue';
import BaseSwitch from '../../../components/base/BaseSwitch.vue';
import FieldGroup from '../../../components/fields/FieldGroup.vue';
import FieldRow from '../../../components/fields/FieldRow.vue';
import InstallmentFeesField from '../../../components/fields/InstallmentFeesField.vue';
import PageHeader from '../../../components/layout/PageHeader.vue';
import SettingsActionBar from '../../../components/layout/SettingsActionBar.vue';
import SettingsPanel from '../../../components/layout/SettingsPanel.vue';
import { __, sprintf } from '../../../utils/i18n';
import { formatMoney } from '../../../utils/money';

const props = defineProps({
  // Null for a campaign being created.
  campaign: { type: Object, default: null },
  bootstrap: { type: Object, required: true },
  locked: { type: Boolean, default: false },
  save: { type: Function, required: true },
  search: { type: Function, required: true },
  preview: { type: Function, required: true },
});

const emit = defineEmits(['back', 'upsell']);

const environment = computed(() => props.bootstrap.environment || {});
const store = computed(() => props.bootstrap.store || {});
const features = computed(() => props.bootstrap.features || {});

/**
 * A deep copy, so the form never edits the list's objects.
 *
 * @since 6.0.0
 * @param {object} value - Plain data.
 * @return {object}
 */
function clone(value) {
  return JSON.parse(JSON.stringify(value));
}

/**
 * The form's starting point: the campaign, or a blank one switched on.
 *
 * @since 6.0.0
 * @param {object|null} campaign - The campaign as the server holds it.
 * @return {{id: number, title: string, status: string, rules: object}}
 */
function initial(campaign) {
  if (campaign) {
    return clone({ id: campaign.id, title: campaign.title, status: campaign.status, rules: campaign.rules });
  }

  return { id: 0, title: '', status: 'publish', rules: clone(props.bootstrap.defaults || {}) };
}

const form = reactive(initial(props.campaign));
const saved = ref(JSON.stringify(form));
const labels = ref(clone(props.campaign?.labels || { products: {}, terms: {} }));
const state = ref(props.campaign?.state || '');
const errors = ref({});
const saving = ref(false);
const leaving = ref(false);
const reach = ref({ products: null, conflicts: [], loading: false });

const dirty = computed(() => JSON.stringify(form) !== saved.value);
const isNew = computed(() => !form.id);

const scopeOptions = computed(() => [
  { value: 'all', label: __('Todos os produtos') },
  { value: 'products', label: __('Produtos escolhidos') },
  { value: 'categories', label: __('Categorias') },
  { value: 'tags', label: __('Tags') },
  ...(props.bootstrap.taxonomies?.brands ? [{ value: 'brands', label: __('Marcas') }] : []),
]);

const SCOPE_TYPES = { products: 'product', categories: 'product_cat', tags: 'product_tag', brands: 'product_brand' };

const onSaleOptions = [
  { value: 'any', label: __('Com ou sem promoção') },
  { value: 'only', label: __('Só os produtos em promoção') },
  { value: 'exclude', label: __('Só os produtos sem promoção') },
];

const methodOptions = computed(() => [
  { value: 'percentage', label: __('Percentual (%)') },
  { value: 'fixed', label: sprintf(__('Valor fixo (%s)'), environment.value.currency_symbol || 'R$') },
]);

const interestOptions = [
  { value: 'store', label: __('Os juros da loja') },
  { value: 'single', label: __('Uma taxa mensal única') },
  { value: 'table', label: __('Uma tabela por quantidade de parcelas') },
];

const gatewayOptions = computed(() => props.bootstrap.gateways || []);

const scopeSearch = computed(() => {
  const type = SCOPE_TYPES[form.rules.target.scope];

  return (term) => props.search(type, term);
});

const searchProducts = (term) => props.search('product', term);
const searchCategories = (term) => props.search('product_cat', term);

const scopeLabels = computed(() => (form.rules.target.scope === 'products' ? labels.value.products : labels.value.terms));

// The interest table covers the counts the campaign charges interest on.
const tableRange = computed(() => {
  const plan = form.rules.installments;
  const max = plan.max !== '' ? Number(plan.max) : Number(store.value.max) || 12;
  const withoutFee = plan.without_fee !== '' ? Number(plan.without_fee) : Number(store.value.without_fee) || 0;

  return { max, first: Math.min(max, withoutFee + 1) };
});

// A scope's list means nothing in another scope.
watch(
  () => form.rules.target.scope,
  (scope, previous) => {
    if (previous !== undefined && scope !== previous) {
      form.rules.target.ids = [];
    }
  },
);

let previewTimer = null;

/**
 * Ask the server what the target reaches, a moment after it stops changing.
 *
 * @since 6.0.0
 * @return {void}
 */
function schedulePreview() {
  clearTimeout(previewTimer);
  reach.value = { ...reach.value, loading: true };

  previewTimer = setTimeout(async () => {
    const answer = await props.preview({ id: form.id, rules: form.rules });

    reach.value = answer ? { ...answer, loading: false } : { products: null, conflicts: [], loading: false };
  }, 400);
}

watch(
  () => JSON.stringify([form.rules.target, form.rules.starts_at, form.rules.ends_at, form.rules.discount.enabled, form.rules.installments.enabled]),
  schedulePreview,
);

/**
 * Save the campaign, and take in what the server kept.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function onSave() {
  if (props.locked) {
    emit('upsell');

    return;
  }

  saving.value = true;

  const result = await props.save(clone(form));

  saving.value = false;
  errors.value = result.errors || {};

  if (result.ok && result.campaign) {
    Object.assign(form, initial(result.campaign));
    saved.value = JSON.stringify(form);
    labels.value = clone(result.campaign.labels || labels.value);
    state.value = result.campaign.state;
  }
}

/**
 * Put the form back as it was last saved.
 *
 * @since 6.0.0
 * @return {void}
 */
function onDiscard() {
  Object.assign(form, JSON.parse(saved.value));
  errors.value = {};
}

/**
 * Go back to the list, asking first when there are unsaved changes.
 *
 * @since 6.0.0
 * @return {void}
 */
function onBack() {
  if (dirty.value) {
    leaving.value = true;

    return;
  }

  emit('back');
}

/**
 * Keep the browser from dropping unsaved changes on reload or close.
 *
 * @since 6.0.0
 * @param {BeforeUnloadEvent} event - The unload event.
 * @return {void}
 */
function onBeforeUnload(event) {
  if (dirty.value) {
    event.preventDefault();
    event.returnValue = '';
  }
}

onMounted(() => {
  window.addEventListener('beforeunload', onBeforeUnload);
  schedulePreview();
});

onBeforeUnmount(() => {
  clearTimeout(previewTimer);
  window.removeEventListener('beforeunload', onBeforeUnload);
});

/**
 * What the store does when a campaign field is left blank.
 *
 * @since 6.0.0
 * @param {string} value - The store's value, already written for the reader.
 * @return {string}
 */
function keeps(value) {
  return sprintf(__('Em branco, vale o da loja: %s.'), value);
}
</script>

<template>
  <div>
    <BaseButton variant="link" icon="arrow-left" class="mb-4" @click="onBack">{{ __('Todas as campanhas') }}</BaseButton>

    <PageHeader :title="isNew ? __('Nova campanha') : form.title || __('Campanha sem nome')">
      <template #description>
        {{ __('Escolha os produtos, o período e o que muda neles. Um produto com desconto próprio mantém o dele.') }}
      </template>

      <template #actions>
        <div class="flex items-center gap-3 rounded-[8px] bg-white px-4 py-2.5 ring-1 ring-slate-200">
          <BaseSwitch
            id="wcci-campaign-status"
            :model-value="form.status"
            true-value="publish"
            false-value="draft"
            :aria-label="__('Campanha ativa')"
            @update:model-value="form.status = $event"
          />
          <span class="text-[14px] font-semibold text-slate-700">
            {{ form.status === 'publish' ? __('Ativa') : __('Inativa') }}
          </span>
        </div>

        <BaseButton icon="save" :loading="saving" :disabled="!dirty && !isNew" @click="onSave">
          {{ __('Salvar campanha') }}
        </BaseButton>
      </template>
    </PageHeader>

    <BaseNotice v-if="locked" tone="info" icon="crown" class="mt-8">
      {{ __('Campanhas fazem parte do Pro. Você pode ver esta campanha, mas salvar exige uma licença ativa.') }}
    </BaseNotice>

    <BaseNotice v-if="Object.keys(errors).length" tone="danger" class="mt-8" live :title="__('Revise os campos destacados antes de salvar.')">
      <ul class="list-disc space-y-1 pl-5">
        <li v-for="(message, key) in errors" :key="key">{{ message }}</li>
      </ul>
    </BaseNotice>

    <div class="mt-8 grid items-start gap-8 2xl:grid-cols-[minmax(0,1fr)_340px]">
      <SettingsPanel>
        <div class="flex flex-col gap-4">
          <FieldGroup :title="__('Campanha')" :description="__('Como ela aparece na lista, e quem vence quando duas valem para o mesmo produto.')">
            <FieldRow :label="__('Nome')" :description="__('Só você vê este nome.')" control-id="wcci-campaign-title">
              <BaseInput
                id="wcci-campaign-title"
                v-model="form.title"
                :placeholder="__('Black Friday de calçados')"
                :aria-label="__('Nome')"
              />
              <p v-if="errors.title" class="mt-2 text-[13px] text-rose-600">{{ errors.title }}</p>
            </FieldRow>

            <FieldRow
              :label="__('Prioridade')"
              :description="__('Quando duas campanhas valem para o mesmo produto, vence a de maior prioridade.')"
              control-id="wcci-campaign-priority"
            >
              <BaseInput id="wcci-campaign-priority" v-model="form.rules.priority" type="number" step="1" :aria-label="__('Prioridade')" />
            </FieldRow>
          </FieldGroup>

          <FieldGroup :title="__('Produtos')" :description="__('Quais produtos a campanha alcança. As variações seguem o produto.')">
            <FieldRow :label="__('Aplicar a')" control-id="wcci-campaign-scope">
              <BaseSelect
                id="wcci-campaign-scope"
                v-model="form.rules.target.scope"
                :options="scopeOptions"
                :aria-label="__('Aplicar a')"
              />
            </FieldRow>

            <FieldRow
              v-if="form.rules.target.scope !== 'all'"
              :label="scopeOptions.find((option) => option.value === form.rules.target.scope)?.label || ''"
              :description="form.rules.target.scope === 'categories' ? __('As subcategorias entram junto.') : ''"
              control-id="wcci-campaign-ids"
            >
              <BaseMultiSelect
                id="wcci-campaign-ids"
                v-model="form.rules.target.ids"
                :search="scopeSearch"
                :labels="scopeLabels"
                :invalid="Boolean(errors.target)"
                :aria-label="__('Buscar e escolher')"
                :placeholder="__('Digite para buscar')"
              />
              <p v-if="errors.target" class="mt-2 text-[13px] text-rose-600">{{ errors.target }}</p>
            </FieldRow>

            <FieldRow :label="__('Deixar de fora estes produtos')" control-id="wcci-campaign-exclude-products">
              <BaseMultiSelect
                id="wcci-campaign-exclude-products"
                v-model="form.rules.target.exclude_products"
                :search="searchProducts"
                :labels="labels.products"
                :aria-label="__('Produtos deixados de fora')"
                :placeholder="__('Nenhum')"
              />
            </FieldRow>

            <FieldRow
              v-if="bootstrap.taxonomies?.categories"
              :label="__('Deixar de fora estas categorias')"
              :description="__('Com as subcategorias.')"
              control-id="wcci-campaign-exclude-categories"
            >
              <BaseMultiSelect
                id="wcci-campaign-exclude-categories"
                v-model="form.rules.target.exclude_categories"
                :search="searchCategories"
                :labels="labels.terms"
                :aria-label="__('Categorias deixadas de fora')"
                :placeholder="__('Nenhuma')"
              />
            </FieldRow>

            <FieldRow :label="__('Produtos em promoção')" control-id="wcci-campaign-on-sale">
              <BaseSelect
                id="wcci-campaign-on-sale"
                v-model="form.rules.target.on_sale"
                :options="onSaleOptions"
                :aria-label="__('Produtos em promoção')"
              />
            </FieldRow>
          </FieldGroup>

          <FieldGroup
            :title="__('Período')"
            :description="
              sprintf(
                __('No horário da loja (%s). Sem início, vale assim que for ativada; sem fim, até ser desativada.'),
                bootstrap.timezone || ''
              )
            "
          >
            <FieldRow :label="__('Começa em')" control-id="wcci-campaign-starts">
              <BaseDatePicker
                id="wcci-campaign-starts"
                v-model="form.rules.starts_at"
                with-time
                default-time="00:00"
                :aria-label="__('Começa em')"
                :placeholder="__('Assim que for ativada')"
              />
            </FieldRow>

            <FieldRow :label="__('Termina em')" :description="__('A campanha vale até o último segundo deste minuto.')" control-id="wcci-campaign-ends">
              <BaseDatePicker
                id="wcci-campaign-ends"
                v-model="form.rules.ends_at"
                with-time
                default-time="23:59"
                :min="form.rules.starts_at"
                :invalid="Boolean(errors.ends_at)"
                :aria-label="__('Termina em')"
                :placeholder="__('Até ser desativada')"
              />
              <p v-if="errors.ends_at" class="mt-2 text-[13px] text-rose-600">{{ errors.ends_at }}</p>
            </FieldRow>
          </FieldGroup>

          <FieldGroup
            :title="__('Desconto')"
            :description="__('Substitui o desconto à vista da loja (Pix e boleto) nos produtos da campanha.')"
          >
            <FieldRow :label="__('Dar um desconto')" control-id="wcci-campaign-discount">
              <BaseSwitch
                id="wcci-campaign-discount"
                v-model="form.rules.discount.enabled"
                :true-value="true"
                :false-value="false"
                :aria-label="__('Dar um desconto')"
              />
              <p v-if="errors.effects" class="mt-2 text-[13px] text-rose-600">{{ errors.effects }}</p>
            </FieldRow>

            <template v-if="form.rules.discount.enabled">
              <FieldRow :label="__('Tipo de desconto')" control-id="wcci-campaign-discount-method">
                <BaseSelect
                  id="wcci-campaign-discount-method"
                  v-model="form.rules.discount.method"
                  :options="methodOptions"
                  :aria-label="__('Tipo de desconto')"
                />
              </FieldRow>

              <FieldRow :label="__('Valor do desconto')" control-id="wcci-campaign-discount-amount">
                <BaseInputGroup>
                  <template v-if="form.rules.discount.method === 'fixed'" #prefix>
                    <span class="px-3">{{ environment.currency_symbol || 'R$' }}</span>
                  </template>

                  <BaseInput
                    id="wcci-campaign-discount-amount"
                    v-model="form.rules.discount.amount"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    min="0"
                    :aria-label="__('Valor do desconto')"
                    bare
                  />

                  <template v-if="form.rules.discount.method !== 'fixed'" #suffix>
                    <span class="px-3">%</span>
                  </template>
                </BaseInputGroup>
                <p v-if="errors.discount" class="mt-2 text-[13px] text-rose-600">{{ errors.discount }}</p>
              </FieldRow>

              <FieldRow
                :label="__('Cobrar na forma de pagamento')"
                :description="__('Com uma forma escolhida, o desconto também é aplicado no checkout quando o cliente paga com ela.')"
                control-id="wcci-campaign-discount-gateway"
              >
                <BaseSelect
                  id="wcci-campaign-discount-gateway"
                  v-model="form.rules.discount.gateway"
                  :options="gatewayOptions"
                  :aria-label="__('Cobrar na forma de pagamento')"
                />
              </FieldRow>
            </template>
          </FieldGroup>

          <FieldGroup
            :title="__('Parcelamento')"
            :description="__('Substitui o parcelamento da loja nos produtos da campanha. O que ficar em branco continua como na loja.')"
          >
            <FieldRow :label="__('Mudar o parcelamento')" control-id="wcci-campaign-installments">
              <BaseSwitch
                id="wcci-campaign-installments"
                v-model="form.rules.installments.enabled"
                :true-value="true"
                :false-value="false"
                :aria-label="__('Mudar o parcelamento')"
              />
            </FieldRow>

            <template v-if="form.rules.installments.enabled">
              <FieldRow :label="__('Máximo de parcelas')" :description="keeps(String(store.max))" control-id="wcci-campaign-max">
                <BaseInput id="wcci-campaign-max" v-model="form.rules.installments.max" type="number" min="1" max="48" :placeholder="String(store.max)" :aria-label="__('Máximo de parcelas')" />
              </FieldRow>

              <FieldRow :label="__('Parcelas sem juros')" :description="keeps(String(store.without_fee))" control-id="wcci-campaign-without-fee">
                <BaseInput
                  id="wcci-campaign-without-fee"
                  v-model="form.rules.installments.without_fee"
                  type="number"
                  min="0"
                  max="48"
                  :placeholder="String(store.without_fee)"
                  :aria-label="__('Parcelas sem juros')"
                />
              </FieldRow>

              <FieldRow
                :label="__('Valor mínimo da parcela')"
                :description="keeps(formatMoney(store.min_value, environment))"
                control-id="wcci-campaign-min-value"
              >
                <BaseInputGroup>
                  <template #prefix>
                    <span class="px-3">{{ environment.currency_symbol || 'R$' }}</span>
                  </template>

                  <BaseInput
                    id="wcci-campaign-min-value"
                    v-model="form.rules.installments.min_value"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    min="0"
                    :placeholder="String(store.min_value)"
                    :aria-label="__('Valor mínimo da parcela')"
                    bare
                  />
                </BaseInputGroup>
              </FieldRow>

              <FieldRow :label="__('Juros')" control-id="wcci-campaign-interest">
                <BaseSelect
                  id="wcci-campaign-interest"
                  v-model="form.rules.installments.interest"
                  :options="interestOptions"
                  :aria-label="__('Juros')"
                />
              </FieldRow>

              <FieldRow
                v-if="form.rules.installments.interest === 'single'"
                :label="__('Taxa de juros mensal')"
                :description="__('Composta, aplicada a cada parcela além das sem juros.')"
                control-id="wcci-campaign-fee"
              >
                <BaseInputGroup>
                  <BaseInput
                    id="wcci-campaign-fee"
                    v-model="form.rules.installments.fee"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    min="0"
                    :aria-label="__('Taxa de juros mensal')"
                    bare
                  />

                  <template #suffix>
                    <span class="px-3">%</span>
                  </template>
                </BaseInputGroup>
              </FieldRow>

              <FieldRow
                v-if="form.rules.installments.interest === 'table'"
                :label="__('Tabela de juros da campanha')"
                :description="__('Para cada quantidade de parcelas com juros, um valor fixo, um percentual sobre o preço ou os dois. O valor fixo é somado uma vez ao total. Uma linha vazia é oferecida sem juros.')"
                :locked="!features.fee_per_installment"
                wide
              >
                <InstallmentFeesField
                  v-model="form.rules.installments.fees"
                  :field="{ key: 'fees' }"
                  :disabled="!features.fee_per_installment"
                  :max-installments="tableRange.max"
                  :first-installment="tableRange.first"
                  :environment="environment"
                />
              </FieldRow>

              <p v-if="errors.installments" class="pb-6 text-[13px] text-rose-600">{{ errors.installments }}</p>
            </template>
          </FieldGroup>
        </div>

        <template #footer>
          <SettingsActionBar
            :dirty="dirty || isNew"
            :saving="saving"
            :save-label="__('Salvar campanha')"
            @save="onSave"
            @discard="onDiscard"
          />
        </template>
      </SettingsPanel>

      <div class="2xl:sticky 2xl:top-12">
        <CampaignSummary
          :rules="form.rules"
          :environment="environment"
          :state="dirty ? '' : state"
          :products="reach.products"
          :loading="reach.loading"
          :conflicts="reach.conflicts"
        />
      </div>
    </div>

    <BaseConfirmDialog
      :open="leaving"
      :title="__('Sair sem salvar?')"
      :description="__('As alterações feitas nesta campanha serão perdidas.')"
      :confirm-label="__('Sair sem salvar')"
      @confirm="emit('back')"
      @cancel="leaving = false"
    />
  </div>
</template>
