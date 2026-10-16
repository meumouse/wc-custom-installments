<script setup>
/**
 * FieldGroup.vue - one card of the schema, as a group inside the panel.
 *
 * A group whose every field is locked is announced once, at its heading,
 * rather than badging each field: a Pro-only group should read as an
 * invitation, not as a wall of padlocks.
 *
 * A group may also carry a badge of its own, which is how an integration card
 * says whether the other plugin was found, and a link to that plugin.
 *
 * @since 6.0.0
 */
import BaseBadge from '../base/BaseBadge.vue';
import BaseButton from '../base/BaseButton.vue';
import { __ } from '../../utils/i18n';

defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  locked: { type: Boolean, default: false },
  badge: { type: Object, default: null },
  url: { type: String, default: '' },
});
</script>

<template>
  <div class="border-t border-slate-100 pt-10 first:border-t-0 first:pt-0">
    <div v-if="title" class="flex flex-wrap items-center gap-3">
      <h2 class="text-lg font-semibold text-ink">{{ title }}</h2>
      <BaseBadge v-if="locked" tone="pro" icon="lock">{{ __('Pro') }}</BaseBadge>
      <BaseBadge v-if="badge && badge.text" :tone="badge.tone || 'neutral'" :icon="badge.icon || ''">
        {{ badge.text }}
      </BaseBadge>
    </div>

    <p v-if="description" class="mt-1 max-w-3xl text-[14px] leading-6 text-slate-500">{{ description }}</p>

    <p v-if="url" class="mt-1">
      <BaseButton :href="url" variant="link" external icon="link">
        {{ __('Ver o plugin') }}
      </BaseButton>
    </p>

    <div class="mt-2 divide-y divide-slate-100">
      <slot />
    </div>
  </div>
</template>
