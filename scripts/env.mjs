/**
 * Load the repository-root `.env` into `process.env`.
 *
 * The build and deploy scripts need a handful of local secrets and machine
 * paths (a WordPress.org login, a PHP 8.1 binary, translation API keys). Keeping
 * them in a Git-ignored `.env` beats exporting them by hand in every shell, and
 * beats passing them as flags that end up in the shell history.
 *
 * Deliberately dependency-free: the root package has one devDependency
 * (archiver) and adding `dotenv` just to read four lines is not worth it. The
 * parser handles what a `.env` actually carries — `KEY=value`, optional
 * `export`, quoted values, `#` comments — and nothing more.
 *
 * A real environment variable always wins over the file, so CI (which injects
 * secrets into the environment) is never overridden by a stray local `.env`.
 */

import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';

/**
 * Parse the contents of a `.env` file.
 *
 * @param {string} contents Raw file contents.
 * @returns {Record<string, string>} Parsed key/value pairs.
 */
export function parseEnv(contents) {
	const values = {};

	for (const rawLine of contents.split(/\r?\n/)) {
		const line = rawLine.trim();

		if (!line || line.startsWith('#')) {
			continue;
		}

		const match = line.match(/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/);

		if (!match) {
			continue;
		}

		const [, key] = match;
		let value = match[2].trim();

		if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
			// Quoted: strip the quotes and honour \n inside double quotes.
			const quote = value[0];
			value = value.slice(1, -1);

			if (quote === '"') {
				value = value.replace(/\\n/g, '\n');
			}
		} else {
			// Unquoted: an inline comment ends the value.
			value = value.replace(/\s+#.*$/, '').trim();
		}

		values[key] = value;
	}

	return values;
}

/**
 * Read `<root>/.env` (when it exists) into `process.env`.
 *
 * @param {string} root Directory holding the `.env` file.
 * @returns {string[]} Names of the variables the file supplied.
 */
export function loadEnv(root) {
	const file = path.join(root, '.env');

	if (!existsSync(file)) {
		return [];
	}

	const applied = [];

	for (const [key, value] of Object.entries(parseEnv(readFileSync(file, 'utf8')))) {
		// Never clobber something the shell or CI already set.
		if (process.env[key] === undefined) {
			process.env[key] = value;
			applied.push(key);
		}
	}

	return applied;
}
