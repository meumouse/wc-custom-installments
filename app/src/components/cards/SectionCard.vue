<script setup>
/**
 * SectionCard.vue - one card of settings, with its heading and lock state.
 *
 * A card whose every field is locked is announced once, at the top, rather
 * than badging each field: a Pro-only card should read as an invitation, not
 * as a wall of padlocks.
 *
 * @since 1.0.0
 */
import { __ } from '../../utils/i18n';

defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  locked: { type: Boolean, default: false },
});
</script>

<template>
  <article class="rounded-2xl border border-shell-100 bg-panel p-6 shadow-soft">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="m-0 text-base font-semibold text-ink">{{ title }}</h2>
        <p v-if="description" class="mt-1 text-sm leading-relaxed text-muted">{{ description }}</p>
      </div>

      <span
        v-if="locked"
        class="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-primary-800"
      >
        {{ __('Pro') }}
      </span>
    </header>

    <div class="mt-4">
      <slot />
    </div>
  </article>
</template>
