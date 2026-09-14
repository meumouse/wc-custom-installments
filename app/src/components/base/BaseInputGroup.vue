<script setup>
/**
 * BaseInputGroup.vue - an input with an addon glued to one side.
 *
 * The group owns the border and the focus ring, so the addon and the field read
 * as a single control. Put a `bare` BaseInput in the default slot and the addon
 * (a unit, a currency symbol, a select) in `prefix` or `suffix`.
 *
 * @since 6.0.0
 */
defineProps({
  disabled: { type: Boolean, default: false },
});
</script>

<template>
  <div
    class="flex w-full items-stretch overflow-hidden rounded-lg border border-slate-200 bg-white transition-colors focus-within:border-primary focus-within:ring-1 focus-within:ring-primary"
    :class="disabled ? 'bg-slate-50' : ''"
  >
    <div
      v-if="$slots.prefix"
      class="flex shrink-0 items-center border-r border-slate-200 bg-slate-50 text-[14px] text-slate-500"
    >
      <slot name="prefix" />
    </div>

    <div class="min-w-0 flex-1">
      <slot />
    </div>

    <div
      v-if="$slots.suffix"
      class="flex shrink-0 items-center border-l border-slate-200 bg-slate-50 text-[14px] text-slate-500"
    >
      <slot name="suffix" />
    </div>
  </div>
</template>
