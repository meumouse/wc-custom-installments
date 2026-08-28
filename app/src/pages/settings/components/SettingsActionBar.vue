<script setup>
/**
 * SettingsActionBar.vue - the save bar, pinned to the bottom while dirty.
 *
 * It slides out of view rather than unmounting. A <transition> around a v-if
 * needs a transitionend to finish, and when the element is already sitting at
 * its leave-to transform there is no transition to end: the bar stays parked
 * off-screen, still in the DOM, wearing its leave classes. Keeping it mounted
 * and moving it removes that failure mode entirely.
 *
 * `inert` is what makes the hidden bar genuinely gone. Off-screen is not the
 * same as unreachable, and a keyboard user should not be able to tab into a
 * Save button they cannot see.
 *
 * @since 1.0.0
 */
import BaseButton from '../../../components/base/BaseButton.vue';
import { __ } from '../../../utils/i18n';

defineProps({
  dirty: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
});

const emit = defineEmits(['save', 'discard']);
</script>

<template>
  <div
    class="fixed inset-x-0 bottom-0 z-[99999] border-t border-shell-200 bg-panel/95 px-6 py-3 shadow-soft backdrop-blur transition-transform duration-200"
    :class="dirty ? 'translate-y-0' : 'pointer-events-none translate-y-full'"
    :inert="!dirty"
    :aria-hidden="!dirty"
  >
    <div class="mx-auto flex max-w-5xl items-center justify-between gap-4">
      <span class="text-sm text-muted">{{ __('You have unsaved changes.') }}</span>

      <div class="flex gap-2">
        <BaseButton variant="ghost" :disabled="saving" @click="emit('discard')">
          {{ __('Discard') }}
        </BaseButton>

        <BaseButton :disabled="saving" @click="emit('save')">
          {{ saving ? __('Saving...') : __('Save changes') }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>
