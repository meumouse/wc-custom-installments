import { createApp } from 'vue';
import { createPinia } from 'pinia';

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
 * Render a minimal error message into the mount element.
 *
 * @since 1.0.0
 * @param {HTMLElement} mount - Mount element.
 * @param {string} message - Message to display.
 * @return {void}
 */
function renderBootstrapError(mount, message) {
  mount.innerHTML = '';

  const notice = document.createElement('div');
  notice.className = 'notice notice-error';
  notice.setAttribute('role', 'alert');
  notice.innerHTML = `<p>${message}</p>`;

  mount.appendChild(notice);
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

  const config = readBootstrapConfig();

  if (!config) {
    renderBootstrapError(mount, 'This screen could not start. Please reload the page.');

    return null;
  }

  let bootstrap = {};

  try {
    bootstrap = await fetchBootstrap(config);

    if (config.page) {
      bootstrap.page = config.page;
    }
  } catch (error) {
    renderBootstrapError(mount, 'Could not load this page. Please reload and try again.');

    return null;
  }

  const app = createApp(component, { bootstrap });

  app.use(createPinia());

  return app.mount(mount);
}
