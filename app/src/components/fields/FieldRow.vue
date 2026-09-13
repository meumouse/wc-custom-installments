<script setup>
/**
 * FieldRow.vue - one setting: its label and description, then its control.
 *
 * Label on the left and control on the right from the `lg` breakpoint, in the
 * fixed 420/460 columns Joinotify uses, so controls line up down the page.
 * Below that, and always for a wide control, they stack.
 *
 * @since 6.0.0
 */
import BaseBadge from '../base/BaseBadge.vue';
import BaseLabel from '../base/BaseLabel.vue';
import { __ } from '../../utils/i18n';

defineProps({
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  controlId: { type: String, default: '' },
  wide: { type: Boolean, default: false },
  locked: { type: Boolean, default: false },
});
</script>

<template>
  <div
    class="grid items-start gap-4 py-6"
    :class="wide ? '' : 'lg:grid-cols-[minmax(0,420px)_minmax(0,460px)] lg:items-center lg:gap-6'"
  >
    <div class="min-w-0">
      <div class="flex flex-wrap items-center gap-2">
        <BaseLabel :for="controlId">{{ label }}</BaseLabel>
        <BaseBadge v-if="locked" tone="pro" icon="lock">{{ __('Pro') }}</BaseBadge>
      </div>

      <p v-if="description" class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
        {{ description }}
      </p>
    </div>

    <div class="min-w-0">
      <slot />
    </div>
  </div>
</template>
