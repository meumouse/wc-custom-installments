/**
 * lint-ui.mjs — the admin UI is made of components, never hand-written HTML.
 *
 * AGENTS.md §7: a native control (`button`, `input`, `select`, `textarea`,
 * `label`, `a`, `form`, `dialog`) may appear only inside
 * `app/src/components/base/`, the one place that wraps them. Everywhere else
 * uses the `Base*` component. `v-html` and `innerHTML` are refused outright:
 * they are markup that no component owns.
 *
 * The storefront runtime (`app/src/storefront/`) is out of scope. It runs on
 * customer themes with no framework, and its markup is the §5 contract.
 *
 * Usage:
 *   npm run lint:ui
 *
 * Also imported by scripts/build.mjs, which refuses to package a violation.
 */

import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const CONTROL_TAGS = ['button', 'input', 'select', 'textarea', 'label', 'a', 'form', 'dialog'];
const TAG_PATTERN = new RegExp(`<(${CONTROL_TAGS.join('|')})(?=[\\s/>])`, 'g');
const RAW_HTML_PATTERN = /\bv-html\b|\.innerHTML\b|\.outerHTML\b|insertAdjacentHTML/g;

// Relative to app/src. `components/base` wraps the native controls; the
// storefront has no framework by design.
const EXEMPT = ['components/base/', 'storefront/'];

/**
 * List the source files under a directory.
 *
 * @param {string} dir - Directory to walk.
 * @param {string[]} out - Accumulator.
 * @return {string[]} Absolute file paths.
 */
function collect(dir, out = []) {
	for (const entry of readdirSync(dir, { withFileTypes: true })) {
		const full = path.join(dir, entry.name);

		if (entry.isDirectory()) {
			collect(full, out);
		} else if (/\.(vue|js|ts)$/.test(entry.name)) {
			out.push(full);
		}
	}

	return out;
}

/**
 * Line number of an offset.
 *
 * @param {string} source - File contents.
 * @param {number} index - Offset.
 * @return {number} 1-based line.
 */
function lineOf(source, index) {
	return source.slice(0, index).split('\n').length;
}

/**
 * Blank out a range while keeping its line breaks, so offsets still map to lines.
 *
 * @param {string} text - Text to blank.
 * @return {string} Same length, only newlines kept.
 */
function blank(text) {
	return text.replace(/[^\n]/g, ' ');
}

/**
 * Find the violations in one file.
 *
 * @param {string} file - Absolute path.
 * @return {{line: number, message: string}[]} Violations.
 */
function inspect(file) {
	const source = readFileSync(file, 'utf8').replace(/\r\n/g, '\n');
	const problems = [];

	// Comments may talk about <button> and innerHTML freely; only code counts.
	const code = source
		.replace(/<!--[\s\S]*?-->/g, blank)
		.replace(/\/\*[\s\S]*?\*\//g, blank)
		.replace(/(^|[^:'"`])\/\/[^\n]*/g, (match, lead) => lead + blank(match.slice(lead.length)));

	for (const match of code.matchAll(RAW_HTML_PATTERN)) {
		problems.push({ line: lineOf(code, match.index), message: `raw HTML through "${match[0]}"` });
	}

	if (file.endsWith('.vue')) {
		const start = code.indexOf('<template>');
		const end = code.lastIndexOf('</template>');

		if (start !== -1 && end > start) {
			const template = code.slice(start, end);

			for (const match of template.matchAll(TAG_PATTERN)) {
				problems.push({
					line: lineOf(code, start + match.index),
					message: `native <${match[1]}> — use the Base* component`,
				});
			}
		}
	}

	return problems;
}

/**
 * Lint the admin UI.
 *
 * @param {string} root - Plugin root.
 * @return {boolean} Whether the UI is clean.
 */
export function lintUi(root) {
	const srcDir = path.join(root, 'app', 'src');
	const files = collect(srcDir).filter((file) => {
		const relative = path.relative(srcDir, file).replace(/\\/g, '/');

		return !EXEMPT.some((prefix) => relative.startsWith(prefix));
	});

	let count = 0;

	for (const file of files) {
		for (const problem of inspect(file)) {
			count += 1;
			console.error(`${path.relative(root, file).replace(/\\/g, '/')}:${problem.line}  ${problem.message}`);
		}
	}

	if (count > 0) {
		console.error(`\nUI lint failed: ${count} problem(s). Native controls belong in app/src/components/base (AGENTS.md §7).`);

		return false;
	}

	console.log(`UI lint passed: ${files.length} file(s), no native controls outside components/base.`);

	return true;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

	process.exit(lintUi(root) ? 0 : 1);
}
