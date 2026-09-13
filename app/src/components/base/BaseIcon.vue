<script setup>
/**
 * BaseIcon.vue - an icon, named rather than imported.
 *
 * The schema names a section's icon from PHP ('credit-card'), and markup never
 * crosses that boundary, so the name is resolved here. Only the icons listed
 * below reach the bundle: each is imported on its own, which keeps the rest of
 * the Boxicons set out of it.
 *
 * @since 6.0.0
 */
import { computed } from 'vue';
import AlertCircle from '@boxicons/vue/AlertCircle';
import AlertTriangle from '@boxicons/vue/AlertTriangle';
import ArrowLeft from '@boxicons/vue/ArrowLeft';
import ArrowRight from '@boxicons/vue/ArrowRight';
import ArrowToBottom from '@boxicons/vue/ArrowToBottom';
import ArrowToTop from '@boxicons/vue/ArrowToTop';
import BookOpen from '@boxicons/vue/BookOpen';
import Check from '@boxicons/vue/Check';
import CheckCircle from '@boxicons/vue/CheckCircle';
import ChevronDown from '@boxicons/vue/ChevronDown';
import Cog from '@boxicons/vue/Cog';
import CreditCard from '@boxicons/vue/CreditCard';
import Crown from '@boxicons/vue/Crown';
import InfoCircle from '@boxicons/vue/InfoCircle';
import Key from '@boxicons/vue/Key';
import Link from '@boxicons/vue/Link';
import Lock from '@boxicons/vue/Lock';
import Palette from '@boxicons/vue/Palette';
import Paragraph from '@boxicons/vue/Paragraph';
import Percentage from '@boxicons/vue/Percentage';
import RefreshCcw from '@boxicons/vue/RefreshCcw';
import Save from '@boxicons/vue/Save';
import Search from '@boxicons/vue/Search';
import Store from '@boxicons/vue/Store';
import Tag from '@boxicons/vue/Tag';
import Wallet from '@boxicons/vue/Wallet';
import X from '@boxicons/vue/X';

const ICONS = {
  'alert-circle': AlertCircle,
  'alert-triangle': AlertTriangle,
  'arrow-left': ArrowLeft,
  'arrow-right': ArrowRight,
  download: ArrowToBottom,
  upload: ArrowToTop,
  'book-open': BookOpen,
  check: Check,
  'check-circle': CheckCircle,
  'chevron-down': ChevronDown,
  cog: Cog,
  'credit-card': CreditCard,
  crown: Crown,
  'info-circle': InfoCircle,
  key: Key,
  link: Link,
  lock: Lock,
  palette: Palette,
  paragraph: Paragraph,
  percentage: Percentage,
  refresh: RefreshCcw,
  save: Save,
  search: Search,
  store: Store,
  tag: Tag,
  wallet: Wallet,
  x: X,
};

const props = defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 20 },
  label: { type: String, default: '' },
});

const component = computed(() => ICONS[props.name] || null);
</script>

<template>
  <component
    :is="component"
    v-if="component"
    :width="size"
    :height="size"
    fill="currentColor"
    class="shrink-0"
    :role="label ? 'img' : undefined"
    :aria-label="label || undefined"
    :aria-hidden="label ? undefined : 'true'"
    focusable="false"
  />
</template>
