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
 * An element with a `parent` is a part of that one - the discounted price
 * inside the product price. It is listed under its parent rather than ordered
 * on its own, and every property left empty inherits from the parent.
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
import BaseIcon from '../base/BaseIcon.vue';
import BaseIconButton from '../base/BaseIconButton.vue';
import BaseInput from '../base/BaseInput.vue';
import BaseInputGroup from '../base/BaseInputGroup.vue';
import BaseLabel from '../base/BaseLabel.vue';
import BaseModal from '../base/BaseModal.vue';
import BaseSelect from '../base/BaseSelect.vue';
import BaseTabs from '../base/BaseTabs.vue';
import DesignBoxControl from './DesignBoxControl.vue';
import ElementPreview from './ElementPreview.vue';
import { unitOptions as buildUnitOptions } from '../../utils/units';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  field: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
  iconFormat: { type: String, default: 'class' },
  // The copy a variable product's price starts with, e.g. "A partir de".
  startingFrom: { type: String, default: '' },
  // Whether the store prints the percentage-off badge beside a sale price.
  saleBadge: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

// What each element reads like on a product, for the preview. The product
// price draws itself; an element an add-on registers falls back to its title.
const PREVIEWS = {
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

const unitOptions = computed(() => buildUnitOptions(props.field.units));

const weights = computed(() => (props.field.weights || ['400', '500', '600', '700']).map((weight) => String(weight)));

const canBrowse = computed(() => typeof window !== 'undefined' && Boolean(window.wp?.media));

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

/**
 * The element a part belongs to.
 *
 * @since 6.0.0
 * @param {string} key - Element key.
 * @return {string} The parent's key, or an empty string for a top-level element.
 */
function parentOf(key) {
  return String(defaults.value[key]?.parent || '');
}

/**
 * The name an element is listed under.
 *
 * @since 6.0.0
 * @param {string} key - Element key.
 * @return {string} Its title.
 */
function titleOf(key) {
  return defaults.value[key]?.settings_title || key;
}

/**
 * The first part of an element, resolved, for its preview.
 *
 * @since 6.0.0
 * @param {string} key - Key of the parent element.
 * @return {object|null} The part, or null when it has none.
 */
function partOf(key) {
  const part = Object.keys(defaults.value).find((candidate) => parentOf(candidate) === key);

  return part ? { key: part, ...resolve(part) } : null;
}

// The elements the store orders, in their order.
const topLevel = computed(() =>
  Object.keys(defaults.value)
    .filter((key) => !parentOf(key))
    .map((key) => ({ key, ...resolve(key), title: titleOf(key) }))
    .sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0)),
);

// Every row of the list: each element followed by its parts.
const rows = computed(() =>
  topLevel.value.flatMap((element, index) => [
    { ...element, index, isPart: false },
    ...Object.keys(defaults.value)
      .filter((key) => parentOf(key) === element.key)
      .map((key) => ({ key, ...resolve(key), title: titleOf(key), index, isPart: true })),
  ]),
);

const editing = computed(() => (editingKey.value ? { key: editingKey.value, ...resolve(editingKey.value) } : null));

const editingTitle = computed(() => titleOf(editingKey.value));

const editingParentKey = computed(() => parentOf(editingKey.value));

const isEditingPart = computed(() => Boolean(editingParentKey.value));

const editingParent = computed(() =>
  editingParentKey.value ? { key: editingParentKey.value, ...resolve(editingParentKey.value) } : null,
);

const weightOptions = computed(() => {
  const options = weights.value.map((weight) => ({ value: weight, label: weight }));

  if (!isEditingPart.value) {
    return options;
  }

  return [{ value: '', label: sprintf(__('Herdar (%s)'), String(inherited('font_weight') || '400')) }, ...options];
});

const resetLabel = computed(() =>
  isEditingPart.value ? sprintf(__('Herdar de %s'), titleOf(editingParentKey.value)) : __('Redefinir para a cor padrão'),
);

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
 * @param {number} index - Position of the element among the ordered ones.
 * @param {number} step - -1 to move up, 1 to move down.
 * @return {void}
 */
function move(index, step) {
  const keys = topLevel.value.map((element) => element.key);
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
 * Read what the element being edited inherits, for the chosen device.
 *
 * @since 6.0.0
 * @param {string} property - Style property.
 * @return {*} The parent's value, or undefined for a top-level element.
 */
function inherited(property) {
  return editingParent.value?.styles?.[device.value]?.[property];
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
 * Put a colour back to what the element ships with, which for a part is
 * inheriting it again.
 *
 * @since 6.0.0
 * @param {string} property - `font_color` or `background_color`.
 * @return {void}
 */
function resetColor(property) {
  setStyle(property, defaults.value[editingKey.value]?.styles?.[device.value]?.[property] ?? '');
}

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
      v-for="row in rows"
      :key="row.key"
      class="flex flex-wrap items-center gap-3 rounded-lg border px-3 py-3"
      :class="row.isPart ? 'ml-6 border-dashed border-slate-200 bg-slate-50/60 sm:ml-10' : 'border-slate-200 bg-white'"
    >
      <div v-if="!row.isPart" class="flex flex-col">
        <BaseIconButton
          icon="arrow-up"
          :size="18"
          :label="sprintf(__('Mover %s para cima'), row.title)"
          :disabled="disabled || row.index === 0"
          @click="move(row.index, -1)"
        />
        <BaseIconButton
          icon="arrow-down"
          :size="18"
          :label="sprintf(__('Mover %s para baixo'), row.title)"
          :disabled="disabled || row.index === topLevel.length - 1"
          @click="move(row.index, 1)"
        />
      </div>

      <div class="min-w-0 flex-1 overflow-hidden">
        <ElementPreview
          v-if="row.isPart"
          :element="{ key: parentOf(row.key), ...resolve(parentOf(row.key)) }"
          :sale="row"
          :icon-format="iconFormat"
          :starting-from="startingFrom"
          :sale-badge="saleBadge"
          focus-sale
        />
        <ElementPreview
          v-else
          :element="row"
          :sale="partOf(row.key)"
          :icon-format="iconFormat"
          :text="PREVIEWS[row.key] || row.title"
          :starting-from="startingFrom"
          :sale-badge="saleBadge"
        />
      </div>

      <BaseBadge tone="info">{{ row.title }}</BaseBadge>

      <BaseButton variant="secondary" size="sm" icon="edit" :disabled="disabled" @click="editingKey = row.key">
        {{ __('Editar') }}
      </BaseButton>
    </div>

    <BaseModal
      :open="Boolean(editing)"
      size="2xl"
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

        <p v-if="isEditingPart" class="mt-4 text-[13px] leading-5 text-slate-500">
          {{
            sprintf(
              __('O preço exibido ao lado do preço riscado quando o produto está em promoção. O que ficar em branco segue o elemento "%s".'),
              titleOf(editingParentKey),
            )
          }}
        </p>

        <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
          <div class="flex min-w-0 flex-col gap-6">
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="min-w-0">
                <BaseLabel size="sm">{{ __('Tamanho do texto') }}</BaseLabel>
                <BaseInputGroup class="mt-2" :disabled="disabled">
                  <BaseInput
                    :model-value="style('font_size')"
                    type="number"
                    inputmode="decimal"
                    step="0.05"
                    min="0"
                    :placeholder="isEditingPart ? String(inherited('font_size') ?? '') : ''"
                    :disabled="disabled"
                    :aria-label="__('Tamanho do texto')"
                    bare
                    @update:model-value="setStyle('font_size', $event)"
                  />
                  <template #suffix>
                    <div class="w-28">
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

              <div class="min-w-0">
                <BaseLabel size="sm">{{ __('Espessura do texto') }}</BaseLabel>
                <BaseSelect
                  class="mt-2"
                  :model-value="isEditingPart ? String(style('font_weight') ?? '') : String(style('font_weight') || '400')"
                  :options="weightOptions"
                  :disabled="disabled"
                  :aria-label="__('Espessura do texto')"
                  @update:model-value="setStyle('font_weight', $event)"
                />
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="min-w-0">
                <BaseLabel size="sm">{{ __('Cor do texto') }}</BaseLabel>
                <BaseColorInput
                  class="mt-2"
                  fluid
                  :model-value="style('font_color') || ''"
                  :placeholder="isEditingPart ? String(inherited('font_color') || '') : ''"
                  :disabled="disabled"
                  :aria-label="__('Cor do texto')"
                  @update:model-value="setStyle('font_color', $event)"
                >
                  <template #append>
                    <BaseIconButton
                      icon="undo"
                      :label="resetLabel"
                      :disabled="disabled"
                      @click="resetColor('font_color')"
                    />
                  </template>
                </BaseColorInput>
              </div>

              <div class="min-w-0">
                <BaseLabel size="sm">{{ __('Cor de fundo') }}</BaseLabel>
                <BaseColorInput
                  class="mt-2"
                  fluid
                  :model-value="style('background_color') || ''"
                  :placeholder="isEditingPart ? 'transparent' : ''"
                  :disabled="disabled"
                  :aria-label="__('Cor de fundo')"
                  @update:model-value="setStyle('background_color', $event)"
                >
                  <template #append>
                    <BaseIconButton
                      icon="undo"
                      :label="resetLabel"
                      :disabled="disabled"
                      @click="resetColor('background_color')"
                    />
                  </template>
                </BaseColorInput>
              </div>
            </div>

            <DesignBoxControl
              :model-value="style('margin')"
              :label="__('Margem')"
              :units="field.units"
              :placeholder="isEditingPart ? '0' : ''"
              :disabled="disabled"
              @update:model-value="setStyle('margin', $event)"
            />

            <DesignBoxControl
              :model-value="style('padding')"
              :label="__('Preenchimento')"
              :units="field.units"
              :placeholder="isEditingPart ? '0' : ''"
              :disabled="disabled"
              @update:model-value="setStyle('padding', $event)"
            />

            <DesignBoxControl
              :model-value="style('border_radius')"
              :label="__('Raio da borda')"
              :units="field.units"
              :placeholder="isEditingPart ? '0' : ''"
              :disabled="disabled"
              @update:model-value="setStyle('border_radius', $event)"
            />

            <div v-if="!isEditingPart">
              <BaseLabel size="sm">{{ __('Ícone') }}</BaseLabel>

              <template v-if="iconFormat === 'class'">
                <p class="mt-1 text-[13px] text-slate-500">
                  {{ __('As classes do ícone no Font Awesome, por exemplo:') }}
                  <BaseCode>fa-solid fa-barcode</BaseCode>
                </p>
                <BaseInputGroup class="mt-2" :disabled="disabled">
                  <template #prefix>
                    <span class="flex w-12 items-center justify-center self-stretch text-[18px] text-slate-600">
                      <i v-if="editing.icon?.class" :class="editing.icon.class" aria-hidden="true" />
                      <BaseIcon v-else name="image" :size="18" class="text-slate-300" />
                    </span>
                  </template>
                  <BaseInput
                    :model-value="editing.icon?.class || ''"
                    :disabled="disabled"
                    :aria-label="__('Classe do ícone')"
                    placeholder="fa-regular fa-credit-card"
                    bare
                    @update:model-value="update('icon.class', $event)"
                  />
                </BaseInputGroup>
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
                  <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-2"
                  >
                    <img
                      v-if="editing.icon?.image"
                      :src="editing.icon.image"
                      alt=""
                      class="max-h-full max-w-full object-contain"
                    />
                    <BaseIcon v-else name="image" :size="18" class="text-slate-300" />
                  </span>
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
                  <BaseIconButton
                    v-if="editing.icon?.image"
                    icon="x"
                    :label="__('Remover ícone')"
                    :disabled="disabled"
                    @click="update('icon.image', '')"
                  />
                </div>
              </template>
            </div>
          </div>

          <div class="order-first lg:sticky lg:top-0 lg:order-none lg:self-start">
            <BaseLabel size="sm">{{ __('Pré-visualizar') }}</BaseLabel>
            <div
              class="mt-2 flex min-h-[8rem] items-center justify-center overflow-hidden rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4"
            >
              <ElementPreview
                v-if="isEditingPart"
                :element="editingParent"
                :sale="editing"
                :device="device"
                :icon-format="iconFormat"
                :starting-from="startingFrom"
                :sale-badge="saleBadge"
                focus-sale
              />
              <ElementPreview
                v-else
                :element="editing"
                :sale="partOf(editingKey)"
                :device="device"
                :icon-format="iconFormat"
                :text="PREVIEWS[editingKey] || editingTitle"
                :starting-from="startingFrom"
                :sale-badge="saleBadge"
              />
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
