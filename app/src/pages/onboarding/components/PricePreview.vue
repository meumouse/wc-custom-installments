<script setup>
/**
 * PricePreview.vue - what the choices will look like on a product.
 *
 * Built from the same numbers the server will use, on a made-up product, so
 * the reader can see the effect of a setting before committing to it.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseBadge from '../../../components/base/BaseBadge.vue';
import { __, sprintf } from '../../../utils/i18n';

const props = defineProps({
  settings: { type: Object, required: true },
  environment: { type: Object, default: () => ({}) },
  price: { type: Number, default: 97 },
});

/**
 * Format an amount the way the store does.
 *
 * @since 6.0.0
 * @param {number} amount - The amount.
 * @return {string} The formatted price.
 */
function money(amount) {
  const decimals = props.environment.decimal_separator ?? ',';
  const thousands = props.environment.thousand_separator ?? '.';
  const [whole, fraction] = Number(amount).toFixed(2).split('.');
  const number = `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, thousands)}${decimals}${fraction}`;

  // %s is the symbol and %v the number, so "R$ %v" and "%v €" keep their order.
  const format = props.environment.price_format || '%s%v';

  return format.replace('%s', props.environment.currency_symbol ?? 'R$').replace('%v', number);
}

const best = computed(() => {
  const free = Math.max(1, parseInt(props.settings.max_qtd_installments_without_fee, 10) || 1);
  const max = Math.max(1, parseInt(props.settings.max_qtd_installments, 10) || 1);
  const minimum = parseFloat(props.settings.min_value_installments) || 0;

  // The largest interest-free instalment that still clears the minimum.
  let count = Math.min(free, max);

  while (count > 1 && props.price / count < minimum) {
    count -= 1;
  }

  return { count, value: props.price / count };
});

const discounted = computed(() => {
  if (props.settings.enable_all_discount_options !== 'yes') {
    return null;
  }

  const amount = parseFloat(props.settings.discount_main_price) || 0;

  if (amount <= 0) {
    return null;
  }

  return props.settings.product_price_discount_method === 'fixed'
    ? Math.max(0, props.price - amount)
    : props.price - props.price * (amount / 100);
});
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-5">
    <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-shell-500">
      {{ __('Preview') }}
    </p>

    <div class="flex flex-col items-start gap-2">
      <span class="text-xl font-semibold text-ink">{{ money(price) }}</span>

      <span class="text-[14px] text-slate-700">
        {{ sprintf(__('Up to %dx of %s interest free'), best.count, money(best.value)) }}
      </span>

      <BaseBadge v-if="discounted !== null" tone="success" class="!text-[13px] !normal-case !tracking-normal">
        {{ sprintf(__('%s paying upfront'), money(discounted)) }}
      </BaseBadge>
    </div>
  </div>
</template>
