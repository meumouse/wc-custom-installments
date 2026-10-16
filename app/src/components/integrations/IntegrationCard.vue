<script setup>
/**
 * IntegrationCard.vue - one integration in the grid.
 *
 * The card carries the integration's master switch and nothing else that can
 * be changed: everything it decides sits behind the button, in a dialog, so a
 * grid of five cards stays a grid rather than five stacked forms.
 *
 * A card whose plugin is not installed still switches and still configures.
 * The settings are the store's, not the other plugin's, and a store that
 * deactivates a plugin for an afternoon should find its choices intact.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseBadge from '../base/BaseBadge.vue';
import BaseButton from '../base/BaseButton.vue';
import BaseSwitch from '../base/BaseSwitch.vue';
import { markFor } from './integrationMarks';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  card: { type: Object, required: true },
  modelValue: { type: [String, Boolean], default: 'no' },
  configurableCount: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue', 'configure']);

const mark = computed(() => markFor(props.card.slug));
const initial = computed(() => String(props.card.title || '?').charAt(0).toUpperCase());
const badge = computed(() => props.card.badge || null);
const buttonLabel = computed(() => props.card.modal?.button_label || __('Configurar'));
const showsButton = computed(() => props.configurableCount > 0);
const enabled = computed(() => props.modelValue === 'yes' || props.modelValue === true);

const switchValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <div class="flex h-full flex-col rounded-[8px] border border-slate-200 bg-white">
    <div class="flex min-h-[132px] items-center justify-center border-b border-slate-100 px-6 py-7">
      <img
        v-if="mark"
        :src="mark"
        alt=""
        width="64"
        height="64"
        class="block h-16 w-16"
        draggable="false"
      />
      <div
        v-else
        class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-3xl font-semibold text-slate-400"
        aria-hidden="true"
      >
        {{ initial }}
      </div>
    </div>

    <div class="flex flex-1 flex-col px-6 py-6">
      <div class="flex flex-wrap items-center gap-2">
        <h3 class="text-[17px] font-semibold leading-6 text-ink">{{ card.title }}</h3>
        <BaseBadge v-if="badge" :tone="badge.tone || 'neutral'" :icon="badge.icon || ''">
          {{ badge.text }}
        </BaseBadge>
      </div>

      <p v-if="card.description" class="mt-3 text-[13px] leading-6 text-slate-500">
        {{ card.description }}
      </p>

      <p
        v-if="!card.active && card.not_found_notice"
        class="mt-4 rounded-[8px] bg-slate-50 px-4 py-3 text-[13px] leading-5 text-slate-500"
      >
        {{ card.not_found_notice }}
      </p>

      <div class="mt-6 flex flex-1 items-end justify-between gap-4">
        <BaseSwitch
          v-model="switchValue"
          :id="`wcci-integration-${card.slug}`"
          :aria-label="sprintf(__('Integração com %s'), card.title || '')"
        />

        <div class="flex items-center gap-3">
          <BaseButton v-if="card.url" :href="card.url" variant="link" external icon="link">
            {{ __('Ver') }}
          </BaseButton>

          <BaseButton
            v-if="showsButton"
            variant="secondary"
            size="sm"
            icon="cog"
            :disabled="!enabled"
            @click="$emit('configure', card.slug)"
          >
            {{ buttonLabel }}
          </BaseButton>
        </div>
      </div>
    </div>
  </div>
</template>
