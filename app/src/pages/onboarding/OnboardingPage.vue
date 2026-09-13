<script setup>
/**
 * OnboardingPage.vue - the setup wizard.
 *
 * Seven steps, each saving as the reader leaves it. The controls come from the
 * same schema the settings screen renders, so a field's label, options and
 * gating are described once.
 *
 * @since 1.0.0
 */
import { computed, ref } from 'vue';
import StepIndicator from './components/StepIndicator.vue';
import PricePreview from './components/PricePreview.vue';
import TrialForm from './components/TrialForm.vue';
import BaseButton from '../../components/base/BaseButton.vue';
import FieldRenderer from '../../components/fields/FieldRenderer.vue';
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
    push(__('That did not save. Please try again.'), 'error');
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
  <div class="mx-auto max-w-3xl py-10">
    <header class="mb-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <StepIndicator :steps="steps" :index="index" @select="goTo" />

        <button type="button" class="text-sm text-muted underline hover:text-ink" @click="onSkip">
          {{ __('Skip setup') }}
        </button>
      </div>

      <div class="mt-4 h-1 w-full overflow-hidden rounded-full bg-shell-100">
        <div class="h-full rounded-full bg-primary transition-all duration-300" :style="{ width: `${progress}%` }" />
      </div>
    </header>

    <section class="rounded-2xl border border-shell-100 bg-panel p-8 shadow-soft">
      <h1 class="m-0 text-2xl font-bold text-ink">{{ current?.title }}</h1>
      <p class="mt-1 text-sm leading-relaxed text-muted">{{ current?.summary }}</p>

      <div class="mt-6">
        <!-- Welcome -->
        <div v-if="current?.id === 'welcome'" class="flex flex-col gap-4 text-sm leading-relaxed text-muted">
          <p class="m-0">
            {{ __('This plugin answers the question every shopper asks before buying: how much is it per month, and what do I save by paying it all at once.') }}
          </p>

          <p
            v-if="migrated"
            class="m-0 rounded-lg bg-success/10 px-4 py-3 text-success"
          >
            {{ __('We found settings from your previous installation and imported them. Nothing was changed — you can review everything on the settings screen.') }}
          </p>

          <p class="m-0">
            {{ __('The next few questions set the instalment rules. You can change any of them later.') }}
          </p>
        </div>

        <!-- The schema-driven steps -->
        <div v-else-if="stepFields.length" class="flex flex-col gap-6">
          <div>
            <FieldRenderer
              v-for="field in stepFields"
              :key="field.key"
              v-model="settings[field.key]"
              :field="field"
            />
          </div>

          <!-- Card brands only matter once the card row is on. -->
          <div v-if="current?.id === 'payment-methods' && settings.enable_credit_card_method_payment_form === 'yes'">
            <p class="m-0 mb-2 text-sm font-semibold text-ink">{{ __('Credit card brands') }}</p>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
              <label
                v-for="brand in creditBrands"
                :key="brand.key"
                class="flex items-center gap-2 rounded-lg border border-shell-100 px-3 py-2 text-sm text-ink"
              >
                <input
                  type="checkbox"
                  :checked="settings[brand.key] === 'yes'"
                  @change="settings[brand.key] = $event.target.checked ? 'yes' : 'no'"
                />
                {{ brand.label }}
              </label>
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
          <p
            v-if="migrated"
            class="m-0 rounded-lg bg-success/10 px-4 py-3 text-sm leading-relaxed text-success"
          >
            {{ __('We found the settings from your previous installation and imported them, exactly as they were. Nothing was changed, and your old options are still where they were in case you roll back.') }}
          </p>

          <p class="m-0 text-sm leading-relaxed text-muted">
            {{ migrated
              ? __('There is nothing else to set up. Review anything you like on the settings screen.')
              : __('Your store is showing instalment plans. From here you can fine-tune the wording, the placement and the appearance.') }}
          </p>

          <PricePreview :settings="settings" :environment="environment" />

          <div class="flex flex-wrap gap-3 text-sm">
            <a :href="links.shop_url" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary">
              {{ __('View your shop') }}
            </a>
            <a :href="links.docs_url" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary">
              {{ __('Read the documentation') }}
            </a>
          </div>
        </div>
      </div>

      <footer class="mt-8 flex items-center justify-between gap-3 border-t border-shell-100 pt-5">
        <BaseButton v-if="!isFirst" variant="ghost" :disabled="isSaving" @click="back">
          {{ __('Back') }}
        </BaseButton>
        <span v-else />

        <BaseButton v-if="!isLast" :disabled="isSaving" @click="onNext">
          {{ isSaving ? __('Saving…') : __('Continue') }}
        </BaseButton>

        <BaseButton v-else :disabled="isSaving || finished" @click="onFinish">
          {{ __('Go to the settings') }}
        </BaseButton>
      </footer>
    </section>

    <p class="mt-4 text-center text-xs text-muted">
      {{ sprintf(__('Step %1$d of %2$d'), index + 1, steps.length) }}
    </p>

    <ToastStack :toasts="toasts" @dismiss="dismiss" />
  </div>
</template>
