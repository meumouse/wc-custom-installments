import { computed } from 'vue';

/**
 * Pick an image from the WordPress media library.
 *
 * The settings screen loads the library only for someone allowed to upload
 * files, so every control offering it also takes a typed address: `canBrowse`
 * says whether to show the button at all.
 *
 * @since 6.0.0
 * @return {object} Whether the library is there, and the picker.
 */
export function useMediaLibrary() {
  const canBrowse = computed(() => typeof window !== 'undefined' && Boolean(window.wp?.media));

  /**
   * Open the library on images and hand the chosen one's address over.
   *
   * @since 6.0.0
   * @param {object} labels - The frame's `title` and the `button` that confirms.
   * @param {Function} onSelect - Receives the address of the chosen image.
   * @return {void}
   */
  function pickImage(labels, onSelect) {
    if (!canBrowse.value) {
      return;
    }

    const frame = window.wp.media({
      title: labels.title,
      button: { text: labels.button },
      library: { type: 'image' },
      multiple: false,
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();

      onSelect(attachment.url || '');
    });

    frame.open();
  }

  return { canBrowse, pickImage };
}
