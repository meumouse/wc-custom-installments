<script setup>
/**
 * OnboardingPage.vue - the setup wizard.
 *
 * Seven steps, each saving as the reader leaves it. The controls come from the
 * same schema the settings screen renders, so a field's label, options and
 * gating are described once.
 *
 * It wears the same page header as the settings screen — logo, plugin name —
 * with the steps and the progress under it and the step itself in one panel.
 *
 * @since 6.0.0
 */
import { computed, ref } from 'vue';
import PricePreview from './components/PricePreview.vue';
import TrialForm from './components/TrialForm.vue';
import BaseButton from '../../components/base/BaseButton.vue';
import BaseCheckbox from '../../components/base/BaseCheckbox.vue';
import BaseLabel from '../../components/base/BaseLabel.vue';
import BaseNotice from '../../components/base/BaseNotice.vue';
import BaseProgress from '../../components/base/BaseProgress.vue';
import BaseStepper from '../../components/base/BaseStepper.vue';
import FieldRenderer from '../../components/fields/FieldRenderer.vue';
import PageHeader from '../../components/layout/PageHeader.vue';
import SettingsPanel from '../../components/layout/SettingsPanel.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import { useOnboarding } from '../../composables/useOnboarding';
import { useToasts } from '../../composables/useToasts';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  bootstrap: { type: Object, required: true },
});

const {
  api,
  steps,
  fields,
  settings,
  migrated,
  index,
  current,
  isFirst,
  isLast,
  isSaving,
  progress,
  next,
  back,
  goTo,
  complete,
  skip,
} = useOnboarding(props.bootstrap);

const { toasts, push, dismiss } = useToasts();
const finished = ref(false);

const links = computed(() => props.bootstrap.links || {});
const pro = computed(() => props.bootstrap.pro || {});
const environment = computed(() => props.bootstrap.environment || {});
const brands = computed(() => props.bootstrap.brands || {});

// Which fields each step shows. Kept here because it is a question about this
// screen's flow, not about what a setting means.
const STEP_FIELDS = {
  installments: [
    'max_qtd_installments',
    'max_qtd_installments_without_fee',
    'fee_installments_global',
    'min_value_installments',
  ],
  discount: ['enable_all_discount_options', 'product_price_discount_method', 'discount_main_price'],
  'payment-methods': [
    'enable_pix_method_payment_form',
    'enable_ticket_method_payment_form',
    'enable_credit_card_method_payment_form',
    'enable_debit_card_method_payment_form',
  ],
  display: ['hook_display_best_installments', 'display_discount_price_hook', 'display_installment_type'],
};

const stepFields = computed(() =>
  (STEP_FIELDS[current.value?.id] || []).map((key) => fields.value[key]).filter(Boolean),
);

const showsPreview = computed(() => ['installments', 'discount', 'display'].includes(current.value?.id));

const creditBrands = computed(() =>
  Object.entries(brands.value).map(([key, label]) => ({ key: `enable_${key}_flag_credit`, label })),
);

/**
 * Save and move on, reporting anything that went wrong.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function onNext() {
  const before = index.value;

  await next();

  if (index.value === before && !isLast.value) {
    push(__('Não foi possível salvar. Tente novamente.'), 'error');
  }
}

/**
 * Finish and go to the settings screen.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function onFinish() {
  await complete();
  finished.value = true;
  window.location.href = links.value.settings_url || window.location.href;
}

/**
 * Leave the wizard without finishing.
 *
 * @since 6.0.0
 * @return {Promise<void>}
 */
async function onSkip() {
  await skip();
  window.location.href = links.value.settings_url || window.location.href;
}
</script>

<template>
  <div class="mx-auto max-w-3xl pb-10 pt-4">
    <PageHeader :title="__('Configuração')" :description="sprintf(__('Etapa %1$d de %2$d'), index + 1, steps.length)">
      <template #actions>
        <BaseButton variant="ghost" size="sm" :disabled="isSaving" @click="onSkip">
          {{ __('Pular configuração') }}
        </BaseButton>
      </template>
    </PageHeader>

    <div class="mt-8 flex flex-col gap-4">
      <BaseStepper :steps="steps" :index="index" :aria-label="__('Etapas da configuração')" @select="goTo" />
      <BaseProgress :value="progress" :label="__('Progresso da configuração')" />
    </div>

    <SettingsPanel class="mt-6">
      <h2 class="text-2xl font-semibold tracking-tight text-ink">{{ current?.title }}</h2>
      <p class="mt-1 text-[14px] leading-6 text-slate-500">{{ current?.summary }}</p>

      <div class="mt-8">
        <!-- Welcome -->
        <div v-if="current?.id === 'welcome'" class="flex flex-col gap-4 text-[14px] leading-6 text-slate-600">
          <p>
            {{ __('Este plugin responde à pergunta que todo cliente faz antes de comprar: quanto fica por mês e quanto eu economizo pagando à vista.') }}
          </p>

          <BaseNotice v-if="migrated" tone="success">
            {{ __('Encontramos as configurações da sua instalação anterior e as importamos. Nada foi alterado — você pode revisar tudo na tela de configurações.') }}
          </BaseNotice>

          <p>
            {{ __('As próximas perguntas definem as regras de parcelamento. Você pode alterar qualquer uma delas depois.') }}
          </p>
        </div>

        <!-- The schema-driven steps -->
        <div v-else-if="stepFields.length" class="flex flex-col gap-6">
          <div class="divide-y divide-slate-100">
            <FieldRenderer
              v-for="field in stepFields"
              :key="field.key"
              v-model="settings[field.key]"
              :field="field"
            />
          </div>

          <!-- Card brands only matter once the card row is on. -->
          <div v-if="current?.id === 'payment-methods' && settings.enable_credit_card_method_payment_form === 'yes'">
            <BaseLabel>{{ __('Bandeiras de cartão de crédito') }}</BaseLabel>

            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
              <BaseCheckbox
                v-for="brand in creditBrands"
                :key="brand.key"
                v-model="settings[brand.key]"
                :label="brand.label"
                tile
              />
            </div>
          </div>

          <PricePreview v-if="showsPreview" :settings="settings" :environment="environment" />
        </div>

        <!-- Pro trial -->
        <TrialForm v-else-if="current?.id === 'trial'" :api="api" :pro="pro" :links="links" />

        <!-- Finish -->
        <div v-else-if="current?.id === 'finish'" class="flex flex-col gap-4">
          <!-- A migrated store lands straight here, so this is where it has to
               be told what happened to its old settings. -->
          <BaseNotice v-if="migrated" tone="success">
            {{ __('Encontramos as configurações da sua instalação anterior e as importamos exatamente como estavam. Nada foi alterado, e suas opções antigas continuam onde estavam caso você volte à versão anterior.') }}
          </BaseNotice>

          <p class="text-[14px] leading-6 text-slate-600">
            {{ migrated
              ? __('Não há mais nada para configurar. Revise o que quiser na tela de configurações.')
              : __('Sua loja está exibindo parcelamentos. A partir daqui, você pode ajustar os textos, a posição e a aparência.') }}
          </p>

          <PricePreview :settings="settings" :environment="environment" />

          <div class="flex flex-wrap gap-3">
            <BaseButton v-if="links.shop_url" variant="secondary" icon="store" :href="links.shop_url" external>
              {{ __('Ver sua loja') }}
            </BaseButton>
            <BaseButton v-if="links.docs_url" variant="secondary" icon="book-open" :href="links.docs_url" external>
              {{ __('Ler a documentação') }}
            </BaseButton>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-6 py-5 sm:px-10">
          <BaseButton v-if="!isFirst" variant="secondary" icon="arrow-left" :disabled="isSaving" @click="back">
            {{ __('Voltar') }}
          </BaseButton>
          <span v-else />

          <BaseButton
            v-if="!isLast"
            icon="arrow-right"
            icon-position="end"
            :loading="isSaving"
            @click="onNext"
          >
            {{ isSaving ? __('Salvando…') : __('Continuar') }}
          </BaseButton>

          <BaseButton v-else icon="check" :loading="isSaving || finished" @click="onFinish">
            {{ __('Ir para as configurações') }}
          </BaseButton>
        </div>
      </template>
    </SettingsPanel>

    <ToastStack :toasts="toasts" @dismiss="dismiss" />
  </div>
</template>
