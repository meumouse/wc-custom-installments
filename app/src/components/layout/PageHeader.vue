<script setup>
/**
 * PageHeader.vue - the top of every internal page.
 *
 * The plugin name as an eyebrow, the logo beside the page title, a description,
 * and room on the right for the page's own actions. It is the same header
 * Joinotify uses, so the MeuMouse plugins read as one family.
 *
 * @since 6.0.0
 */
import BrandMark from '../brand/BrandMark.vue';
import { __ } from '../../utils/i18n';

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
});
</script>

<template>
  <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div class="min-w-0 space-y-2">
      <p class="text-xs font-semibold uppercase tracking-[0.22em] text-shell-500">
        {{ __('Custom Installments') }}
      </p>

      <div class="flex items-center gap-3">
        <BrandMark size="md" />
        <h1 class="text-3xl font-semibold tracking-tight text-ink">{{ title }}</h1>
      </div>

      <p v-if="description || $slots.description" class="max-w-3xl text-sm leading-6 text-shell-500">
        <slot name="description">{{ description }}</slot>
      </p>
    </div>

    <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-3">
      <slot name="actions" />
    </div>
  </header>
</template>
