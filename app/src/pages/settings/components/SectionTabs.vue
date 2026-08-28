<script setup>
/**
 * SectionTabs.vue - the settings sections, as a tab strip.
 *
 * @since 1.0.0
 */
defineProps({
  sections: { type: Array, default: () => [] },
  active: { type: String, default: '' },
});

const emit = defineEmits(['select']);
</script>

<template>
  <nav class="flex flex-wrap gap-1.5 border-b border-shell-200 pb-3" role="tablist">
    <button
      v-for="section in sections"
      :key="section.id"
      type="button"
      role="tab"
      :aria-selected="section.id === active"
      class="rounded-lg px-4 py-2 text-sm font-semibold transition-colors"
      :class="section.id === active ? 'bg-primary text-white' : 'text-ink hover:bg-shell-100'"
      @click="emit('select', section.id)"
    >
      {{ section.title }}
    </button>
  </nav>
</template>
