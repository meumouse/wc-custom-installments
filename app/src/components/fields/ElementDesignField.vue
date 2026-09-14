<script setup>
/**
 * ElementDesignField.vue - order and style each element of the price group.
 *
 * The stored shape is the 5.x one, { price: { order, icon: { class, image },
 * styles: { mobile: {...}, desktop: {...} } } }, so a migrated store opens the
 * panel on exactly what it had. The field's default is the element registry:
 * an element the store never touched still shows what it ships with, and each
 * colour can go back to it.
 *
 * Every change emits a complete structure rather than a patch. The server
 * sanitizes whole elements, and a partial one would lose the sides and units
 * nobody edited.
 *
 * @since 6.0.0
 */
import { computed, ref } from 'vue';
import BaseBadge from '../base/BaseBadge.vue';
import BaseButton from '../base/BaseButton.vue';
import BaseCode from '../base/BaseCode.vue';
import BaseColorInput from '../base/BaseColorInput.vue';
import BaseIconButton from '../base/BaseIconButton.vue';
import BaseInput from '../base/BaseInput.vue';
import BaseInputGroup from '../base/BaseInputGroup.vue';
import BaseLabel from '../base/BaseLabel.vue';
import BaseModal from '../base/BaseModal.vue';
import BaseSelect from '../base/BaseSelect.vue';
import BaseTabs from '../base/BaseTabs.vue';
import DesignBoxControl from './DesignBoxControl.vue';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  field: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
  iconFormat: { type: String, default: 'class' },
});

const emit = defineEmits(['update:modelValue']);

// What each element reads like on a product, for the preview. An element an
// add-on registers falls back to its title.
const PREVIEWS = {
  price: __('R$97,00'),
  installments: __('Em até 3x de R$32,33 sem juros'),
  discount_pix: __('À vista R$87,30 no Pix'),
  pix_economy: __('Economize R$9,70 no Pix'),
  discount_slip_bank: __('À vista R$87,30 no boleto bancário'),
};

const DEVICES = [
  { id: 'desktop', title: __('Computador'), icon: 'desktop' },
  { id: 'mobile', title: __('Celular'), icon: 'mobile' },
];

const editingKey = ref('');
const device = ref('desktop');

const defaults = computed(() =>
  props.field.default && typeof props.field.default === 'object' ? props.field.default : {},
);

const unitOptions = computed(() =>
  (props.field.units || ['px', 'em', 'rem', '%']).map((unit) => ({ value: unit, label: unit })),
);

const weightOptions = computed(() =>
  (props.field.weights || ['400', '500', '600', '700']).map((weight) => ({ value: String(weight), label: String(weight) })),
);

/**
 * Deep copy a plain value.
 *
 * @since 6.0.0
 * @param {*} value - Value to copy.
 * @return {*} The copy.
 */
function clone(value) {
  return JSON.parse(JSON.stringify(value ?? {}));
}

/**
 * Merge one plain object over another, recursing into nested objects.
 *
 * @since 6.0.0
 * @param {object} base - Values to start from.
 * @param {object} overrides - Values that win.
 * @return {object} The merged object.
 */
function merge(base, overrides) {
  const result = { ...base };

  Object.entries(overrides || {}).forEach(([key, value]) => {
    const isObject = value && typeof value === 'object' && !Array.isArray(value);

    result[key] = isObject && result[key] && typeof result[key] === 'object' ? merge(result[key], value) : value;
  });

  return result;
}

/**
 * One element, as shipped and then as the store changed it.
 *
 * @since 6.0.0
 * @param {string} key - Element key.
 * @return {object} The complete element.
 */
function resolve(key) {
  return merge(clone(defaults.value[key] || {}), clone(props.modelValue?.[key] || {}));
}

const elements = computed(() =>
  Object.keys(defaults.value)
    .map((key) => ({ key, ...resolve(key), title: defaults.value[key]?.settings_title || key }))
    .sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0)),
);

const editing = computed(() => (editingKey.value ? resolve(editingKey.value) : null));

const editingTitle = computed(() => defaults.value[editingKey.value]?.settings_title || editingKey.value);

/**
 * The whole structure, ready to store.
 *
 * @since 6.0.0
 * @return {object} Every element with its order, icon and styles.
 */
function snapshot() {
  const next = {};

  Object.keys(defaults.value).forEach((key) => {
    const element = resolve(key);

    next[key] = { order: element.order, icon: element.icon, styles: element.styles };
  });

  return next;
}

/**
 * Emit a new structure, unless the control is locked.
 *
 * @since 6.0.0
 * @param {object} next - The structure to store.
 * @return {void}
 */
function commit(next) {
  if (!props.disabled) {
    emit('update:modelValue', next);
  }
}

/**
 * Swap an element with its neighbour and renumber them all.
 *
 * @since 6.0.0
 * @param {number} index - Position of the element in the sorted list.
 * @param {number} step - -1 to move up, 1 to move down.
 * @return {void}
 */
function move(index, step) {
  const keys = elements.value.map((element) => element.key);
  const target = index + step;

  if (target < 0 || target >= keys.length) {
    return;
  }

  [keys[index], keys[target]] = [keys[target], keys[index]];

  const next = snapshot();

  keys.forEach((key, position) => {
    next[key].order = position + 1;
  });

  commit(next);
}

/**
 * Write one property of the element being edited.
 *
 * @since 6.0.0
 * @param {string} path - Dot path inside the element, e.g. `styles.mobile.font_size`.
 * @param {*} value - New value.
 * @return {void}
 */
function update(path, value) {
  const next = snapshot();
  const keys = path.split('.');
  const last = keys.pop();
  let node = next[editingKey.value];

  keys.forEach((key) => {
    node[key] = node[key] && typeof node[key] === 'object' ? node[key] : {};
    node = node[key];
  });

  node[last] = value;

  commit(next);
}

/**
 * Read a style of the element being edited, for the chosen device.
 *
 * @since 6.0.0
 * @param {string} property - Style property.
 * @return {*} The value.
 */
function style(property) {
  return editing.value?.styles?.[device.value]?.[property];
}

/**
 * Write a style of the element being edited, for the chosen device.
 *
 * @since 6.0.0
 * @param {string} property - Style property.
 * @param {*} value - New value.
 * @return {void}
 */
function setStyle(property, value) {
  update(`styles.${device.value}.${property}`, value);
}

/**
 * Put a colour back to what the element ships with.
 *
 * @since 6.0.0
 * @param {string} property - `font_color` or `background_color`.
 * @return {void}
 */
function resetColor(property) {
  setStyle(property, defaults.value[editingKey.value]?.styles?.[device.value]?.[property] ?? '');
}

/**
 * Format a four-sided box as CSS.
 *
 * @since 6.0.0
 * @param {object} box - Top, right, bottom, left and the unit.
 * @return {string} CSS shorthand.
 */
function boxCss(box) {
  const unit = box?.unit || 'rem';

  return ['top', 'right', 'bottom', 'left'].map((side) => `${box?.[side] ?? 0}${unit}`).join(' ');
}

/**
 * The inline style that previews an element on one device.
 *
 * @since 6.0.0
 * @param {object} element - A resolved element.
 * @param {string} deviceId - `desktop` or `mobile`.
 * @return {object} Style object.
 */
function previewStyle(element, deviceId) {
  const styles = element?.styles?.[deviceId] || {};

  return {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.35rem',
    fontSize: `${styles.font_size ?? 1}${styles.font_unit || 'rem'}`,
    fontWeight: styles.font_weight,
    color: styles.font_color,
    backgroundColor: styles.background_color,
    margin: boxCss(styles.margin),
    padding: boxCss(styles.padding),
    borderRadius: boxCss(styles.border_radius),
  };
}

const canBrowse = computed(() => typeof window !== 'undefined' && Boolean(window.wp?.media));

/**
 * Pick the icon image from the media library.
 *
 * @since 6.0.0
 * @return {void}
 */
function browseImage() {
  if (!canBrowse.value || props.disabled) {
    return;
  }

  const frame = window.wp.media({
    title: __('Escolher ícone'),
    button: { text: __('Usar este ícone') },
    library: { type: 'image' },
    multiple: false,
  });

  frame.on('select', () => {
    const attachment = frame.state().get('selection').first().toJSON();

    update('icon.image', attachment.url || '');
  });

  frame.open();
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div
      v-for="(element, index) in elements"
      :key="element.key"
      class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3"
    >
      <div class="flex flex-col">
        <BaseIconButton
          icon="arrow-up"
          :size="18"
          :label="sprintf(__('Mover %s para cima'), element.title)"
          :disabled="disabled || index === 0"
          @click="move(index, -1)"
        />
        <BaseIconButton
          icon="arrow-down"
          :size="18"
          :label="sprintf(__('Mover %s para baixo'), element.title)"
          :disabled="disabled || index === elements.length - 1"
          @click="move(index, 1)"
        />
      </div>

      <div class="min-w-0 flex-1 overflow-hidden">
        <span :style="previewStyle(element, 'desktop')">
          <img
            v-if="iconFormat !== 'class' && element.icon?.image"
            :src="element.icon.image"
            alt=""
            class="h-4 w-4 object-contain"
          />
          {{ PREVIEWS[element.key] || element.title }}
        </span>
      </div>

      <BaseBadge tone="info">{{ element.title }}</BaseBadge>

      <BaseButton variant="secondary" size="sm" icon="edit" :disabled="disabled" @click="editingKey = element.key">
        {{ __('Editar') }}
      </BaseButton>
    </div>

    <BaseModal
      :open="Boolean(editing)"
      size="xl"
      :title="sprintf(__('Configurar estilos: %s'), editingTitle)"
      @close="editingKey = ''"
    >
      <template v-if="editing">
        <BaseTabs
          v-model="device"
          variant="pills"
          :tabs="DEVICES"
          :aria-label="__('Dispositivo')"
          id-prefix="wcci-design-device"
        />

        <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,18rem)]">
          <div class="flex flex-col gap-6">
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <BaseLabel size="sm">{{ __('Tamanho do texto') }}</BaseLabel>
                <BaseInputGroup class="mt-2" :disabled="disabled">
                  <BaseInput
                    :model-value="style('font_size')"
                    type="number"
                    inputmode="decimal"
                    step="0.05"
                    min="0"
                    :disabled="disabled"
                    :aria-label="__('Tamanho do texto')"
                    bare
                    @update:model-value="setStyle('font_size', $event)"
                  />
                  <template #suffix>
                    <div class="w-24">
                      <BaseSelect
                        :model-value="style('font_unit') || 'rem'"
                        :options="unitOptions"
                        :disabled="disabled"
                        :aria-label="__('Unidade do tamanho do texto')"
                        bare
                        @update:model-value="setStyle('font_unit', $event)"
                      />
                    </div>
                  </template>
                </BaseInputGroup>
              </div>

              <div>
                <BaseLabel size="sm">{{ __('Espessura do texto') }}</BaseLabel>
                <BaseSelect
                  class="mt-2"
                  :model-value="String(style('font_weight') || '400')"
                  :options="weightOptions"
                  :disabled="disabled"
                  :aria-label="__('Espessura do texto')"
                  @update:model-value="setStyle('font_weight', $event)"
                />
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <BaseLabel size="sm">{{ __('Cor do texto') }}</BaseLabel>
                <div class="mt-2 flex items-center gap-2">
                  <BaseColorInput
                    :model-value="style('font_color') || ''"
                    :disabled="disabled"
                    :aria-label="__('Cor do texto')"
                    @update:model-value="setStyle('font_color', $event)"
                  />
                  <BaseIconButton
                    icon="undo"
                    :label="__('Redefinir para a cor padrão')"
                    :disabled="disabled"
                    @click="resetColor('font_color')"
                  />
                </div>
              </div>

              <div>
                <BaseLabel size="sm">{{ __('Cor de fundo') }}</BaseLabel>
                <div class="mt-2 flex items-center gap-2">
                  <BaseColorInput
                    :model-value="style('background_color') || ''"
                    :disabled="disabled"
                    :aria-label="__('Cor de fundo')"
                    @update:model-value="setStyle('background_color', $event)"
                  />
                  <BaseIconButton
                    icon="undo"
                    :label="__('Redefinir para a cor padrão')"
                    :disabled="disabled"
                    @click="resetColor('background_color')"
                  />
                </div>
              </div>
            </div>

            <DesignBoxControl
              :model-value="style('margin')"
              :label="__('Margem')"
              :units="field.units"
              :disabled="disabled"
              @update:model-value="setStyle('margin', $event)"
            />

            <DesignBoxControl
              :model-value="style('padding')"
              :label="__('Preenchimento')"
              :units="field.units"
              :disabled="disabled"
              @update:model-value="setStyle('padding', $event)"
            />

            <DesignBoxControl
              :model-value="style('border_radius')"
              :label="__('Raio da borda')"
              :units="field.units"
              :disabled="disabled"
              @update:model-value="setStyle('border_radius', $event)"
            />

            <div>
              <BaseLabel size="sm">{{ __('Ícone') }}</BaseLabel>

              <template v-if="iconFormat === 'class'">
                <p class="mt-1 text-[13px] text-slate-500">
                  {{ __('As classes do ícone no Font Awesome, por exemplo:') }}
                  <BaseCode>fa-solid fa-barcode</BaseCode>
                </p>
                <BaseInput
                  class="mt-2"
                  :model-value="editing.icon?.class || ''"
                  :disabled="disabled"
                  :aria-label="__('Classe do ícone')"
                  placeholder="fa-regular fa-credit-card"
                  @update:model-value="update('icon.class', $event)"
                />
                <BaseButton
                  class="mt-2"
                  variant="link"
                  href="https://fontawesome.com/search?m=free&o=r"
                  external
                >
                  {{ __('Acessar o Font Awesome') }}
                </BaseButton>
              </template>

              <template v-else>
                <p class="mt-1 text-[13px] text-slate-500">
                  {{ __('O endereço de uma imagem pequena, de preferência SVG ou PNG transparente.') }}
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                  <div class="min-w-0 flex-1">
                    <BaseInput
                      :model-value="editing.icon?.image || ''"
                      type="url"
                      :disabled="disabled"
                      :aria-label="__('Imagem do ícone')"
                      placeholder="https://"
                      @update:model-value="update('icon.image', $event)"
                    />
                  </div>
                  <BaseButton
                    v-if="canBrowse"
                    variant="secondary"
                    icon="image"
                    :disabled="disabled"
                    @click="browseImage"
                  >
                    {{ __('Procurar') }}
                  </BaseButton>
                </div>
              </template>
            </div>
          </div>

          <div>
            <BaseLabel size="sm">{{ __('Pré-visualizar') }}</BaseLabel>
            <div class="mt-2 flex min-h-[8rem] items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4">
              <span :style="previewStyle(editing, device)">
                <img
                  v-if="iconFormat !== 'class' && editing.icon?.image"
                  :src="editing.icon.image"
                  alt=""
                  class="h-4 w-4 object-contain"
                />
                {{ PREVIEWS[editingKey] || editingTitle }}
              </span>
            </div>
          </div>
        </div>
      </template>

      <template #actions>
        <BaseButton @click="editingKey = ''">{{ __('Concluir') }}</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
