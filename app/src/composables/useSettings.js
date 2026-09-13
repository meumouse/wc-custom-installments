import { computed, reactive, ref } from 'vue';
import { createApiClient } from '../utils/api';
import { __ } from '../utils/i18n';

/**
 * Own the settings screen's state: the values, what changed, and the requests.
 *
 * Every write goes back through the server's answer rather than the submitted
 * payload, because they can differ: a locked field the browser tried to change
 * comes back with the value that is actually stored. Trusting the request would
 * leave the screen showing a setting the store does not have.
 *
 * @since 6.0.0
 * @param {object} bootstrap - Payload from the settings bootstrap endpoint.
 * @return {object} State and actions for the settings screen.
 */
export function useSettings(bootstrap) {
  const api = createApiClient(bootstrap);

  const schema = ref(bootstrap.schema || []);
  const features = ref(bootstrap.features || {});
  const settings = reactive({ ...(bootstrap.settings || {}) });

  // A ref, not a plain variable: the dirty check reads it, and a computed only
  // re-runs for reactive dependencies. As a `let` it would go on reporting
  // unsaved changes after a save that changed nothing on the server's side.
  const saved = ref(JSON.stringify(settings));

  const isSaving = ref(false);
  const isResetting = ref(false);

  const isDirty = computed(() => JSON.stringify(settings) !== saved.value);

  /**
   * Replace the local state with what the server reports it stored.
   *
   * @since 6.0.0
   * @param {object} response - Response body from a settings endpoint.
   * @return {void}
   */
  function adopt(response) {
    if (response?.settings) {
      Object.keys(settings).forEach((key) => {
        if (!(key in response.settings)) {
          delete settings[key];
        }
      });

      Object.assign(settings, response.settings);
    }

    if (response?.schema) {
      schema.value = response.schema;
    }

    saved.value = JSON.stringify(settings);
  }

  /**
   * Persist the settings.
   *
   * @since 6.0.0
   * @return {Promise<{ok: boolean, message: string}>} What to tell the reader.
   */
  async function save() {
    isSaving.value = true;

    try {
      const response = await api.post('admin/settings', { settings: { ...settings } });

      adopt(response);

      return { ok: true, message: response.message || __('Settings saved.') };
    } catch (error) {
      return { ok: false, message: error.message || __('Could not save the settings.') };
    } finally {
      isSaving.value = false;
    }
  }

  /**
   * Restore every setting to its default.
   *
   * @since 6.0.0
   * @return {Promise<{ok: boolean, message: string}>} What to tell the reader.
   */
  async function reset() {
    isResetting.value = true;

    try {
      const response = await api.post('admin/settings/reset', {});

      adopt(response);

      return { ok: true, message: response.message || __('Settings restored to their defaults.') };
    } catch (error) {
      return { ok: false, message: error.message || __('Could not restore the settings.') };
    } finally {
      isResetting.value = false;
    }
  }

  /**
   * Fetch the current configuration as a portable document.
   *
   * @since 6.0.0
   * @return {Promise<object>} The export document.
   */
  async function exportSettings() {
    return api.get('admin/settings/export');
  }

  /**
   * Apply an exported configuration.
   *
   * @since 6.0.0
   * @param {object} document - A previously exported document.
   * @return {Promise<{ok: boolean, message: string}>} What to tell the reader.
   */
  async function importSettings(document) {
    try {
      const response = await api.post('admin/settings/import', { document });

      if (response.status === 'error') {
        return { ok: false, message: response.message };
      }

      adopt(response);

      return { ok: true, message: response.message || __('Settings imported.') };
    } catch (error) {
      return { ok: false, message: error.message || __('Could not import that file.') };
    }
  }

  return {
    api,
    schema,
    features,
    settings,
    isDirty,
    isSaving,
    isResetting,
    save,
    reset,
    exportSettings,
    importSettings,
  };
}

/**
 * Decide whether a schema node is visible, given the current values.
 *
 * A node declares `depends_on` as { setting: expectedValue }. Every entry has
 * to match: dependencies here are narrowing, never alternatives.
 *
 * @since 6.0.0
 * @param {object} node - A card or field definition.
 * @param {object} settings - Current setting values.
 * @return {boolean} Whether the node should render.
 */
export function isVisible(node, settings) {
  const dependencies = node?.depends_on;

  if (!dependencies || typeof dependencies !== 'object') {
    return true;
  }

  return Object.entries(dependencies).every(([key, expected]) => {
    const actual = settings?.[key];

    return Array.isArray(expected) ? expected.includes(actual) : actual === expected;
  });
}
