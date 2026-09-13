<script setup>
/**
 * BaseCode.vue - code, inline or as a block.
 *
 * @since 6.0.0
 */
defineProps({
  block: { type: Boolean, default: false },
});
</script>

<template>
  <pre
    v-if="block"
    class="overflow-x-auto rounded-lg border border-slate-200 bg-white p-3 font-mono text-[12px] leading-5 text-slate-700"
  ><slot /></pre>

  <code
    v-else
    class="break-all rounded-md border border-slate-200 bg-white px-2 py-1 font-mono text-[13px] text-slate-800"
  ><slot /></code>
</template>
