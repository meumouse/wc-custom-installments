<script setup>
/**
 * BrandMark.vue - the plugin logo.
 *
 * The SVG is imported from assets/brand, so Vite ships it inside the bundle.
 * No URL is built at runtime: a path hard-coded to /wp-content/plugins breaks
 * on every install with a moved content directory, and the previews have no
 * WordPress to ask.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import logoUrl from '@brand/logo.svg';
import { __ } from '../../utils/i18n';

const props = defineProps({
  size: { type: String, default: 'md' },
  // The mark usually sits beside the plugin name, which already says it.
  decorative: { type: Boolean, default: true },
});

const HEIGHTS = { xs: 24, sm: 32, md: 40, lg: 48, xl: 56 };

const height = computed(() => HEIGHTS[props.size] || HEIGHTS.md);
</script>

<template>
  <img
    :src="logoUrl"
    :alt="decorative ? '' : __('Custom Installments for WooCommerce')"
    :height="height"
    :style="{ height: `${height}px`, width: 'auto' }"
    class="block shrink-0"
    draggable="false"
  />
</template>
