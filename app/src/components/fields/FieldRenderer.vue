<script setup>
/**
 * FieldRenderer.vue - render one schema field.
 *
 * A locked field still renders its real control, disabled and marked PRO, so
 * the store owner can see what the feature looks like before buying it. The
 * lock here is presentation: the server is what refuses the write.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import { resolveFieldComponent } from './fieldRegistry';
import BaseInput from '../base/BaseInput.vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: [String, Number, Boolean, Object, Array], default: '' },
  unitValue: { type: String, default: '' },
  field: { type: Object, required: true },
  showLockBadge: { type: Boolean, default: true },
  context: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'update:unitValue', 'locked-click']);

const isLocked = computed(() => Boolean(props.field?.locked));
const isWide = computed(() =>
  ['gateway-rates', 'installment-fees', 'element-design', 'textarea'].includes(props.field?.type),
);

const component = computed(() => resolveFieldComponent(props.field) || BaseInput);

const controlProps = computed(() => {
  const field = props.field;
  const shared = { disabled: isLocked.value, ariaLabel: field.label };

  switch (field.type) {
    case 'select':
      return { ...shared, options: field.options || [] };

    case 'number':
      return {
        ...shared,
        type: 'number',
        inputmode: 'decimal',
        placeholder: field.placeholder || '',
        min: field.min ?? null,
        max: field.max ?? null,
        step: field.step ?? null,
      };

    case 'textarea':
      return { ...shared, rows: 4, monospace: true };

    case 'unit':
    case 'placeholder-text':
    case 'gateway-rates':
      return { ...shared, field };

    case 'installment-fees':
      return { ...shared, field, maxInstallments: props.context.maxInstallments ?? 12 };

    default:
      return { ...shared, placeholder: field.placeholder || '', inputmode: field.inputmode || '' };
  }
});

const model = computed({
  get: () => props.modelValue,
  set: (value) => {
    if (isLocked.value) {
      return;
    }

    emit('update:modelValue', value);
  },
});

// A measurement is two settings behind one control. Only that control receives
// the second binding: handing it to the others would land on the DOM as a stray
// attribute, since a plain input has no such prop to absorb it.
const unitBinding = computed(() => {
  if (props.field?.type !== 'unit') {
    return {};
  }

  return {
    unitValue: props.unitValue,
    'onUpdate:unitValue': (value) => {
      if (!isLocked.value) {
        emit('update:unitValue', value);
      }
    },
  };
});

/**
 * Open the upsell when the store owner reaches for a locked control.
 *
 * @since 6.0.0
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
    class="border-b border-shell-100 py-4 last:border-b-0"
    :class="isWide ? 'flex flex-col gap-3' : 'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-8'"
    @click="onLockedClick"
  >
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-2">
        <span class="text-sm font-semibold text-ink">{{ field.label }}</span>

        <span
          v-if="isLocked && showLockBadge"
          class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-primary-800"
        >
          {{ __('Pro') }}
        </span>
      </div>

      <p v-if="field.description" class="mt-1 text-sm leading-relaxed text-muted">
        {{ field.description }}
      </p>
    </div>

    <div
      :class="[isWide ? 'w-full' : 'shrink-0 sm:w-72', isLocked ? 'pointer-events-none opacity-60' : '']"
    >
      <component :is="component" v-model="model" v-bind="{ ...controlProps, ...unitBinding }" />
    </div>
  </div>
</template>
