<script setup>
/**
 * BaseButton.vue - the action button, and the link that looks like one.
 *
 * Given an `href` it renders a link, so an action that navigates is still a
 * link to the browser (middle-click, "open in new tab") while looking like
 * every other button on the screen. External links open in a new tab.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import BaseIcon from './BaseIcon.vue';

const props = defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  href: { type: String, default: '' },
  external: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  icon: { type: String, default: '' },
  iconPosition: { type: String, default: 'start' },
  type: { type: String, default: 'button' },
  block: { type: Boolean, default: false },
});

const VARIANTS = {
  primary: 'bg-primary-700 text-white shadow-sm hover:bg-primary-800',
  secondary: 'bg-white text-slate-700 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:text-slate-900',
  outline: 'border border-primary-200 bg-white text-primary-700 hover:bg-primary-50',
  ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
  danger: 'bg-rose-600 text-white shadow-sm hover:bg-rose-700',
  'danger-outline': 'border border-rose-200 bg-white text-rose-600 hover:bg-rose-50',
  link: 'text-primary-700 underline-offset-4 hover:text-primary-800 hover:underline',
};

const SIZES = {
  sm: 'gap-1.5 px-3 py-2 text-[13px]',
  md: 'gap-2 px-5 py-3 text-[14px]',
  lg: 'gap-2 px-6 py-3.5 text-[15px]',
};

const ICON_SIZES = { sm: 16, md: 18, lg: 20 };

const isLink = computed(() => Boolean(props.href) && !props.disabled);
const isInactive = computed(() => props.disabled || props.loading);

const classes = computed(() => [
  'inline-flex items-center justify-center rounded-[8px] font-semibold leading-5 no-underline transition-colors',
  'focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-100',
  props.variant === 'link' ? 'gap-1.5 p-0 text-[14px]' : SIZES[props.size] || SIZES.md,
  VARIANTS[props.variant] || VARIANTS.primary,
  props.block ? 'w-full' : '',
  isInactive.value ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
]);

const iconSize = computed(() => ICON_SIZES[props.size] || ICON_SIZES.md);
</script>

<template>
  <a
    v-if="isLink"
    :href="href"
    :class="classes"
    :target="external ? '_blank' : undefined"
    :rel="external ? 'noopener noreferrer' : undefined"
  >
    <BaseIcon v-if="icon && iconPosition === 'start'" :name="icon" :size="iconSize" />
    <slot />
    <BaseIcon v-if="icon && iconPosition === 'end'" :name="icon" :size="iconSize" />
  </a>

  <button v-else :type="type" :disabled="isInactive" :aria-busy="loading || undefined" :class="classes">
    <span
      v-if="loading"
      class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"
      aria-hidden="true"
    />
    <BaseIcon v-else-if="icon && iconPosition === 'start'" :name="icon" :size="iconSize" />
    <slot />
    <BaseIcon v-if="!loading && icon && iconPosition === 'end'" :name="icon" :size="iconSize" />
  </button>
</template>
