<script setup>
/**
 * FieldGroup.vue - one card of the schema, as a group inside the panel.
 *
 * A group whose every field is locked is announced once, at its heading,
 * rather than badging each field: a Pro-only group should read as an
 * invitation, not as a wall of padlocks.
 *
 * @since 6.0.0
 */
import BaseBadge from '../base/BaseBadge.vue';
import { __ } from '../../utils/i18n';

defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  locked: { type: Boolean, default: false },
});
</script>

<template>
  <div class="border-t border-slate-100 pt-10 first:border-t-0 first:pt-0">
    <div v-if="title" class="flex flex-wrap items-center gap-3">
      <h2 class="text-lg font-semibold text-ink">{{ title }}</h2>
      <BaseBadge v-if="locked" tone="pro" icon="lock">{{ __('Pro') }}</BaseBadge>
    </div>

    <p v-if="description" class="mt-1 max-w-3xl text-[14px] leading-6 text-slate-500">{{ description }}</p>

    <div class="mt-2 divide-y divide-slate-100">
      <slot />
    </div>
  </div>
</template>
