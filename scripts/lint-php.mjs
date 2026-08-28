/**
 * lint-php.mjs — parse every source PHP file with a PHP 8.1 binary.
 *
 * The plugin declares `Requires PHP: 8.1`, but development happens on newer PHP, so
 * nothing stops a `match` expression or a nullsafe operator from slipping in and
 * fataling on the oldest supported host. `php -l` under a real 8.1 binary is the
 * only honest check: it catches every syntax-level regression at once.
 *
 * It does not catch functions newer than the floor, which parse fine everywhere
 * — see AGENTS.md §4 for the list to avoid.
 *
 * Usage:
 *   npm run lint:php
 *   PHP81_BIN="C:\\path\\to\\php.exe" npm run lint:php
 *
 * PHP81_BIN can also live in the repository-root `.env` (see `.env.example`).
 *
 * Resolution order for the binary: --php=<path>, $PHP81_BIN, then a few common
 * Local by Flywheel / XAMPP locations, then `php8.1` on PATH.
 */

import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync, statSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { loadEnv } from './env.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

// PHP81_BIN is usually a per-machine path, so it belongs in .env rather than in
// everyone's shell profile — see .env.example.
loadEnv(root);

// Directories that hold code we ship or run, minus everything vendored,
// generated or kept around as editor history.
const scanDirs = ['admin/src', 'templates', 'tests', 'examples'];
const scanFiles = ['wc-custom-installments.php'];
const skipDirs = new Set(['node_modules', 'vendor', 'dist', '.git', '.history', 'release', '.wporg-svn']);

function findPhp() {
	const fromFlag = process.argv.find((arg) => arg.startsWith('--php='));

	const candidates = [
		fromFlag ? fromFlag.slice('--php='.length) : null,
		process.env.PHP81_BIN,
		path.join(
			os.homedir(),
			'AppData/Roaming/Local/lightning-services/php-8.1.23+2/bin/win64/php.exe',
		),
		'C:/xampp81/php/php.exe',
		'/usr/bin/php8.1',
	].filter(Boolean);

	for (const candidate of candidates) {
		if (existsSync(candidate)) {
			return candidate;
		}
	}

	// Last resort: whatever `php8.1` resolves to on PATH.
	return 'php8.1';
}

function collect(dir, out) {
	for (const entry of readdirSync(dir, { withFileTypes: true })) {
		if (entry.isDirectory()) {
			if (!skipDirs.has(entry.name)) {
				collect(path.join(dir, entry.name), out);
			}

			continue;
		}

		if (entry.name.endsWith('.php')) {
			out.push(path.join(dir, entry.name));
		}
	}

	return out;
}

const php = findPhp();
const version = spawnSync(php, ['-n', '-r', 'echo PHP_VERSION;'], { encoding: 'utf8' });

if (version.status !== 0) {
	console.error(
		`Could not run a PHP 8.1 binary ("${php}").\n` +
			'Point at one with PHP81_BIN=... or --php=<path>.',
	);
	process.exit(1);
}

if (!version.stdout.startsWith('8.1')) {
	console.error(
		`"${php}" is PHP ${version.stdout.trim()}, not 8.1. Linting against a newer ` +
			'runtime would accept syntax the oldest supported host rejects.\n' +
			'Point at an 8.1 binary with PHP81_BIN=... or --php=<path>.',
	);
	process.exit(1);
}

const files = [];

for (const rel of scanDirs) {
	const dir = path.join(root, rel);

	if (existsSync(dir) && statSync(dir).isDirectory()) {
		collect(dir, files);
	}
}

for (const rel of scanFiles) {
	const file = path.join(root, rel);

	if (existsSync(file)) {
		files.push(file);
	}
}

let failed = 0;

for (const file of files) {
	// -n ignores php.ini so the check does not depend on the local extension set.
	const result = spawnSync(php, ['-n', '-l', file], { encoding: 'utf8' });

	if (result.status !== 0) {
		failed += 1;
		console.error(`${path.relative(root, file)}\n${(result.stdout + result.stderr).trim()}\n`);
	}
}

if (failed > 0) {
	console.error(`PHP 8.1 lint failed: ${failed} of ${files.length} file(s) do not parse.`);
	process.exit(1);
}

console.log(`PHP ${version.stdout.trim()} lint passed: ${files.length} file(s).`);
