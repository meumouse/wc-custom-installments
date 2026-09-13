<script setup>
/**
 * ExternalSection.vue - a settings tab whose content an add-on renders.
 *
 * Holds the mount point, waits for the add-on registered under `name` (see
 * sectionRegistry.js), and hands it the element and the context. Leaving the
 * tab tears the add-on's render down, so coming back mounts it fresh.
 *
 * @since 6.0.0
 */
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import BaseNotice from '../base/BaseNotice.vue';
import { resolveSection } from './sectionRegistry';
import { __ } from '../../utils/i18n';

const props = defineProps({
  name: { type: String, required: true },
  context: { type: Object, default: () => ({}) },
});

const target = ref(null);
const state = ref('waiting');

let teardown = null;

/**
 * Mount the add-on's render, once, if it has registered.
 *
 * @since 6.0.0
 * @return {Promise<boolean>} Whether it mounted.
 */
async function tryMount() {
  if (state.value === 'mounted') {
    return true;
  }

  const definition = resolveSection(props.name);

  if (!definition) {
    return false;
  }

  state.value = 'mounted';
  await nextTick();

  try {
    const result = definition.mount(target.value, { ...props.context });

    teardown = typeof result === 'function' ? result : null;
  } catch (error) {
    state.value = 'failed';
  }

  return true;
}

/**
 * Mount when the section named here registers.
 *
 * @since 6.0.0
 * @param {CustomEvent} event - Registration event.
 * @return {void}
 */
function onRegistered(event) {
  if (event?.detail?.name === props.name) {
    tryMount();
  }
}

/**
 * Give up waiting once the page has finished loading every script.
 *
 * @since 6.0.0
 * @return {void}
 */
function onPageLoaded() {
  if (state.value === 'waiting' && !resolveSection(props.name)) {
    state.value = 'missing';
  }
}

onMounted(async () => {
  if (await tryMount()) {
    return;
  }

  window.addEventListener('wc-custom-installments:section-registered', onRegistered);

  if (document.readyState === 'complete') {
    // Module scripts run before `load`; one more tick lets a late one register.
    setTimeout(onPageLoaded, 0);
  } else {
    window.addEventListener('load', onPageLoaded, { once: true });
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('wc-custom-installments:section-registered', onRegistered);
  window.removeEventListener('load', onPageLoaded);

  if (teardown) {
    try {
      teardown();
    } finally {
      teardown = null;
    }
  }
});
</script>

<template>
  <div>
    <div v-if="state === 'waiting'" class="wcci-skeleton" style="height: 240px" />

    <BaseNotice v-else-if="state === 'missing'" tone="warning">
      {{ __('Esta seção vem de um complemento que não carregou. Recarregue a página ou verifique se o complemento está ativo e atualizado.') }}
    </BaseNotice>

    <BaseNotice v-else-if="state === 'failed'" tone="danger">
      {{ __('Não foi possível exibir esta seção. Recarregue a página e tente novamente.') }}
    </BaseNotice>

    <div v-show="state === 'mounted'" ref="target" />
  </div>
</template>
