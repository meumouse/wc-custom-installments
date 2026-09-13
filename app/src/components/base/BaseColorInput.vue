<script setup>
/**
 * BaseColorInput.vue - a colour swatch paired with its hex value.
 *
 * The text field is the one that matters: a store owner pasting a brand hex
 * from a style guide should not have to find it on a colour wheel. The swatch
 * opens the browser's own picker, which stays out of sight.
 *
 * @since 6.0.0
 */
import { computed, ref } from 'vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: String, default: '#000000' },
  id: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const picker = ref(null);

const isHex = computed(() => /^#([A-Fa-f0-9]{3}){1,2}$/.test(props.modelValue || ''));

const model = computed({
  get: () => props.modelValue || '',
  set: (value) => emit('update:modelValue', value),
});

const swatch = computed({
  get: () => (isHex.value ? props.modelValue : '#000000'),
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <div class="flex items-center gap-3">
    <button
      type="button"
      :disabled="disabled"
      :aria-label="ariaLabel ? `${ariaLabel} — ${__('Pick a colour')}` : __('Pick a colour')"
      class="relative h-11 w-11 shrink-0 overflow-hidden rounded-xl border border-slate-200 shadow-sm transition focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100 disabled:cursor-not-allowed disabled:opacity-60"
      :style="{ backgroundColor: isHex ? modelValue : 'transparent' }"
      @click="picker?.click()"
    />

    <input
      ref="picker"
      v-model="swatch"
      type="color"
      tabindex="-1"
      aria-hidden="true"
      class="sr-only"
    />

    <input
      :id="id || undefined"
      v-model="model"
      type="text"
      spellcheck="false"
      :disabled="disabled"
      :aria-label="ariaLabel || undefined"
      class="w-40 rounded-xl border border-slate-200 bg-white px-4 py-3 font-mono text-[14px] uppercase leading-5 text-slate-700 transition-colors focus:border-primary-700 focus:ring-4 focus:ring-primary-100 disabled:cursor-not-allowed disabled:bg-slate-50"
    />
  </div>
</template>
