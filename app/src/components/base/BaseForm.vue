<script setup>
/**
 * BaseForm.vue - a form that never navigates.
 *
 * Every form in the admin apps submits through the REST client, so the native
 * submission is always prevented. Keeping the <form> element still gives Enter
 * in a field its expected meaning.
 *
 * @since 6.0.0
 */
const emit = defineEmits(['submit']);
</script>

<template>
  <form novalidate @submit.prevent="emit('submit', $event)">
    <slot />
  </form>
</template>
