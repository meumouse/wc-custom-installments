import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import gettextParser from "gettext-parser";

import { indexReferences, writeScriptTranslations } from "./script-translations.js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const TEXT_DOMAIN = "wc-custom-installments";
const PLUGIN_ROOT = path.resolve(__dirname, "..");
const POT_FILE = path.join(__dirname, `${TEXT_DOMAIN}.pot`);

/**
 * Read the source references from the .pot.
 *
 * A .po is only rewritten by a translation run, so its `#:` comments go stale as
 * soon as a string moves between files. `npm run pot` runs on every build, which
 * makes the .pot the current answer to "which bundle uses this string".
 */
function loadReferences() {
  if (!fs.existsSync(POT_FILE)) {
    console.warn(
      `Warning: ${path.basename(POT_FILE)} not found; falling back to the references in each .po.`
    );

    return undefined;
  }

  return indexReferences(gettextParser.po.parse(fs.readFileSync(POT_FILE)));
}

function getPoFiles() {
  const args = process.argv.slice(2);
  const langFlagIndex = args.findIndex((arg) => arg === "--lang" || arg === "-l");
  const inlineLangArg = args.find((arg) => arg.startsWith("--lang="));

  let selectedLangCode = null;

  if (langFlagIndex !== -1) {
    selectedLangCode = args[langFlagIndex + 1] || null;
  } else if (inlineLangArg) {
    selectedLangCode = inlineLangArg.split("=")[1] || null;
  } else if (args[0] && !args[0].startsWith("-")) {
    selectedLangCode = args[0];
  }

  if (selectedLangCode) {
    return [path.join(__dirname, `${TEXT_DOMAIN}-${selectedLangCode}.po`)];
  }

  return fs
    .readdirSync(__dirname)
    .filter((file) => file.endsWith(".po") && file !== `${TEXT_DOMAIN}.pot`)
    .map((file) => path.join(__dirname, file));
}

function getLocale(poPath) {
  const match = path.basename(poPath).match(/^wc-custom-installments-(.+)\.po$/i);

  return match ? match[1] : null;
}

function compileJsonFiles(poPath, references) {
  if (!fs.existsSync(poPath)) {
    console.error(`PO file not found: ${path.basename(poPath)}`);
    return false;
  }

  const locale = getLocale(poPath);

  if (!locale) {
    console.error(`Could not read a locale from: ${path.basename(poPath)}`);
    return false;
  }

  const poData = gettextParser.po.parse(fs.readFileSync(poPath));

  console.log(`Compiling script translations for ${locale}...`);

  writeScriptTranslations(poData, {
    pluginRoot: PLUGIN_ROOT,
    outputDir: __dirname,
    textDomain: TEXT_DOMAIN,
    locale,
    references,
  });

  return true;
}

function main() {
  const poFiles = getPoFiles();

  if (poFiles.length === 0) {
    console.log("No translation .po files found.");
    process.exit(0);
  }

  const references = loadReferences();
  let hasError = false;

  for (const poFile of poFiles) {
    const ok = compileJsonFiles(poFile, references);

    if (!ok) {
      hasError = true;
    }
  }

  if (hasError) {
    process.exit(1);
  }
}

main();
