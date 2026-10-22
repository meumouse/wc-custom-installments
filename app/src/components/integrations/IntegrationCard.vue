<script setup>
/**
 * IntegrationCard.vue - one integration in the grid.
 *
 * The shape is the one Joinotify uses on the same screen, so the two plugins
 * read as one family: the mark alone in a panel at the top, the name and what
 * the integration does centred under it, then whatever the card has to say
 * about its state, and the switch last with the way into its settings.
 *
 * Only the switch can be changed here. Everything the integration decides sits
 * behind the button, so a grid of cards stays a grid rather than a column of
 * forms, and a card with nothing else to decide simply has no button.
 *
 * The mark comes resolved from the schema, because only WordPress can say
 * where the plugin directory is on this install. A card with none falls back to
 * its initial, which is what an add-on's card gets until it ships one.
 *
 * A card whose plugin is not installed still switches and still configures:
 * the settings are the store's, not the other plugin's, and a store that
 * deactivates a plugin for an afternoon should find its choices intact.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseButton from '../base/BaseButton.vue';
import BaseSwitch from '../base/BaseSwitch.vue';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  card: { type: Object, required: true },
  modelValue: { type: [String, Boolean], default: 'no' },
  configurableCount: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue', 'configure']);

const mark = computed(() => String(props.card.mark || ''));
const initial = computed(() => String(props.card.title || '?').charAt(0).toUpperCase());
const buttonLabel = computed(() => props.card.modal?.button_label || __('Configurar'));
const hasSettings = computed(() => props.configurableCount > 0);
const enabled = computed(() => props.modelValue === 'yes' || props.modelValue === true);
const showsButton = computed(() => hasSettings.value && enabled.value);

const switchValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <div class="flex h-full flex-col rounded-[8px] border border-slate-200 bg-white">
    <div class="flex min-h-[148px] items-center justify-center border-b border-slate-200 px-6 py-8">
      <!-- Height-bound rather than square: the marks come from the other
           plugins, and a wide wordmark squeezed into a square box shrinks to a
           strip while a square icon fills it. -->
      <img
        v-if="mark"
        :src="mark"
        alt=""
        class="block max-h-20 w-auto max-w-full object-contain"
        draggable="false"
      />
      <div
        v-else
        class="flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 text-4xl font-semibold text-slate-400"
        aria-hidden="true"
      >
        {{ initial }}
      </div>
    </div>

    <div class="flex flex-1 flex-col px-6 py-6 text-center">
      <h3 class="text-[20px] font-semibold leading-7 text-ink">{{ card.title }}</h3>

      <p v-if="card.description" class="mt-4 text-[14px] leading-6 text-slate-500">
        {{ card.description }}
      </p>

      <p v-if="card.url" class="mt-3">
        <BaseButton :href="card.url" variant="link" external icon="link">
          {{ __('Ver o plugin') }}
        </BaseButton>
      </p>

      <div class="mt-5 space-y-3 text-left">
        <p
          v-if="!card.active && card.not_found_notice"
          class="rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] leading-5 text-amber-800"
        >
          {{ card.not_found_notice }}
        </p>

        <p
          v-if="hasSettings && !enabled"
          class="rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] leading-5 text-slate-600"
        >
          {{ __('Ligue a integração para acessar as configurações.') }}
        </p>
      </div>

      <div class="mt-6 flex flex-1 flex-col items-center justify-end gap-4">
        <BaseSwitch
          v-model="switchValue"
          :id="`wcci-integration-${card.slug}`"
          :aria-label="sprintf(__('Integração com %s'), card.title || '')"
        />

        <BaseButton
          v-if="showsButton"
          variant="outline"
          @click="$emit('configure', card.slug)"
        >
          {{ buttonLabel }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>
