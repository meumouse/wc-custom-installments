import { ref } from 'vue';

/**
 * Transient feedback messages.
 *
 * @since 6.0.0
 * @return {object} The toast list and the helpers that drive it.
 */
export function useToasts() {
  const toasts = ref([]);
  let counter = 0;

  /**
   * Remove one toast.
   *
   * @since 6.0.0
   * @param {number} id - Toast id.
   * @return {void}
   */
  function dismiss(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
  }

  /**
   * Show a toast, and take it away again on its own.
   *
   * An error stays twice as long: it is the one the reader has to act on, and
   * losing it mid-sentence means doing the whole action again to read it.
   *
   * @since 6.0.0
   * @param {string} message - What to say.
   * @param {string} [type] - Either 'success' or 'error'.
   * @return {void}
   */
  function push(message, type = 'success') {
    counter += 1;

    const id = counter;

    toasts.value = [...toasts.value, { id, message, type }];

    setTimeout(() => dismiss(id), type === 'error' ? 8000 : 4000);
  }

  return { toasts, push, dismiss };
}
