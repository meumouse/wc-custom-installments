<script setup>
/**
 * ToastStack.vue - transient feedback, stacked at the bottom right.
 *
 * @since 1.0.0
 */
import { __ } from '../../utils/i18n';

defineProps({
  toasts: { type: Array, default: () => [] },
});

const emit = defineEmits(['dismiss']);
</script>

<template>
  <div class="pointer-events-none fixed bottom-6 right-6 z-[100001] flex flex-col gap-2">
    <div
      v-for="toast in toasts"
      :key="toast.id"
      class="pointer-events-auto flex max-w-sm items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-soft"
      :class="toast.type === 'error' ? 'bg-danger text-white' : 'bg-ink text-white'"
      role="status"
    >
      <span class="flex-1">{{ toast.message }}</span>

      <button
        type="button"
        class="shrink-0 text-lg leading-none text-white/70 hover:text-white"
        :aria-label="__('Dismiss')"
        @click="emit('dismiss', toast.id)"
      >
        &times;
      </button>
    </div>
  </div>
</template>
