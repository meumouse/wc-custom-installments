/**
 * panels.js - the instalment details panel and dialog.
 *
 * @since 6.0.0
 */
import { all, delegate, one } from './dom';

/**
 * Open and close the collapsible panel.
 *
 * The height is measured rather than animated to `auto`, because `auto` is not
 * a value CSS can transition between.
 *
 * @since 6.0.0
 * @return {void}
 */
export function initAccordion() {
  delegate('.wci-accordion-header', 'click', (event, header) => {
    event.preventDefault();

    const content = header.nextElementSibling;

    if (!content || !content.classList.contains('wci-accordion-content')) {
      return;
    }

    const item = header.closest('.wci-accordion-item');
    const isOpen = item?.classList.contains('active');

    if (isOpen) {
      content.style.maxHeight = '0px';
      item.classList.remove('active');
      document.body.classList.remove('wci-accordion-active');

      return;
    }

    content.style.maxHeight = `${content.scrollHeight}px`;
    item?.classList.add('active');
    document.body.classList.add('wci-accordion-active');
  });
}

/**
 * Open and close the dialog.
 *
 * @since 6.0.0
 * @return {void}
 */
export function initModal() {
  delegate('.wci-open-popup', 'click', (event, button) => {
    event.preventDefault();

    const container = button.parentElement?.querySelector('.wci-popup-container');

    if (container) {
      container.classList.add('show');
      document.body.classList.add('wci-modal-active');
    }
  });

  delegate('.wci-close-popup', 'click', (event) => {
    event.preventDefault();
    close();
  });

  // A click on the backdrop, but not on the panel sitting on top of it.
  document.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.classList.contains('wci-popup-container')) {
      close();
    }
  });

  // Escape closes it, which is what every dialog on the web does.
  document.addEventListener('keydown', (event) => {
    if ('Escape' === event.key && one('.wci-popup-container.show')) {
      close();
    }
  });
}

/**
 * Close every open dialog.
 *
 * @since 6.0.0
 * @return {void}
 */
function close() {
  all('.wci-popup-container.show').forEach((container) => container.classList.remove('show'));
  document.body.classList.remove('wci-modal-active');
}
