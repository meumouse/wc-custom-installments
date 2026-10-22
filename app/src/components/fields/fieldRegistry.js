/**
 * fieldRegistry.js
 *
 * Maps a schema field type to the component that renders it. An add-on shipping
 * its own control registers it here through the global exposed at the bottom,
 * which is the versioned contract between the free bundle and the Pro one.
 *
 * @since 6.0.0
 */
import BaseSwitch from '../base/BaseSwitch.vue';
import BaseInput from '../base/BaseInput.vue';
import BaseSelect from '../base/BaseSelect.vue';
import BaseTextarea from '../base/BaseTextarea.vue';
import BaseColorInput from '../base/BaseColorInput.vue';
import UnitField from './UnitField.vue';
import PlaceholderTextField from './PlaceholderTextField.vue';
import GatewayRatesField from './GatewayRatesField.vue';
import InstallmentFeesField from './InstallmentFeesField.vue';
import ElementDesignField from './ElementDesignField.vue';
import CardBrandsField from './CardBrandsField.vue';
import CheckboxGroupField from './CheckboxGroupField.vue';

const registry = new Map();

/**
 * Normalize a type name into a lookup key.
 *
 * @since 6.0.0
 * @param {string} name - Raw field type or component name.
 * @return {string} Normalized key.
 */
function normalize(name) {
  return String(name || '')
    .trim()
    .toLowerCase()
    .replace(/[\s_-]+/g, '');
}

/**
 * Register a component under a type name.
 *
 * @since 6.0.0
 * @param {string} name - Field type or component name.
 * @param {object} component - Vue component.
 * @return {void}
 */
export function registerFieldComponent(name, component) {
  const key = normalize(name);

  if (!key || !component) {
    return;
  }

  registry.set(key, component);
  syncGlobal();
}

/**
 * Resolve the component for a field definition.
 *
 * A field may name an explicit component, which wins over its type - that is
 * how a Pro control replaces the rendering of a setting the free plugin
 * declared.
 *
 * @since 6.0.0
 * @param {object} field - Field definition from the schema.
 * @return {object|null} The component, or null when nothing matches.
 */
export function resolveFieldComponent(field) {
  if (!field || typeof field !== 'object') {
    return null;
  }

  for (const name of [field.component, field.type]) {
    const component = registry.get(normalize(name));

    if (component) {
      return component;
    }
  }

  return null;
}

/**
 * List the registered type names.
 *
 * @since 6.0.0
 * @return {string[]} Registered keys.
 */
export function getRegisteredFieldTypes() {
  return Array.from(registry.keys());
}

registerFieldComponent('toggle', BaseSwitch);
registerFieldComponent('text', BaseInput);
registerFieldComponent('number', BaseInput);
registerFieldComponent('select', BaseSelect);
registerFieldComponent('textarea', BaseTextarea);
registerFieldComponent('color', BaseColorInput);
registerFieldComponent('unit', UnitField);
registerFieldComponent('placeholder-text', PlaceholderTextField);
registerFieldComponent('gateway-rates', GatewayRatesField);
registerFieldComponent('installment-fees', InstallmentFeesField);
registerFieldComponent('element-design', ElementDesignField);
registerFieldComponent('card-brands', CardBrandsField);
registerFieldComponent('checkbox-group', CheckboxGroupField);

/**
 * Publish the registry so a separately built bundle can add to it.
 *
 * @since 6.0.0
 * @return {object|null} The global registry, or null outside a browser.
 */
function syncGlobal() {
  if (typeof window === 'undefined') {
    return null;
  }

  window.WCCustomInstallmentsFieldComponents = window.WCCustomInstallmentsFieldComponents || {};
  window.WCCustomInstallmentsFieldComponents.register = registerFieldComponent;
  window.WCCustomInstallmentsFieldComponents.resolve = resolveFieldComponent;
  window.WCCustomInstallmentsFieldComponents.types = getRegisteredFieldTypes();

  return window.WCCustomInstallmentsFieldComponents;
}

/**
 * Announce that the registry accepts registrations.
 *
 * The flag matters as much as the event: a bundle that loads after this one
 * would miss the event entirely, and polling for a global is not an API.
 *
 * @since 6.0.0
 * @return {void}
 */
function announceReady() {
  const global = syncGlobal();

  if (!global || typeof window === 'undefined') {
    return;
  }

  global.ready = true;

  if (typeof window.CustomEvent === 'function') {
    window.dispatchEvent(
      new window.CustomEvent('wc-custom-installments:field-registry-ready', { detail: global }),
    );
  }
}

announceReady();
