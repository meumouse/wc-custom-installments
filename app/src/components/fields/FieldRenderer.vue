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
import FieldRow from './FieldRow.vue';
import BaseInput from '../base/BaseInput.vue';
import BaseNotice from '../base/BaseNotice.vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: [String, Number, Boolean, Object, Array], default: '' },
  unitValue: { type: String, default: '' },
  field: { type: Object, required: true },
  showLockBadge: { type: Boolean, default: true },
  context: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'update:unitValue', 'locked-click']);

// Controls made of several parts: the label names the group, not one input.
const COMPOSITE = [
  'unit',
  'placeholder-text',
  'gateway-rates',
  'installment-fees',
  'element-design',
  'card-brands',
  'checkbox-group',
];

const isLocked = computed(() => Boolean(props.field?.locked));
const isWide = computed(() =>
  ['gateway-rates', 'installment-fees', 'element-design', 'card-brands', 'checkbox-group', 'textarea'].includes(
    props.field?.type,
  ),
);

const controlId = computed(() =>
  COMPOSITE.includes(props.field?.type) ? '' : `wcci-field-${String(props.field?.key || '').replace(/[^\w-]/g, '-')}`,
);

const resolved = computed(() => resolveFieldComponent(props.field));
const component = computed(() => resolved.value || BaseInput);

// A structured setting whose control nobody registered - the Pro design panel
// in the free build - has no text form to fall back to. Say where it comes
// from rather than printing the object into an input.
const isUnavailable = computed(
  () => !resolved.value && props.modelValue !== null && typeof props.modelValue === 'object',
);

const controlProps = computed(() => {
  const field = props.field;
  const shared = { disabled: isLocked.value, ariaLabel: field.label };

  if (controlId.value) {
    shared.id = controlId.value;
  }

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
    case 'card-brands':
    case 'checkbox-group':
      return { ...shared, field };

    case 'installment-fees':
      return {
        ...shared,
        field,
        maxInstallments: props.context.maxInstallments ?? 12,
        firstInstallment: props.context.firstInstallment ?? 1,
        environment: props.context.environment || {},
      };

    case 'element-design':
      return {
        ...shared,
        field,
        iconFormat: props.context.iconFormat || 'class',
        startingFrom: props.context.startingFrom || '',
        saleBadge: Boolean(props.context.saleBadge),
      };

    case 'toggle':
    case 'color':
      return shared;

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
  <FieldRow
    :label="field.label"
    :description="field.description"
    :code="field.code || ''"
    :control-id="controlId"
    :wide="isWide"
    :locked="isLocked && showLockBadge"
    :class="isLocked ? 'cursor-pointer' : ''"
    @click="onLockedClick"
  >
    <div :class="isLocked ? 'pointer-events-none opacity-60' : ''">
      <BaseNotice v-if="isUnavailable" tone="info" icon="crown">
        {{ __('Este controle faz parte do complemento Pro.') }}
      </BaseNotice>

      <component :is="component" v-else v-model="model" v-bind="{ ...controlProps, ...unitBinding }" />
    </div>
  </FieldRow>
</template>
