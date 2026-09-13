/**
 * portal.js
 *
 * The element teleported content (dropdowns, dialogs, toasts) is rendered into.
 *
 * It exists because Tailwind is scoped to `.wc-custom-installments-app`: a
 * dialog teleported straight into <body> would lose every utility it was
 * written with. The portal carries the class, so anything inside it is styled
 * exactly as it would be in place.
 *
 * @since 6.0.0
 */
const PORTAL_ID = 'wc-custom-installments-portal';

/**
 * Return the portal element, creating it on first use.
 *
 * @since 6.0.0
 * @return {HTMLElement|string} The element, or 'body' outside a browser.
 */
export function ensurePortal() {
  if (typeof document === 'undefined') {
    return 'body';
  }

  let portal = document.getElementById(PORTAL_ID);

  if (!portal) {
    portal = document.createElement('div');
    portal.id = PORTAL_ID;
    portal.className = 'wc-custom-installments-app';
    document.body.appendChild(portal);
  }

  return portal;
}
