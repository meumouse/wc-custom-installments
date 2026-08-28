<script setup>
/**
 * OnboardingPage.vue — the setup wizard shell.
 *
 * The seven-step flow arrives in its own phase; this mounts the screen and
 * proves its bootstrap endpoint, so the wizard can be built on top without
 * touching the plumbing.
 *
 * @since 1.0.0
 */
import { __ } from '../../utils/i18n';

defineProps({
  bootstrap: { type: Object, required: true },
});
</script>

<template>
  <div class="mx-auto max-w-2xl py-10">
    <div class="rounded-2xl border border-shell-100 bg-panel p-8 shadow-soft">
      <h1 class="m-0 text-2xl font-bold text-ink">{{ __('Welcome to Custom Installments') }}</h1>

      <p class="mt-2 text-sm leading-relaxed text-muted">
        {{ __('A few questions and your store will be showing installment plans. It takes about a minute.') }}
      </p>

      <a
        :href="bootstrap.links?.settings_url || '#'"
        class="mt-6 inline-block rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white no-underline hover:bg-primary-800"
      >
        {{ __('Go to the settings') }}
      </a>
    </div>
  </div>
</template>
