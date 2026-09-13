/**
 * sectionRegistry.js
 *
 * Settings tabs whose content an add-on renders. A section in the schema that
 * names a `component` gets a mount point instead of field groups, and the
 * add-on registered under that name fills it.
 *
 * The add-on mounts an app of its own rather than handing over a component: it
 * is a separately built bundle with its own copy of Vue, and a component
 * compiled against another runtime renders inside this app with its
 * reactivity silently cut off. An app per mount point has no such problem.
 *
 * Registration is race-safe. The add-on's script may run before or after this
 * one: `window.WCCustomInstallmentsSections` queues nothing, it simply stores
 * the definition and announces it, and a mount point waiting for a name picks
 * it up whenever it arrives.
 *
 * @since 6.0.0
 */
const registry = new Map();

/**
 * Register the renderer of an add-on section.
 *
 * `mount(element, context)` renders into the element and returns a function
 * that tears the render down, called when the reader leaves the tab.
 *
 * @since 6.0.0
 * @param {string} name - The section's `component` name in the schema.
 * @param {{mount: Function}} definition - The renderer.
 * @return {void}
 */
export function registerSection(name, definition) {
  if (!name || typeof definition?.mount !== 'function') {
    return;
  }

  registry.set(String(name), definition);
  syncGlobal();

  if (typeof window !== 'undefined' && typeof window.CustomEvent === 'function') {
    window.dispatchEvent(new window.CustomEvent('wc-custom-installments:section-registered', { detail: { name } }));
  }
}

/**
 * Find the renderer registered under a name.
 *
 * @since 6.0.0
 * @param {string} name - Section component name.
 * @return {{mount: Function}|null} The renderer, or null.
 */
export function resolveSection(name) {
  return registry.get(String(name)) || null;
}

/**
 * Publish the registry so a separately built bundle can add to it.
 *
 * A definition an add-on stored on the global before this bundle ran is
 * adopted, so the order the two scripts load in never matters.
 *
 * @since 6.0.0
 * @return {object|null} The global registry, or null outside a browser.
 */
function syncGlobal() {
  if (typeof window === 'undefined') {
    return null;
  }

  const global = window.WCCustomInstallmentsSections || {};

  global.register = registerSection;
  global.resolve = resolveSection;
  global.names = Array.from(registry.keys());
  window.WCCustomInstallmentsSections = global;

  return global;
}

/**
 * Take in early registrations and announce that the registry is ready.
 *
 * @since 6.0.0
 * @return {void}
 */
function announceReady() {
  if (typeof window === 'undefined') {
    return;
  }

  const early = window.WCCustomInstallmentsSections?.pending || {};
  const global = syncGlobal();

  delete global.pending;

  for (const [name, definition] of Object.entries(early)) {
    registerSection(name, definition);
  }

  global.ready = true;

  if (typeof window.CustomEvent === 'function') {
    window.dispatchEvent(new window.CustomEvent('wc-custom-installments:section-registry-ready', { detail: global }));
  }
}

announceReady();
