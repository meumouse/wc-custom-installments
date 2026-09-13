<script setup>
/**
 * ToastStack.vue - transient feedback, stacked at the top right.
 *
 * Each toast carries a coloured header naming what happened and a bar that
 * runs out with its remaining time, as in Joinotify. Errors are announced
 * assertively; a success can wait for the reader.
 *
 * @since 6.0.0
 */
import BaseIcon from '../base/BaseIcon.vue';
import BaseIconButton from '../base/BaseIconButton.vue';
import { ensurePortal } from '../../utils/portal';
import { __ } from '../../utils/i18n';

defineProps({
  toasts: { type: Array, default: () => [] },
});

const emit = defineEmits(['dismiss']);

const portal = ensurePortal();

const TYPES = {
  success: { header: 'bg-success text-white', bar: 'bg-success', icon: 'check-circle', title: () => __('Success') },
  error: { header: 'bg-danger text-white', bar: 'bg-danger', icon: 'alert-circle', title: () => __('Error') },
  warning: { header: 'bg-warning text-dark', bar: 'bg-warning', icon: 'alert-triangle', title: () => __('Warning') },
  info: { header: 'bg-info text-white', bar: 'bg-info', icon: 'info-circle', title: () => __('Information') },
};

/**
 * Presentation for a toast type.
 *
 * @since 6.0.0
 * @param {string} type - Toast type.
 * @return {object} Classes, icon and title.
 */
function look(type) {
  return TYPES[type] || TYPES.info;
}
</script>

<template>
  <Teleport :to="portal">
    <div class="pointer-events-none fixed right-3 top-12 z-[100090] flex w-[350px] max-w-[calc(100vw-1.5rem)] flex-col gap-3">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="wcci-slide-in pointer-events-auto overflow-hidden rounded-lg bg-white shadow-toast ring-1 ring-black/5"
        :role="toast.type === 'error' ? 'alert' : 'status'"
      >
        <div class="flex items-center gap-2 px-4 py-2 font-bold" :class="look(toast.type).header">
          <BaseIcon :name="look(toast.type).icon" :size="18" />
          <span class="flex-1 text-[14px]">{{ look(toast.type).title() }}</span>
          <BaseIconButton
            icon="x"
            :size="18"
            :label="__('Dismiss')"
            :tone="toast.type === 'warning' ? 'muted' : 'inverse'"
            @click="emit('dismiss', toast.id)"
          />
        </div>

        <p class="px-4 py-4 text-[15px] leading-6 text-slate-600">{{ toast.message }}</p>

        <div
          class="h-[3px] origin-left"
          :class="look(toast.type).bar"
          :style="{ animation: `wcci-toast-progress ${toast.duration || 4000}ms linear forwards` }"
        />
      </div>
    </div>
  </Teleport>
</template>
