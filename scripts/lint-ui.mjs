/**
 * lint-ui.mjs — the admin UI is made of components, never hand-written HTML.
 *
 * AGENTS.md §7: a native control (`button`, `input`, `select`, `textarea`,
 * `label`, `a`, `form`, `dialog`) may appear only inside
 * `app/src/components/base/`, the one place that wraps them. Everywhere else
 * uses the `Base*` component. `v-html` and `innerHTML` are refused outright:
 * they are markup that no component owns.
 *
 * The browser's own widgets are refused everywhere, `components/base` included:
 * the date, time, month, week and colour pickers an `<input type>` opens, and
 * `alert()`, `confirm()` and `prompt()`. They cannot wear the design system and
 * look different in every browser; each has a dedicated component instead
 * (BaseDatePicker, BaseConfirmDialog, ...).
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

// `type="date"`, `type: 'color'`, `:type="'time'"`… and the blocking dialogs.
const NATIVE_PICKER_TYPES = ['date', 'datetime-local', 'time', 'month', 'week', 'color'];
const NATIVE_PICKER_PATTERN = new RegExp(
	`\\btype\\s*[=:]\\s*["'\`]{1,2}(${NATIVE_PICKER_TYPES.join('|')})["'\`]`,
	'g',
);
const NATIVE_DIALOG_PATTERN = /(?:\bwindow\.|(?<![\w.$]))(alert|confirm|prompt)\s*\(/g;

// Relative to app/src. `components/base` wraps the native controls; the
// storefront has no framework by design.
const EXEMPT = ['components/base/', 'storefront/'];

// Native widgets still waiting for their component, with what is allowed:
// `'components/base/BaseThing.vue': ['color']`. Each entry is a debt, not a
// precedent: replace the widget, then remove it. Empty is the goal.
const NATIVE_WIDGET_DEBT = {};

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
 * Strip the comments out of a file, keeping its line breaks.
 *
 * Comments may talk about <button>, innerHTML or type="date" freely; only code
 * counts.
 *
 * @param {string} file - Absolute path.
 * @return {string} The code.
 */
function readCode(file) {
	return readFileSync(file, 'utf8')
		.replace(/\r\n/g, '\n')
		.replace(/<!--[\s\S]*?-->/g, blank)
		.replace(/\/\*[\s\S]*?\*\//g, blank)
		.replace(/(^|[^:'"`])\/\/[^\n]*/g, (match, lead) => lead + blank(match.slice(lead.length)));
}

/**
 * Find the browser widgets a file opens instead of a component.
 *
 * @param {string} file - Absolute path.
 * @param {string} relative - Path relative to app/src.
 * @return {{line: number, message: string}[]} Violations.
 */
function inspectWidgets(file, relative) {
	const code = readCode(file);
	const allowed = NATIVE_WIDGET_DEBT[relative] || [];
	const problems = [];

	for (const match of code.matchAll(NATIVE_PICKER_PATTERN)) {
		if (!allowed.includes(match[1])) {
			problems.push({
				line: lineOf(code, match.index),
				message: `native ${match[1]} picker — use the dedicated component (BaseDatePicker, …)`,
			});
		}
	}

	for (const match of code.matchAll(NATIVE_DIALOG_PATTERN)) {
		problems.push({ line: lineOf(code, match.index), message: `native ${match[1]}() — use BaseConfirmDialog or a toast` });
	}

	return problems;
}

/**
 * Find the violations in one file.
 *
 * @param {string} file - Absolute path.
 * @return {{line: number, message: string}[]} Violations.
 */
function inspect(file) {
	const code = readCode(file);
	const problems = [];

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
	const relativeOf = (file) => path.relative(srcDir, file).replace(/\\/g, '/');
	const all = collect(srcDir).filter((file) => !relativeOf(file).startsWith('storefront/'));
	const files = all.filter((file) => !EXEMPT.some((prefix) => relativeOf(file).startsWith(prefix)));

	let count = 0;

	const report = (file, problem) => {
		count += 1;
		console.error(`${path.relative(root, file).replace(/\\/g, '/')}:${problem.line}  ${problem.message}`);
	};

	for (const file of files) {
		inspect(file).forEach((problem) => report(file, problem));
	}

	for (const file of all) {
		inspectWidgets(file, relativeOf(file)).forEach((problem) => report(file, problem));
	}

	if (count > 0) {
		console.error(
			`\nUI lint failed: ${count} problem(s). Native controls belong in app/src/components/base, and the browser's own pickers and dialogs are not used at all (AGENTS.md §7).`,
		);

		return false;
	}

	console.log(`UI lint passed: ${all.length} file(s), no native controls outside components/base, no native pickers or dialogs.`);

	return true;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

	process.exit(lintUi(root) ? 0 : 1);
}
