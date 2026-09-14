<script setup>
/**
 * CampaignSummary.vue - the campaign being edited, in plain words.
 *
 * Sits beside the form and follows it: what the campaign reaches (with the
 * number of published products, asked of the server as the target changes),
 * when, what it changes, and which active campaigns overlap it, where only one
 * of them can apply.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseBadge from '../../../components/base/BaseBadge.vue';
import BaseIcon from '../../../components/base/BaseIcon.vue';
import BaseNotice from '../../../components/base/BaseNotice.vue';
import { describeEffects, describePeriod, describeTarget } from '../../../composables/useCampaigns';
import { __, _n, sprintf } from '../../../utils/i18n';

const props = defineProps({
  rules: { type: Object, required: true },
  environment: { type: Object, default: () => ({}) },
  state: { type: String, default: '' },
  products: { type: Number, default: null },
  loading: { type: Boolean, default: false },
  conflicts: { type: Array, default: () => [] },
});

const STATES = {
  running: { label: __('Em andamento'), tone: 'success' },
  scheduled: { label: __('Agendada'), tone: 'info' },
  ended: { label: __('Encerrada'), tone: 'neutral' },
  inactive: { label: __('Inativa'), tone: 'neutral' },
};

const discount = computed(() =>
  props.rules.discount.enabled ? describeEffects({ ...props.rules, installments: { enabled: false } }, props.environment)[0] || '' : '',
);

const installments = computed(() =>
  props.rules.installments.enabled ? describeEffects({ ...props.rules, discount: { enabled: false } }, props.environment) : [],
);

const reach = computed(() => {
  if (props.loading || props.products === null) {
    return __('Contando os produtos…');
  }

  return sprintf(_n('%d produto publicado', '%d produtos publicados', props.products), props.products);
});
</script>

<template>
  <aside class="rounded-[8px] bg-white p-6 shadow-card ring-1 ring-slate-100">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold text-ink">{{ __('Resumo') }}</h2>
      <BaseBadge v-if="STATES[state]" :tone="STATES[state].tone">{{ STATES[state].label }}</BaseBadge>
    </div>

    <ul class="mt-5 space-y-4 text-[14px] leading-6 text-slate-600">
      <li class="flex gap-3">
        <BaseIcon name="box" :size="18" class="mt-0.5 text-slate-400" />
        <div>
          <p class="font-semibold text-slate-700">{{ describeTarget(rules.target) }}</p>
          <p class="text-[13px] text-slate-500">{{ reach }}</p>
        </div>
      </li>

      <li class="flex gap-3">
        <BaseIcon name="calendar" :size="18" class="mt-0.5 text-slate-400" />
        <p>{{ describePeriod(rules) }}</p>
      </li>

      <li class="flex gap-3">
        <BaseIcon name="discount" :size="18" class="mt-0.5 text-slate-400" />
        <p>
          {{
            discount
              ? sprintf(__('%s no lugar do desconto à vista da loja'), discount)
              : __('Mantém o desconto da loja')
          }}
        </p>
      </li>

      <li class="flex gap-3">
        <BaseIcon name="credit-card" :size="18" class="mt-0.5 text-slate-400" />
        <p>{{ installments.length ? installments.join(', ') : __('Mantém o parcelamento da loja') }}</p>
      </li>
    </ul>

    <BaseNotice v-if="conflicts.length" tone="warning" class="mt-6" :title="__('Sobreposição com outras campanhas')">
      <p>{{ __('Nos produtos em comum vale só uma: a de maior prioridade.') }}</p>
      <ul class="mt-2 list-disc space-y-1 pl-5">
        <li v-for="conflict in conflicts" :key="conflict.id">
          {{ sprintf(__('%s (prioridade %d)'), conflict.title, conflict.priority) }}
        </li>
      </ul>
    </BaseNotice>

    <p class="mt-6 border-t border-slate-100 pt-4 text-[13px] leading-5 text-slate-500">
      {{ __('Um produto com desconto próprio mantém o dele. O parcelamento exibido não muda o que a forma de pagamento cobra: ajuste também o seu gateway.') }}
    </p>
  </aside>
</template>
