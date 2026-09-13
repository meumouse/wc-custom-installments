import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import BaseNotice from '../components/base/BaseNotice.vue';
import { __ } from './i18n';

/**
 * Read the minimal bootstrap config WordPress localized for this screen.
 *
 * Only what the client needs to fetch its payload is embedded in the page: the
 * REST root, a nonce, the page slug and the endpoint to request.
 *
 * @since 6.0.0
 * @return {Object|null} Bootstrap config, or null when it is unavailable.
 */
export function readBootstrapConfig() {
  const config = globalThis.wcCustomInstallmentsBootstrap;

  return config && typeof config === 'object' ? config : null;
}

/**
 * Fetch the page payload from the REST API.
 *
 * @since 6.0.0
 * @param {Object} config - Bootstrap config from readBootstrapConfig().
 * @return {Promise<Object>} Parsed payload.
 */
async function fetchBootstrap(config) {
  const root = String(config.restUrl || '').replace(/\/$/, '');
  const endpoint = String(config.endpoint || '').replace(/^\//, '');

  const response = await fetch(`${root}/${endpoint}`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}),
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error(`Bootstrap request failed (${response.status}).`);
  }

  return response.json();
}

/**
 * Render an error message into the mount element.
 *
 * Mounted as its own small app, so even the failure path is made of the same
 * components as the page it replaces.
 *
 * @since 6.0.0
 * @param {HTMLElement} mount - Mount element.
 * @param {string} message - Message to display.
 * @return {void}
 */
function renderBootstrapError(mount, message) {
  createApp({
    render: () => h(BaseNotice, { tone: 'danger', live: true }, () => message),
  }).mount(mount);
}

/**
 * Mount a page component once its bootstrap payload resolves.
 *
 * The skeleton already inside the mount element stays visible until then; Vue
 * replaces it when the component mounts.
 *
 * @since 6.0.0
 * @param {string} mountId - DOM id of the mount point.
 * @param {Object} component - Vue component to mount.
 * @return {Promise<import('vue').App|null>} Mounted app, or null.
 */
export async function mountPage(mountId, component) {
  const mount = document.getElementById(mountId);

  if (!mount) {
    return null;
  }

  // Every utility is scoped under this class (tailwind.config.js), so the app
  // claims it itself rather than trusting each view file to carry it.
  mount.classList.add('wc-custom-installments-app');

  const config = readBootstrapConfig();

  if (!config) {
    renderBootstrapError(mount, __('Não foi possível iniciar esta tela. Recarregue a página.'));

    return null;
  }

  let bootstrap = {};

  try {
    bootstrap = await fetchBootstrap(config);

    if (config.page) {
      bootstrap.page = config.page;
    }
  } catch (error) {
    renderBootstrapError(mount, __('Não foi possível carregar esta página. Recarregue e tente novamente.'));

    return null;
  }

  const app = createApp(component, { bootstrap });

  app.use(createPinia());

  return app.mount(mount);
}
