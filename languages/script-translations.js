import fs from "fs";
import path from "path";

import { collectScriptBundles } from "./js-module-graph.js";

/**
 * Write one `wp.i18n` JSON per script handle, scoped to that handle's strings.
 *
 * Every handle used to receive an identical copy of the whole catalogue: ~172 KB
 * and 1740 strings each, of which the majority are PHP-only and unreachable from
 * any bundle. This mirrors what `wp i18n make-json` does instead — resolve each
 * string back to the source files listed in its `#:` reference comments, and ship
 * it only with the handles whose bundle actually contains one of those files.
 */

const DEFAULT_PLURAL_FORMS = "nplurals=2; plural=(n != 1);";

// gettext's separator between a message context and its msgid, and the key
// format `wp.i18n` expects for context-qualified strings.
const CONTEXT_GLUE = "\u0004";

function getPluralForms(poData) {
  const headers = poData.headers || {};
  const key = Object.keys(headers).find(
    (name) => name.toLowerCase() === "plural-forms"
  );

  return (key && headers[key]) || DEFAULT_PLURAL_FORMS;
}

/**
 * Read the source files a PO entry was extracted from.
 *
 * References are stored as "path/to/file.vue:120", one per line or separated by
 * spaces depending on the writer, and the line number is irrelevant here.
 */
function getReferencedFiles(entry) {
  const reference = entry.comments && entry.comments.reference;

  if (!reference) {
    return [];
  }

  return reference
    .split(/\s+/)
    .filter(Boolean)
    .map((value) => value.replace(/:\d+$/, ""));
}

function toCatalogKey(context, msgid) {
  return context ? `${context}${CONTEXT_GLUE}${msgid}` : msgid;
}

function createCatalog(textDomain, locale, pluralForms) {
  return {
    domain: textDomain,
    locale_data: {
      [textDomain]: {
        "": {
          domain: textDomain,
          lang: locale,
          "plural-forms": pluralForms,
        },
      },
    },
  };
}

/**
 * Index every entry of a catalogue by "contextmsgid" -> source files.
 *
 * The .po files are only rewritten by a translation run, so their `#:` comments
 * lag behind the source whenever strings moved since the last one. `npm run pot`
 * refreshes the .pot on every build, which makes it the better reference index.
 *
 * @param {object} potData Parsed POT data (gettext-parser).
 * @return {Map<string, string[]>}
 */
export function indexReferences(potData) {
  const index = new Map();

  for (const [context, group] of Object.entries(potData.translations)) {
    for (const [msgid, entry] of Object.entries(group)) {
      if (msgid === "") {
        continue;
      }

      index.set(toCatalogKey(context, msgid), getReferencedFiles(entry));
    }
  }

  return index;
}

/**
 * Build the per-handle catalogues for one locale.
 *
 * @param {object} poData Parsed PO data (gettext-parser).
 * @param {object} options
 * @param {string} options.pluginRoot Absolute path to the plugin root.
 * @param {string} options.textDomain Plugin text domain.
 * @param {string} options.locale Locale code, e.g. "pt_BR".
 * @param {Map<string, string[]>} [options.references] Source references keyed by
 *   catalogue key. Defaults to the references carried by `poData` itself.
 * @return {{ catalogs: Array<{handle: string, catalog: object, count: number}>, skipped: number }}
 */
export function buildScriptCatalogs(poData, { pluginRoot, textDomain, locale, references }) {
  const pluralForms = getPluralForms(poData);
  const bundles = collectScriptBundles(pluginRoot).map((bundle) => ({
    ...bundle,
    catalog: createCatalog(textDomain, locale, pluralForms),
    count: 0,
  }));

  // Strings extracted from JS/Vue sources that no entry imports: dead modules
  // left in app/src. They ship in the .mo/.l10n.php but belong to no bundle.
  let skipped = 0;

  for (const [context, group] of Object.entries(poData.translations)) {
    for (const [msgid, entry] of Object.entries(group)) {
      if (msgid === "") {
        continue;
      }

      if (!entry.msgstr || !entry.msgstr[0]) {
        continue;
      }

      const key = toCatalogKey(context, msgid);
      const files = references ? references.get(key) || [] : getReferencedFiles(entry);

      if (files.length === 0) {
        continue;
      }

      let matched = false;

      for (const bundle of bundles) {
        if (!files.some((file) => bundle.modules.has(file))) {
          continue;
        }

        bundle.catalog.locale_data[textDomain][key] = entry.msgstr;
        bundle.count++;
        matched = true;
      }

      if (!matched && files.some((file) => !file.endsWith(".php"))) {
        skipped++;
      }
    }
  }

  return {
    catalogs: bundles.map(({ handle, catalog, count }) => ({ handle, catalog, count })),
    skipped,
  };
}

/**
 * Write the per-handle JSON files for one locale.
 *
 * @param {object} poData Parsed PO data (gettext-parser).
 * @param {object} options
 * @param {string} options.pluginRoot Absolute path to the plugin root.
 * @param {string} options.outputDir Directory the JSON files are written to.
 * @param {string} options.textDomain Plugin text domain.
 * @param {string} options.locale Locale code, e.g. "pt_BR".
 * @param {Map<string, string[]>} [options.references] Source references keyed by
 *   catalogue key. Defaults to the references carried by `poData` itself.
 * @return {string[]} Absolute paths of the files written.
 */
export function writeScriptTranslations(
  poData,
  { pluginRoot, outputDir, textDomain, locale, references }
) {
  const { catalogs, skipped } = buildScriptCatalogs(poData, {
    pluginRoot,
    textDomain,
    locale,
    references,
  });

  const written = [];

  for (const { handle, catalog, count } of catalogs) {
    const outputPath = path.join(outputDir, `${textDomain}-${locale}-${handle}.json`);

    fs.writeFileSync(outputPath, JSON.stringify(catalog, null, 2));
    console.log(`   Written: ${path.basename(outputPath)} (${count} strings)`);
    written.push(outputPath);
  }

  if (skipped > 0) {
    console.log(
      `   Note: ${skipped} JS string(s) belong to modules no entry imports; not shipped.`
    );
  }

  return written;
}
