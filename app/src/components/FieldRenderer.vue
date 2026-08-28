<script setup>
/**
 * FieldRenderer.vue — render one schema field.
 *
 * A locked field still renders its real control, disabled and marked PRO, so
 * the store owner can see what the feature looks like before buying it. The
 * lock is cosmetic here on purpose: the server is what refuses the write.
 *
 * @since 1.0.0
 */
import { computed } from 'vue';
import ToggleSwitch from './ToggleSwitch.vue';
import { __ } from '../utils/i18n';

const props = defineProps({
  modelValue: { type: [String, Number, Boolean, Object, Array], default: '' },
  field: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue', 'locked-click']);

const isLocked = computed(() => Boolean(props.field?.locked));

const model = computed({
  get: () => props.modelValue,
  set: (value) => {
    if (isLocked.value) {
      return;
    }

    emit('update:modelValue', value);
  },
});

/**
 * Open the upsell when the store owner reaches for a locked control.
 *
 * @since 1.0.0
 * @return {void}
 */
function onLockedClick() {
  if (isLocked.value) {
    emit('locked-click', props.field);
  }
}
</script>

<template>
  <div
    class="flex flex-col gap-3 border-b border-shell-100 py-4 last:border-b-0 sm:flex-row sm:items-start sm:justify-between sm:gap-8"
    @click="onLockedClick"
  >
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-2">
        <span class="text-sm font-semibold text-ink">{{ field.label }}</span>

        <span
          v-if="isLocked"
          class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-primary-800"
        >
          {{ __('Pro') }}
        </span>
      </div>

      <p v-if="field.description" class="mt-1 text-sm leading-relaxed text-muted">
        {{ field.description }}
      </p>
    </div>

    <div class="shrink-0 sm:w-72" :class="isLocked ? 'pointer-events-none opacity-60' : ''">
      <ToggleSwitch
        v-if="field.type === 'toggle'"
        v-model="model"
        :disabled="isLocked"
        :aria-label="field.label"
      />

      <select
        v-else-if="field.type === 'select'"
        v-model="model"
        :disabled="isLocked"
        class="w-full rounded-lg border border-shell-200 bg-white px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
      >
        <option v-for="option in field.options || []" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>

      <input
        v-else
        v-model="model"
        type="text"
        :disabled="isLocked"
        :placeholder="field.placeholder || ''"
        :inputmode="field.inputmode || undefined"
        class="w-full rounded-lg border border-shell-200 bg-white px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
      />
    </div>
  </div>
</template>
