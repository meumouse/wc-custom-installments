/**
 * panels.js - the instalment details panel and dialog.
 *
 * @since 6.0.0
 */
import { all, delegate, one } from './dom';

let accordionBound = false;
const boundModals = new Set();
let escapeBound = false;

/**
 * Open and close the collapsible panel.
 *
 * Safe to call more than once: a second set of listeners would toggle the
 * panel open and straight back shut on every click.
 *
 * @since 6.0.0
 * @return {void}
 */
export function initAccordion() {
  if (accordionBound) {
    return;
  }

  accordionBound = true;

  delegate('.wci-accordion-header', 'click', (event, header) => {
    event.preventDefault();
    toggleAccordion(header);
  });
}

/**
 * Open or close one panel.
 *
 * The height is measured rather than animated to `auto`, because `auto` is not
 * a value CSS can transition between.
 *
 * @since 6.0.0
 * @param {Element|Event} target - The panel header, or an event whose current target is it.
 * @return {void}
 */
export function toggleAccordion(target) {
  const header = target instanceof Element ? target : target?.currentTarget || target?.target;

  if (!(header instanceof Element)) {
    return;
  }

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
}

/**
 * Open and close the dialog.
 *
 * @since 6.0.0
 * @return {void}
 */
export function initModal() {
  bindModal('.wci-open-popup', '.wci-popup-container', '.wci-close-popup');

  if (escapeBound) {
    return;
  }

  escapeBound = true;

  // Escape closes it, which is what every dialog on the web does.
  document.addEventListener('keydown', (event) => {
    if ('Escape' === event.key && one('.wci-popup-container.show')) {
      close();
    }
  });
}

/**
 * Wire a dialog made of an opener, a backdrop and a close button.
 *
 * The same contract as 5.x's `displayModal()`: the opener shows the dialog
 * beside it, a click on the backdrop itself or on the close button hides it.
 * A set of selectors is wired once, however often this is called.
 *
 * @since 6.0.0
 * @param {string} openSelector - What opens the dialog.
 * @param {string} containerSelector - The backdrop holding the dialog.
 * @param {string} closeSelector - What closes it.
 * @return {void}
 */
export function bindModal(openSelector, containerSelector, closeSelector) {
  const key = [openSelector, containerSelector, closeSelector].join('|');

  if (boundModals.has(key)) {
    return;
  }

  boundModals.add(key);

  delegate(openSelector, 'click', (event, button) => {
    event.preventDefault();

    const container = button.parentElement?.querySelector(containerSelector);

    if (container) {
      container.classList.add('show');
      document.body.classList.add('wci-modal-active');
    }
  });

  delegate(closeSelector, 'click', (event) => {
    event.preventDefault();
    close(containerSelector);
  });

  // A click on the backdrop, but not on the panel sitting on top of it.
  document.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.matches(containerSelector)) {
      close(containerSelector);
    }
  });
}

/**
 * Close every open dialog.
 *
 * @since 6.0.0
 * @param {string} [containerSelector] - The backdrops to close.
 * @return {void}
 */
function close(containerSelector = '.wci-popup-container') {
  const selectors = containerSelector === '.wci-popup-container' ? [containerSelector] : [containerSelector, '.wci-popup-container'];

  selectors.forEach((selector) => {
    all(selector).forEach((container) => container.classList.remove('show'));
  });

  document.body.classList.remove('wci-modal-active');
}
