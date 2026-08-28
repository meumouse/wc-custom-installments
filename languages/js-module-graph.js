import fs from "fs";
import path from "path";

/**
 * Map each Vite entry to the script handle it is enqueued under and to the set
 * of source modules that end up in its bundle.
 *
 * The per-handle translation JSONs must carry only the strings their own bundle
 * can request at runtime, which means knowing which .vue/.ts/.js files each
 * entry pulls in. `app/dist/.vite/manifest.json` cannot answer that: it lists
 * the output chunks an entry loads, but a shared chunk (`_PageHeader-<hash>.js`)
 * names no source module, so the mapping stops at the bundle boundary. Walking
 * the import graph from the entry source recovers it, and works without a build.
 */

const CODE_EXTENSIONS = new Set([".js", ".jsx", ".ts", ".tsx", ".vue"]);

// Vite resolves extensionless and directory imports the way a bundler does, so
// try the same candidates in the same order rather than requiring exact paths.
const RESOLUTION_SUFFIXES = [
  "",
  ".ts",
  ".tsx",
  ".js",
  ".jsx",
  ".vue",
  "/index.ts",
  "/index.js",
  "/index.vue",
];

// `import x from "y"`, `import "y"`, `export * from "y"`, `export { a } from "y"`.
const STATIC_IMPORT_PATTERN =
  /(?:^|[^\w.$])(?:import|export)\s+(?:[\s\S]*?\sfrom\s*)?["']([^"']+)["']/g;

// `import("y")`.
const DYNAMIC_IMPORT_PATTERN = /\bimport\s*\(\s*["']([^"']+)["']\s*\)/g;

// Entries whose handle cannot be read from a `mountPage()` call. The OTP login
// widget is a public-facing bundle that mounts its own Vue apps, and its handle
// lives in PHP (Otp_Login\Frontend_Assets::HANDLE).
const HANDLE_OVERRIDES = {
  "src/entries/otp-login.js": "wc-custom-installments-otp-login",
};

const MOUNT_PAGE_PATTERN = /mountPage\(\s*["']([^"']+)["']/;

/**
 * Normalize an absolute path to the forward-slash, plugin-relative form the
 * .pot writes into its `#:` reference comments.
 */
function toReferencePath(pluginRoot, absolutePath) {
  return path.relative(pluginRoot, absolutePath).replace(/\\/g, "/");
}

function readImportSpecifiers(filePath) {
  const source = fs.readFileSync(filePath, "utf8");
  const specifiers = [];

  for (const pattern of [STATIC_IMPORT_PATTERN, DYNAMIC_IMPORT_PATTERN]) {
    pattern.lastIndex = 0;

    let match;
    while ((match = pattern.exec(source)) !== null) {
      specifiers.push(match[1]);
    }
  }

  return specifiers;
}

/**
 * Resolve a relative import to a file on disk.
 *
 * Bare specifiers (`vue`, `@vueuse/core`) resolve into node_modules, which the
 * .pot never scans, so they are skipped instead of resolved.
 *
 * @return {string|null} Absolute path, or null when the specifier is bare or unresolvable.
 */
function resolveSpecifier(specifier, importerPath) {
  if (!specifier.startsWith(".")) {
    return null;
  }

  // Drop Vite resource queries (`...flags.webp?url`).
  const withoutQuery = specifier.split("?")[0];
  const base = path.resolve(path.dirname(importerPath), withoutQuery);

  for (const suffix of RESOLUTION_SUFFIXES) {
    const candidate = base + suffix;

    if (fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
      return candidate;
    }
  }

  return null;
}

/**
 * Collect every source module reachable from an entry file.
 *
 * @return {{ modules: Set<string>, unresolved: string[] }}
 */
function collectModules(pluginRoot, entryPath) {
  const modules = new Set();
  const unresolved = [];
  const visited = new Set();
  const pending = [entryPath];

  while (pending.length > 0) {
    const filePath = pending.pop();

    if (visited.has(filePath)) {
      continue;
    }

    visited.add(filePath);

    // Stylesheets and images are reachable but carry no translatable strings.
    if (!CODE_EXTENSIONS.has(path.extname(filePath))) {
      continue;
    }

    modules.add(toReferencePath(pluginRoot, filePath));

    for (const specifier of readImportSpecifiers(filePath)) {
      if (!specifier.startsWith(".")) {
        continue;
      }

      const resolved = resolveSpecifier(specifier, filePath);

      if (resolved === null) {
        unresolved.push(`${specifier} (in ${toReferencePath(pluginRoot, filePath)})`);
        continue;
      }

      pending.push(resolved);
    }
  }

  return { modules, unresolved };
}

/**
 * Read the script handle an entry mounts under.
 *
 * Admin pages declare it inline (`mountPage('wc-custom-installments-history-app', …)`), which
 * keeps the pipeline in step with the frontend without a second list to update.
 */
function resolveHandle(entryPath, entryKey) {
  if (HANDLE_OVERRIDES[entryKey]) {
    return HANDLE_OVERRIDES[entryKey];
  }

  const match = fs.readFileSync(entryPath, "utf8").match(MOUNT_PAGE_PATTERN);

  return match ? match[1] : null;
}

/**
 * Map every Vite entry to its script handle and bundled source modules.
 *
 * @param {string} pluginRoot Absolute path to the plugin root.
 * @return {Array<{ handle: string, entry: string, modules: Set<string> }>}
 */
export function collectScriptBundles(pluginRoot) {
  const entriesDir = path.join(pluginRoot, "app", "src", "entries");

  if (!fs.existsSync(entriesDir)) {
    throw new Error(`Vite entries directory not found: ${entriesDir}`);
  }

  const bundles = [];
  const unresolved = [];

  for (const fileName of fs.readdirSync(entriesDir).sort()) {
    const entryPath = path.join(entriesDir, fileName);

    if (!CODE_EXTENSIONS.has(path.extname(fileName))) {
      continue;
    }

    const entryKey = `src/entries/${fileName}`;
    const handle = resolveHandle(entryPath, entryKey);

    // A handle-less entry would silently ship no translations at all, so stop
    // rather than emit a package whose new page renders untranslated.
    if (!handle) {
      throw new Error(
        `Could not resolve a script handle for "${entryKey}". Add a mountPage('<handle>', …) ` +
          `call to the entry, or an entry in HANDLE_OVERRIDES (languages/js-module-graph.js).`
      );
    }

    const collected = collectModules(pluginRoot, entryPath);
    unresolved.push(...collected.unresolved);

    bundles.push({ handle, entry: entryKey, modules: collected.modules });
  }

  if (unresolved.length > 0) {
    console.warn(
      `   Warning: ${unresolved.length} relative import(s) could not be resolved; ` +
        `strings from those modules will be left out:`
    );

    for (const specifier of unresolved) {
      console.warn(`     ${specifier}`);
    }
  }

  return bundles;
}
