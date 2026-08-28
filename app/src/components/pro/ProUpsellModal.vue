<script setup>
/**
 * ProUpsellModal.vue - explain one locked feature and point at the trial.
 *
 * Both actions are plain links opened in a new tab. Nothing leaves the site
 * from this dialog: the trial form lives in the setup wizard, behind an
 * explicit consent step.
 *
 * @since 1.0.0
 */
import { computed } from 'vue';
import BaseButton from '../base/BaseButton.vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
  field: { type: Object, default: null },
  storeUrl: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const isOpen = computed(() => Boolean(props.field));
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-[100000] flex items-center justify-center bg-ink/40 p-4"
    role="dialog"
    aria-modal="true"
    @click.self="emit('close')"
  >
    <div class="w-full max-w-md rounded-2xl bg-panel p-6 shadow-soft">
      <span class="rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-primary-800">
        {{ __('Pro') }}
      </span>

      <h2 class="mt-3 text-lg font-semibold text-ink">{{ field?.label }}</h2>

      <p v-if="field?.description" class="mt-2 text-sm leading-relaxed text-muted">
        {{ field.description }}
      </p>

      <p class="mt-4 text-sm leading-relaxed text-muted">
        {{ __('This is part of the Pro add-on. Try it free for 7 days, with no card.') }}
      </p>

      <div class="mt-6 flex flex-wrap gap-3">
        <a
          :href="storeUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white no-underline hover:bg-primary-800"
        >
          {{ __('Start the free trial') }}
        </a>

        <BaseButton variant="secondary" @click="emit('close')">
          {{ __('Maybe later') }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>
