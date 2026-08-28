#!/usr/bin/env node

/**
 * Build docs/free-vs-pro.md from the feature registry.
 *
 * The tier split is a commercial promise, and support answers "is that in the
 * free version?" from this page. Writing it by hand means it is wrong the first
 * time a feature moves tier, and nobody notices until a customer is told the
 * wrong thing. So it is read out of the code that actually decides.
 *
 * Usage:
 *   node scripts/generate-feature-docs.mjs [--check]
 */

import { spawnSync } from 'node:child_process';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { loadEnv } from './env.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

loadEnv(root);

/**
 * Ask PHP for the registered features.
 *
 * The registry is PHP, and reimplementing it in JavaScript to avoid shelling
 * out would recreate exactly the drift this script exists to prevent.
 *
 * @returns {object[]} One entry per feature.
 */
function readFeatures() {
  const php = process.env.PHP81_BIN || 'php';

  // String.raw, because the namespace separators are backslashes and an
  // ordinary literal eats them — leaving PHP hunting for a class called
  // "MeuMouseWC_Custom_InstallmentsCoreFeatures".
  const script = [
    "require 'tests/bootstrap.php';",
    String.raw`echo json_encode( array_values( MeuMouse\WC_Custom_Installments\Core\Features::all() ) );`,
  ].join(' ');

  const result = spawnSync(php, ['-n', '-r', script], { cwd: root, encoding: 'utf8' });

  if (result.status !== 0) {
    throw new Error(`Could not read the feature registry:\n${result.stderr || result.stdout}`);
  }

  return JSON.parse(result.stdout);
}

/**
 * Render the page.
 *
 * @param {object[]} features Registered features.
 * @returns {string} Markdown.
 */
function render(features) {
  const pro = features.filter((feature) => feature.tier === 'pro');

  return `# Free and Pro

_Generated from the feature registry by \`npm run docs:features\`. Do not edit by hand._

Everything not listed below is in the free plugin, on WordPress.org, with no licence and no
account. That includes the whole instalment display, one upfront-payment discount, every payment
badge, all fifteen shortcodes, the theme integrations and the setup wizard.

## What the Pro add-on adds

${pro.length} capabilities. Each one is a gate: the setting is visible in the free plugin, marked
Pro, and cannot be saved or take effect until a licence answers for it.

| Capability | Gate |
|---|---|
${pro.map((feature) => `| ${feature.label} | \`${feature.id}\` |`).join('\n')}

## How the split works

There is one function deciding all of it, \`Core\Features::is_available()\`, and three behaviours
follow from a single \`feature\` key on a settings field:

1. The field reaches the browser marked \`locked\`, so a store owner can see what the feature does
   before buying it.
2. The settings repository refuses to write it. The browser is told the field is locked; the
   browser is not what enforces it.
3. The engine asks before applying the behaviour, so a value stored while licensed stops taking
   effect when the licence lapses.

What an element *ships with* is not gated, only changing it. A lapsed licence returns the Pix badge
to its green pill rather than dropping its background — a storefront should look un-upgraded, not
broken.
`;
}

async function main() {
  const check = process.argv.includes('--check');
  const target = path.join(root, 'docs/free-vs-pro.md');
  const content = render(readFeatures());

  const current = await fs.readFile(target, 'utf8').catch(() => '');

  if (check) {
    if (current !== content) {
      console.error('docs/free-vs-pro.md is out of date. Run: npm run docs:features');
      process.exit(1);
    }

    console.log('Feature documentation is up to date.');

    return;
  }

  await fs.mkdir(path.dirname(target), { recursive: true });
  await fs.writeFile(target, content);

  console.log('Wrote docs/free-vs-pro.md.');
}

main().catch((error) => {
  console.error(`\x1b[31m✗\x1b[0m ${error.message}`);
  process.exit(1);
});
