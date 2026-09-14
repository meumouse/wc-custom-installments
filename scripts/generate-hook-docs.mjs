#!/usr/bin/env node

/**
 * Build the hook reference in DEVELOPERS.md from the source.
 *
 * A hand-written hook table is wrong the first time somebody adds a hook and
 * forgets the docs — and a wrong hook table is worse than none, because a
 * developer trusts it and writes a listener that never fires. This reads the
 * hooks out of the code, resolves each one's 5.x alias through the same rule
 * the runtime uses, and rewrites the table between two markers.
 *
 * Usage:
 *   node scripts/generate-hook-docs.mjs [--check]
 *
 * `--check` rewrites nothing and exits non-zero when the file is out of date,
 * which is what a release should run.
 */

import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const START = '<!-- hooks:start -->';
const END = '<!-- hooks:end -->';

const PREFIX = 'WC_Custom_Installments/';
const LEGACY_PREFIX = 'Woo_Custom_Installments/';

// Must match Compatibility\Hook_Aliases::$overrides. A hook whose class moved
// keeps the name a third party wrote down, not the one its new file implies.
const OVERRIDES = {
  'WC_Custom_Installments/Core/Before_Init': 'Woo_Custom_Installments/Before_Init',
  'WC_Custom_Installments/Core/Init': 'Woo_Custom_Installments/Init',
  'WC_Custom_Installments/Admin/Default_Options': 'Woo_Custom_Installments/Admin/Set_Default_Options',
};

/**
 * Every PHP file under a directory.
 *
 * @param {string} dir Directory to walk.
 * @returns {Promise<string[]>} Absolute file paths.
 */
async function phpFiles(dir) {
  const found = [];

  for (const entry of await fs.readdir(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);

    if (entry.isDirectory()) {
      if (!['vendor', 'node_modules', '.git'].includes(entry.name)) {
        found.push(...(await phpFiles(full)));
      }

      continue;
    }

    if (entry.name.endsWith('.php')) {
      found.push(full);
    }
  }

  return found;
}

/**
 * The 5.x name of a hook.
 *
 * @param {string} hook Canonical hook name.
 * @returns {string} The alias, or an empty string when there is none.
 */
function legacyName(hook) {
  if (OVERRIDES[hook]) {
    return OVERRIDES[hook];
  }

  return hook.startsWith(PREFIX) ? LEGACY_PREFIX + hook.slice(PREFIX.length) : '';
}

/**
 * Collect every hook the plugin publishes.
 *
 * @param {string[]} files Files to read.
 * @returns {Promise<Map<string, {kind: string, aliased: boolean, file: string}>>} Hooks by name.
 */
async function collectHooks(files) {
  const hooks = new Map();

  // Both the aliased helpers and the plain WordPress functions, because a hook
  // that does not go through Hook_Aliases is still a hook developers can use -
  // it simply has no 5.x name, and the table should say so.
  const patterns = [
    { re: /Hook_Aliases::apply_filters\(\s*'([^']+)'/g, kind: 'filter', aliased: true },
    { re: /Hook_Aliases::do_action\(\s*'([^']+)'/g, kind: 'action', aliased: true },
    { re: /(?<!Hook_Aliases::)\bapply_filters\(\s*'(WC_Custom_Installments\/[^']+)'/g, kind: 'filter', aliased: false },
    { re: /(?<!Hook_Aliases::)\bdo_action\(\s*'(WC_Custom_Installments\/[^']+)'/g, kind: 'action', aliased: false },
  ];

  for (const file of files) {
    const source = await fs.readFile(file, 'utf8');
    const relative = path.relative(root, file).replace(/\\/g, '/');

    for (const { re, kind, aliased } of patterns) {
      for (const match of source.matchAll(re)) {
        const name = match[1];

        // A dynamic name is built at the call site; documenting the fragment
        // would send developers after a hook that does not exist.
        if (!name.startsWith(PREFIX)) {
          continue;
        }

        const existing = hooks.get(name);

        hooks.set(name, {
          kind,
          aliased: aliased || existing?.aliased || false,
          file: existing?.file ?? relative,
        });
      }
    }
  }

  return hooks;
}

/**
 * Render the table.
 *
 * @param {Map<string, object>} hooks Hooks by name.
 * @returns {string} Markdown.
 */
function renderTable(hooks) {
  const rows = [...hooks.entries()].sort(([a], [b]) => a.localeCompare(b));

  const lines = [
    `_${rows.length} hooks, generated from the source by \`npm run docs:hooks\`. Do not edit by hand._`,
    '',
    '| Hook | Kind | 5.x name | Declared in |',
    '|---|---|---|---|',
  ];

  for (const [name, meta] of rows) {
    const legacy = meta.aliased ? legacyName(name) : '';

    lines.push(
      `| \`${name}\` | ${meta.kind} | ${legacy ? `\`${legacy}\`` : '—'} | \`${meta.file}\` |`,
    );
  }

  return lines.join('\n');
}

async function main() {
  const check = process.argv.includes('--check');
  const files = await phpFiles(path.join(root, 'admin/src'));

  files.push(path.join(root, 'templates/single-product/price.php'));

  const hooks = await collectHooks(files);
  const table = renderTable(hooks);

  const target = path.join(root, 'DEVELOPERS.md');
  const document = await fs.readFile(target, 'utf8');

  const start = document.indexOf(START);
  const end = document.indexOf(END);

  if (start === -1 || end === -1) {
    throw new Error(`DEVELOPERS.md is missing the ${START} / ${END} markers.`);
  }

  const updated = `${document.slice(0, start + START.length)}\n\n${table}\n\n${document.slice(end)}`;

  if (check) {
    if (updated !== document) {
      console.error('DEVELOPERS.md is out of date. Run: npm run docs:hooks');
      process.exit(1);
    }

    console.log(`Hook reference is up to date (${hooks.size} hooks).`);

    return;
  }

  await fs.writeFile(target, updated);
  console.log(`Wrote ${hooks.size} hooks into DEVELOPERS.md.`);
}

main().catch((error) => {
  console.error(`\x1b[31m✗\x1b[0m ${error.message}`);
  process.exit(1);
});
