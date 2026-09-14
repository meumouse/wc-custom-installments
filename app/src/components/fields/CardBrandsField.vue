<script setup>
/**
 * CardBrandsField.vue - the card brands the store registers itself.
 *
 * The stored shape is a list of { id, label, image }. The key is the server's
 * to hand out: a new row leaves here without one and comes back from the save
 * with it, and from then on it names the brand's switches on the credit and
 * debit card lists, which is why those switches only appear after a save.
 *
 * Every change emits the whole list. Rows are told apart by position, which is
 * safe because nothing in a row keeps state of its own.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseBadge from '../base/BaseBadge.vue';
import BaseButton from '../base/BaseButton.vue';
import BaseIcon from '../base/BaseIcon.vue';
import BaseIconButton from '../base/BaseIconButton.vue';
import BaseInput from '../base/BaseInput.vue';
import BaseNotice from '../base/BaseNotice.vue';
import { useMediaLibrary } from '../../composables/useMediaLibrary';
import { __, sprintf } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  field: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const { canBrowse, pickImage } = useMediaLibrary();

const brands = computed(() =>
  (Array.isArray(props.modelValue) ? props.modelValue : []).map((brand) => ({
    id: String(brand?.id || ''),
    label: String(brand?.label || ''),
    image: String(brand?.image || ''),
  })),
);

/**
 * How a row is named to a screen reader, before it has a name of its own.
 *
 * @since 6.0.0
 * @param {object} brand - The row.
 * @return {string} The brand's name, or a stand-in.
 */
function nameOf(brand) {
  return brand.label.trim() || __('sem nome');
}

/**
 * Emit the list with one row changed.
 *
 * @since 6.0.0
 * @param {number} index - Row position.
 * @param {string} key - Either 'label' or 'image'.
 * @param {string} value - The new value.
 * @return {void}
 */
function update(index, key, value) {
  if (props.disabled) {
    return;
  }

  emit(
    'update:modelValue',
    brands.value.map((brand, position) => (position === index ? { ...brand, [key]: value } : brand)),
  );
}

/**
 * Append an empty row.
 *
 * @since 6.0.0
 * @return {void}
 */
function add() {
  if (props.disabled) {
    return;
  }

  emit('update:modelValue', [...brands.value, { id: '', label: '', image: '' }]);
}

/**
 * Remove one row.
 *
 * @since 6.0.0
 * @param {number} index - Row position.
 * @return {void}
 */
function remove(index) {
  if (props.disabled) {
    return;
  }

  emit(
    'update:modelValue',
    brands.value.filter((brand, position) => position !== index),
  );
}

/**
 * Pick a row's image from the media library.
 *
 * @since 6.0.0
 * @param {number} index - Row position.
 * @return {void}
 */
function browse(index) {
  if (props.disabled) {
    return;
  }

  pickImage({ title: __('Escolher a imagem da bandeira'), button: __('Usar esta imagem') }, (url) =>
    update(index, 'image', url),
  );
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <BaseNotice v-if="!brands.length" tone="neutral" icon="credit-card">
      {{ __('Nenhuma bandeira cadastrada ainda.') }}
    </BaseNotice>

    <div
      v-for="(brand, index) in brands"
      :key="index"
      class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3"
    >
      <span
        class="flex h-11 w-16 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-1.5"
      >
        <img v-if="brand.image" :src="brand.image" alt="" class="max-h-full max-w-full object-contain" />
        <BaseIcon v-else name="image" :size="18" class="text-slate-300" />
      </span>

      <!-- On a phone the name and address take a line of their own, below the
           image and the actions; beside them they would be too narrow to read. -->
      <div class="order-last flex w-full min-w-0 flex-col gap-2 sm:order-none sm:w-auto sm:flex-1 sm:flex-row sm:items-center">
        <div class="sm:w-44">
          <BaseInput
            :model-value="brand.label"
            :disabled="disabled"
            :placeholder="__('Nome da bandeira')"
            :aria-label="__('Nome da bandeira')"
            @update:model-value="update(index, 'label', $event)"
          />
        </div>

        <div class="min-w-0 flex-1">
          <BaseInput
            :model-value="brand.image"
            type="url"
            :disabled="disabled"
            placeholder="https://"
            :aria-label="sprintf(__('Imagem da bandeira %s'), nameOf(brand))"
            @update:model-value="update(index, 'image', $event)"
          />
        </div>
      </div>

      <div class="ml-auto flex items-center gap-2 sm:ml-0">
        <BaseBadge v-if="!brand.id" tone="warning">{{ __('Não salva') }}</BaseBadge>

        <BaseButton v-if="canBrowse" variant="secondary" icon="image" :disabled="disabled" @click="browse(index)">
          {{ __('Procurar') }}
        </BaseButton>

        <BaseIconButton
          icon="trash"
          tone="danger"
          :label="sprintf(__('Remover a bandeira %s'), nameOf(brand))"
          :disabled="disabled"
          @click="remove(index)"
        />
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
      <BaseButton variant="secondary" icon="plus" :disabled="disabled" @click="add">
        {{ __('Adicionar bandeira') }}
      </BaseButton>

      <p class="text-[13px] text-slate-500">
        {{ __('Uma imagem pequena, de preferência SVG ou PNG com fundo transparente.') }}
      </p>
    </div>
  </div>
</template>
