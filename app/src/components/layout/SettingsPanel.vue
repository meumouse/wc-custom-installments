<script setup>
/**
 * SettingsPanel.vue - the white card holding a page's content.
 *
 * The `footer` slot is for the save bar: it sits inside the card and sticks to
 * the bottom of the viewport while the card is taller than it.
 *
 * @since 6.0.0
 */
defineProps({
  id: { type: String, default: '' },
  labelledBy: { type: String, default: '' },
  padded: { type: Boolean, default: true },
});
</script>

<template>
  <section
    :id="id || undefined"
    :role="labelledBy ? 'tabpanel' : undefined"
    :aria-labelledby="labelledBy || undefined"
    class="rounded-[8px] bg-white shadow-card ring-1 ring-slate-100"
  >
    <div :class="padded ? 'px-6 py-8 sm:px-10 sm:py-12' : ''">
      <slot />
    </div>

    <slot name="footer" />
  </section>
</template>
