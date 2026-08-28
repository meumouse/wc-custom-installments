#!/usr/bin/env node

/**
 * WC Custom Installments build pipeline.
 *
 * Orchestrates a production-ready plugin package:
 *   1. Build the Vue frontend (app/ -> app/dist/ via Vite).
 *   2. Install production PHP dependencies (admin/vendor via Composer, --no-dev).
 *   3. Generate / compile translations (languages/ -> .pot, .mo, .l10n.php, .json).
 *   4. Stage only the runtime files into release/wc-custom-installments/.
 *   5. Zip the staging dir into release/wc-custom-installments-<version>.zip.
 *
 * Usage:
 *   node scripts/build.mjs [flags]
 *
 * Flags:
 *   --skip-app            Don't rebuild the Vue frontend (reuse existing app/dist).
 *   --skip-composer       Don't run composer install (reuse existing admin/vendor).
 *   --skip-translations   Don't touch translations (reuse existing artifacts).
 *   --translate           Re-translate .po files via AI before compiling (needs OPENAI_API_KEY).
 *   --engine=<name>       Translation engine for --translate (default: openai).
 *   --no-install          Skip dependency install steps (npm ci / composer install deps).
 *   --no-zip              Stage files but don't create the .zip.
 *   --ship-locales        Also ship the compiled locales (.po/.mo/.l10n.php/.json).
 *                         Off by default: WordPress.org serves translations from
 *                         translate.wordpress.org, and its reviewers ask that the
 *                         package carry only wc-custom-installments.pot — see languageFilter().
 */

import { spawnSync } from 'node:child_process';
import { createWriteStream, existsSync } from 'node:fs';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import archiver from 'archiver';

import { loadEnv } from './env.mjs';
import { resolveVersion } from './version.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const slug = 'wc-custom-installments';

// Translation keys for --translate live in a Git-ignored .env at the repository
// root (see .env.example); the child processes inherit them from here.
loadEnv(root);

const releaseDir = path.join(root, 'release');
const stagingDir = path.join(releaseDir, slug);

/* ------------------------------------------------------------------ flags */

const argv = process.argv.slice(2);
const hasFlag = (name) => argv.includes(name);
const getOpt = (name, fallback) => {
	const prefix = `${name}=`;
	const match = argv.find((arg) => arg.startsWith(prefix));
	return match ? match.slice(prefix.length) : fallback;
};

const opts = {
	skipApp: hasFlag('--skip-app'),
	skipComposer: hasFlag('--skip-composer'),
	skipTranslations: hasFlag('--skip-translations'),
	translate: hasFlag('--translate'),
	engine: getOpt('--engine', 'openai'),
	install: !hasFlag('--no-install'),
	zip: !hasFlag('--no-zip'),
	// WordPress.org distributes translations itself, so the package ships the
	// .pot alone unless a build explicitly asks for the compiled locales.
	potOnly: !hasFlag('--ship-locales'),
};

/* ---------------------------------------------------------------- helpers */

const log = (msg) => console.log(`\x1b[36m▶\x1b[0m ${msg}`);
const ok = (msg) => console.log(`\x1b[32m✓\x1b[0m ${msg}`);
const warn = (msg) => console.log(`\x1b[33m!\x1b[0m ${msg}`);

function run(command, args, cwd) {
	const printable = `${command} ${args.join(' ')}`;
	log(`${printable}  (in ${path.relative(root, cwd) || '.'})`);

	const result = spawnSync(command, args, {
		cwd,
		stdio: 'inherit',
		// shell:true lets Windows resolve npm.cmd / composer.bat from PATH.
		shell: true,
	});

	if (result.status !== 0) {
		throw new Error(`Command failed (exit ${result.status}): ${printable}`);
	}
}

async function copyDir(relSource, relDest = relSource, filter) {
	const source = path.join(root, relSource);
	const dest = path.join(stagingDir, relDest);

	if (!existsSync(source)) {
		return;
	}

	await fs.cp(source, dest, { recursive: true, filter });
}

async function copyFile(relSource, relDest = relSource) {
	const source = path.join(root, relSource);

	if (!existsSync(source)) {
		return;
	}

	const dest = path.join(stagingDir, relDest);
	await fs.mkdir(path.dirname(dest), { recursive: true });
	await fs.copyFile(source, dest);
}

function zipDirectory(sourceDir, outPath) {
	return new Promise((resolve, reject) => {
		const output = createWriteStream(outPath);
		const archive = archiver('zip', { zlib: { level: 9 } });

		output.on('close', () => resolve(archive.pointer()));
		archive.on('warning', (err) => (err.code === 'ENOENT' ? null : reject(err)));
		archive.on('error', reject);

		archive.pipe(output);
		// Nest everything under the plugin slug folder (WordPress expects this).
		archive.directory(sourceDir, slug);
		archive.finalize();
	});
}

/* ------------------------------------------------------------- copy rules */

// Skip dev clutter that may sneak into otherwise-shipped directories.
const denyList = new Set(['node_modules', '.git', '.env', '.DS_Store', 'Thumbs.db', '.gitignore', '.gitattributes']);

const baseFilter = (src) => !denyList.has(path.basename(src));

// languages/: ship only compiled artifacts, never the Node tooling.
const languageExtensions = new Set(['.po', '.mo', '.pot', '.json', '.php']);
const languageFilter = (src) => {
	const name = path.basename(src);

	if (denyList.has(name) || name.startsWith('package')) {
		return false;
	}

	// Always allow directory entries so their children get evaluated.
	if (!path.extname(name)) {
		return true;
	}

	// Only wc-custom-installments.pot ships. WordPress.org generates and delivers every locale
	// from translate.wordpress.org, and bundling compiled catalogues duplicates
	// that channel — the plugin review team asks for them to be left out. Pass
	// --ship-locales to build a package that carries them, which is what installs
	// outside the directory need, since they get no language packs.
	if (opts.potOnly && path.extname(name) !== '.pot') {
		return false;
	}

	// Keep .l10n.php and friends; drop the *-cli.js pipeline scripts.
	return languageExtensions.has(path.extname(name));
};

/* ----------------------------------------------------------------- stages */

function buildFrontend() {
	if (opts.skipApp) {
		log('Skipping frontend build (--skip-app).');
		return;
	}

	const appDir = path.join(root, 'app');

	if (opts.install) {
		run('npm', ['ci'], appDir);
	}

	run('npm', ['run', 'build'], appDir);
	ok('Frontend built (app/dist).');
}

function installPhpDependencies() {
	if (opts.skipComposer) {
		log('Skipping Composer install (--skip-composer).');
		return;
	}

	run(
		'composer',
		['install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress'],
		path.join(root, 'admin'),
	);
	ok('Production PHP dependencies installed (admin/vendor).');
}

function buildTranslations() {
	if (opts.skipTranslations) {
		log('Skipping translations (--skip-translations).');
		return;
	}

	const langDir = path.join(root, 'languages');

	// The languages pipeline is its own Node project. Until it exists, a build
	// still has to produce an installable package.
	if (!existsSync(path.join(langDir, 'package.json'))) {
		warn('No languages/package.json yet — skipping translation build.');
		return;
	}

	if (opts.install) {
		run('npm', ['ci'], langDir);
	}

	// Refresh the template from current source.
	run('npm', ['run', 'pot'], langDir);

	if (opts.translate) {
		// Re-fill every .po via the chosen engine (needs API keys in languages/.env).
		// translate:ai => OpenAI; translate => Google.
		const script = opts.engine === 'openai' ? 'translate:ai' : 'translate';
		run('npm', ['run', script], langDir);
	}

	// Compile .po -> .mo and .l10n.php so WordPress can load them at runtime,
	// plus the per-handle .json catalogues wp.i18n reads on the JS side. The
	// JSONs used to be written by translate-cli.js alone, so a build without
	// --translate shipped whatever they happened to contain last.
	run('npm', ['run', 'compile:mo'], langDir);
	run('npm', ['run', 'compile:php'], langDir);
	run('npm', ['run', 'compile:json'], langDir);
	ok('Translations compiled (languages/*.mo, *.l10n.php, *.json).');
}

async function stageFiles() {
	log('Staging runtime files...');

	await fs.rm(releaseDir, { recursive: true, force: true });
	await fs.mkdir(stagingDir, { recursive: true });

	// readme.txt and LICENSE are what the WordPress.org review reads, so a
	// package missing either is not shippable — fail loudly instead of quietly
	// producing a ZIP that will be rejected.
	for (const file of ['readme.txt', 'LICENSE']) {
		if (!existsSync(path.join(root, file))) {
			throw new Error(`Required file "${file}" is missing; WordPress.org will reject this package.`);
		}
	}

	// Top-level files.
	for (const file of [`${slug}.php`, 'readme.txt', 'LICENSE', 'README.md', 'CHANGELOG.md']) {
		await copyFile(file);
	}

	// PHP backend: source + production autoloader. composer.json stays for reference.
	await copyDir('admin/src', 'admin/src', baseFilter);
	await copyDir('admin/vendor', 'admin/vendor', baseFilter);
	await copyFile('admin/composer.json');

	// Built frontend (includes .vite/manifest.json that Scripts.php reads).
	await copyDir('app/dist', 'app/dist', baseFilter);

	// Frontend source. WordPress.org guideline 4 requires the human-readable
	// source of anything compiled or minified to ship with the plugin (or be
	// publicly linked); app/dist/*/app.js is minified, so its source travels
	// with it, along with everything needed to rebuild it.
	await copyDir('app/src', 'app/src', baseFilter);

	for (const file of [
		'app/package.json',
		'app/package-lock.json',
		'app/vite.config.js',
		'app/tailwind.config.js',
		'app/postcss.config.js',
		'app/tsconfig.json',
	]) {
		await copyFile(file);
	}

	// Static assets.
	await copyDir('assets', 'assets', baseFilter);

	// PHP templates read at runtime through JOINOTIFY_DIR (the OTP login
	// partials). Templates::render() returns silently when a file is missing,
	// so leaving these out produced a package where the OTP screens rendered
	// nothing at all, with no error to point at the cause.
	await copyDir('templates', 'templates', baseFilter);

	// Compiled translation artifacts only.
	await copyDir('languages', 'languages', languageFilter);

	ok(`Staged at ${path.relative(root, stagingDir)}.`);
}

async function packageZip(version) {
	const manifest = {
		name: slug,
		version,
		generatedAt: new Date().toISOString(),
		zipFile: `${slug}-${version}.zip`,
	};

	await fs.writeFile(
		path.join(releaseDir, 'manifest.json'),
		`${JSON.stringify(manifest, null, 2)}\n`,
	);

	if (!opts.zip) {
		log('Skipping zip (--no-zip).');
		return;
	}

	const zipPath = path.join(releaseDir, manifest.zipFile);
	const bytes = await zipDirectory(stagingDir, zipPath);
	const megabytes = bytes / 1024 / 1024;
	ok(`ZIP created: ${path.relative(root, zipPath)} (${megabytes.toFixed(2)} MB)`);

	// The "add your plugin" form rejects uploads above 10 MB outright. Updates go
	// through SVN and aren't capped, but a package this close to the limit is a
	// sign something unwanted is being staged.
	if (megabytes > 10) {
		warn(`Over the 10 MB WordPress.org submission limit. Trim the package before submitting.`);
	} else if (megabytes > 8) {
		warn(`Close to the 10 MB WordPress.org submission limit.`);
	}
}

/* -------------------------------------------------------------------- main */

async function main() {
	const version = await resolveVersion(root, slug, warn);
	console.log(`\n\x1b[1mBuilding ${slug} v${version}\x1b[0m\n`);

	if (!opts.potOnly) {
		log('Translations: shipping the compiled locales alongside wc-custom-installments.pot.');
	}

	buildFrontend();
	installPhpDependencies();
	buildTranslations();
	await stageFiles();
	await packageZip(version);

	console.log(`\n\x1b[32m\x1b[1mBuild complete.\x1b[0m\n`);
}

main().catch((err) => {
	console.error(`\n\x1b[31m✗ Build failed:\x1b[0m ${err.message}\n`);
	process.exit(1);
});
