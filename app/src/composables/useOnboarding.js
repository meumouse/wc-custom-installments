import { computed, reactive, ref } from 'vue';
import { createApiClient } from '../utils/api';
import { __ } from '../utils/i18n';

/**
 * Own the setup wizard's state and its requests.
 *
 * Each step saves as the reader leaves it rather than everything at the end.
 * A wizard that loses four answers because the fifth request failed is worse
 * than no wizard.
 *
 * @since 6.0.0
 * @param {object} bootstrap - Payload from the onboarding bootstrap endpoint.
 * @return {object} State and actions for the wizard.
 */
export function useOnboarding(bootstrap) {
  const api = createApiClient(bootstrap);

  const steps = ref(bootstrap.steps || []);
  const fields = ref(bootstrap.fields || {});
  const settings = reactive({ ...(bootstrap.settings || {}) });

  // A store upgrading from 5.x already answered all of this years ago. Walking
  // them through it again invites them to overwrite it with our defaults.
  const migrated = Boolean(bootstrap.migrated);
  const index = ref(migrated ? Math.max(0, steps.value.length - 1) : 0);

  const isSaving = ref(false);
  const error = ref(null);

  const current = computed(() => steps.value[index.value] || null);
  const isFirst = computed(() => index.value === 0);
  const isLast = computed(() => index.value >= steps.value.length - 1);
  const progress = computed(() =>
    steps.value.length ? Math.round(((index.value + 1) / steps.value.length) * 100) : 0,
  );

  /**
   * Persist the current step.
   *
   * @since 6.0.0
   * @return {Promise<boolean>} Whether it saved.
   */
  async function persist() {
    isSaving.value = true;
    error.value = null;

    try {
      const response = await api.post('admin/onboarding/step', {
        step: current.value?.id || '',
        settings: { ...settings },
      });

      Object.assign(settings, response.settings || {});

      return true;
    } catch (requestError) {
      error.value = requestError.message || __('Não foi possível salvar. Tente novamente.');

      return false;
    } finally {
      isSaving.value = false;
    }
  }

  /**
   * Save and move on.
   *
   * @since 6.0.0
   * @return {Promise<void>}
   */
  async function next() {
    if (!(await persist())) {
      return;
    }

    if (!isLast.value) {
      index.value += 1;
    }
  }

  /**
   * Go back, without saving.
   *
   * @since 6.0.0
   * @return {void}
   */
  function back() {
    if (!isFirst.value) {
      index.value -= 1;
    }
  }

  /**
   * Jump to a step the reader has already reached.
   *
   * @since 6.0.0
   * @param {number} target - Step index.
   * @return {void}
   */
  function goTo(target) {
    if (target >= 0 && target < steps.value.length) {
      index.value = target;
    }
  }

  /**
   * Finish the wizard.
   *
   * @since 6.0.0
   * @return {Promise<void>}
   */
  async function complete() {
    await persist();

    try {
      await api.post('admin/onboarding/complete', {});
    } catch (requestError) {
      // The settings are already saved; failing to record that the wizard
      // finished only means the reminder comes back, which is recoverable.
      error.value = requestError.message;
    }
  }

  /**
   * Leave without finishing.
   *
   * @since 6.0.0
   * @return {Promise<void>}
   */
  async function skip() {
    try {
      await api.post('admin/onboarding/skip', {});
    } catch (requestError) {
      error.value = requestError.message;
    }
  }

  return {
    api,
    steps,
    fields,
    settings,
    migrated,
    index,
    current,
    isFirst,
    isLast,
    isSaving,
    error,
    progress,
    next,
    back,
    goTo,
    complete,
    skip,
  };
}
