#!/usr/bin/env node

/**
 * Publish a built package to the WordPress.org plugin directory, over SVN.
 *
 * WordPress.org serves whatever `trunk/readme.txt` names in `Stable tag`, from
 * the tag of that name. So a release is two writes that have to agree: trunk
 * gets the new files, `tags/<version>` gets a copy of them. Both go in one
 * commit, because a directory that has the new stable tag declared and not yet
 * created serves nothing.
 *
 * What is pushed is the staged tree `npm run build` leaves in
 * `release/<slug>/` — never the working directory, which carries node_modules,
 * the dev `.env` and whatever is half-finished. Build first, deploy second.
 *
 * Nothing leaves the machine without `--commit`. The default run does every
 * local step and prints what would be committed, which is the only way to see
 * the change before the directory does.
 *
 * Usage:
 *   npm run deploy            # dry run: sync, report, commit nothing
 *   npm run deploy:commit     # the same, then commit
 *
 * Flags:
 *   --commit        Commit trunk, the tag and the assets.
 *   --force         Re-tag a version that already has a tag (see below).
 *   --skip-assets   Leave the directory listing's banner and icon alone.
 *   --dir=<path>    Where to keep the SVN working copy (default .wporg-svn).
 *
 * Reads WPORG_USERNAME, WPORG_PASSWORD and WPORG_SLUG from a Git-ignored .env
 * at the repository root. Leave WPORG_PASSWORD empty to let svn prompt once and
 * cache the credential itself, which keeps it out of the process list.
 */

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { loadEnv } from './env.mjs';
import { resolveVersion } from './version.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

loadEnv(root);

const slug = process.env.WPORG_SLUG || 'wc-custom-installments';
const repoUrl = `https://plugins.svn.wordpress.org/${slug}`;

/* ------------------------------------------------------------------ flags */

const argv = process.argv.slice(2);
const hasFlag = (name) => argv.includes(name);
const getOpt = (name, fallback) => {
	const prefix = `${name}=`;
	const match = argv.find((arg) => arg.startsWith(prefix));
	return match ? match.slice(prefix.length) : fallback;
};

const opts = {
	commit: hasFlag('--commit'),
	force: hasFlag('--force'),
	assets: !hasFlag('--skip-assets'),
	workingCopy: path.resolve(root, getOpt('--dir', '.wporg-svn')),
};

const stagingDir = path.join(root, 'release', slug);
const assetsDir = path.join(root, '.wordpress-org');

/* ---------------------------------------------------------------- helpers */

const log = (msg) => console.log(`\x1b[36m▶\x1b[0m ${msg}`);
const ok = (msg) => console.log(`\x1b[32m✓\x1b[0m ${msg}`);
const warn = (msg) => console.log(`\x1b[33m!\x1b[0m ${msg}`);

/**
 * Run svn, returning its output.
 *
 * @param {string[]} args Arguments.
 * @param {object} [options] `cwd`, and `quiet` to skip the echo.
 * @returns {string} Everything it wrote to stdout.
 */
function svn(args, options = {}) {
	const { cwd = opts.workingCopy, quiet = false } = options;

	if (!quiet) {
		log(`svn ${args.join(' ')}`);
	}

	const result = spawnSync('svn', args, { cwd, encoding: 'utf8' });

	if (result.error || result.status !== 0) {
		const detail = `${result.stdout ?? ''}${result.stderr ?? ''}`.trim();
		throw new Error(`svn ${args.join(' ')} failed:\n${detail || result.error?.message}`);
	}

	return result.stdout ?? '';
}

/**
 * The credentials svn needs, when the .env supplies a password.
 *
 * With no password svn is left to prompt and cache the credential itself, which
 * is both safer and what a person running this by hand expects.
 *
 * @returns {string[]} Arguments to append to a committing command.
 */
function credentials() {
	const username = process.env.WPORG_USERNAME;
	const password = process.env.WPORG_PASSWORD;

	if (!username) {
		return [];
	}

	if (!password) {
		return ['--username', username];
	}

	// On the command line the password is visible to anything that can read the
	// process list. Unattended runs need it; say so rather than hiding it.
	warn('Using WPORG_PASSWORD from the environment; it is visible in the process list.');

	return ['--username', username, '--password', password, '--non-interactive'];
}

/**
 * Every file under a directory, as paths relative to it.
 *
 * @param {string} dir Directory to walk.
 * @param {Set<string>} skip Directory names not to descend into.
 * @returns {Promise<string[]>} Relative file paths, with forward slashes.
 */
async function listFiles(dir, skip = new Set()) {
	const found = [];

	/**
	 * @param {string} current Absolute directory.
	 * @param {string} prefix Path so far.
	 * @returns {Promise<void>}
	 */
	async function walk(current, prefix) {
		for (const entry of await fs.readdir(current, { withFileTypes: true })) {
			if (entry.isDirectory()) {
				if (!skip.has(entry.name)) {
					await walk(path.join(current, entry.name), `${prefix}${entry.name}/`);
				}

				continue;
			}

			found.push(`${prefix}${entry.name}`);
		}
	}

	await walk(dir, '');

	return found;
}

/**
 * Make a working-copy directory hold exactly what a source directory holds.
 *
 * Everything but `.svn` is cleared and the source copied in. A versioned file
 * that comes back with the same bytes is not a modification as far as svn is
 * concerned, so an unchanged release commits nothing — which is what makes the
 * dry run's report trustworthy.
 *
 * @param {string} source Directory to publish.
 * @param {string} dest Directory inside the working copy.
 * @returns {Promise<void>}
 */
async function mirror(source, dest) {
	await fs.mkdir(dest, { recursive: true });

	for (const entry of await fs.readdir(dest)) {
		if (entry !== '.svn') {
			await fs.rm(path.join(dest, entry), { recursive: true, force: true });
		}
	}

	await fs.cp(source, dest, { recursive: true });
}

/**
 * Tell svn about files that arrived and files that went.
 *
 * @returns {{added: number, removed: number}} How many of each.
 */
function reconcile() {
	const added = [];
	const removed = [];

	for (const line of svn(['status'], { quiet: true }).split(/\r?\n/)) {
		const match = line.match(/^(.)\s+(.+)$/);

		if (!match) {
			continue;
		}

		const [, state, file] = match;

		if (state === '?') {
			added.push(file.trim());
		} else if (state === '!') {
			removed.push(file.trim());
		}
	}

	// In batches: a release can add hundreds of files, and a command line has a
	// length limit on every platform.
	for (const batch of chunk(added, 50)) {
		svn(['add', '--parents', ...batch], { quiet: true });
	}

	for (const batch of chunk(removed, 50)) {
		svn(['delete', ...batch], { quiet: true });
	}

	return { added: added.length, removed: removed.length };
}

/**
 * Split a list into fixed-size batches.
 *
 * @param {string[]} items Items.
 * @param {number} size Batch size.
 * @returns {string[][]} The batches.
 */
function chunk(items, size) {
	const batches = [];

	for (let index = 0; index < items.length; index += size) {
		batches.push(items.slice(index, index + size));
	}

	return batches;
}

/* ------------------------------------------------------------------ steps */

/**
 * Refuse to deploy anything the build did not produce.
 *
 * @param {string} version The release version.
 * @returns {Promise<void>}
 */
async function checkStaging(version) {
	if (!existsSync(stagingDir)) {
		throw new Error(
			`No staged package at ${path.relative(root, stagingDir)}. Run "npm run build" first.`,
		);
	}

	const pluginFile = path.join(stagingDir, `${slug}.php`);
	const readmeFile = path.join(stagingDir, 'readme.txt');

	for (const file of [pluginFile, readmeFile]) {
		if (!existsSync(file)) {
			throw new Error(`The staged package is missing ${path.basename(file)}.`);
		}
	}

	// The staged tree can predate the version bump. Publishing it would tag
	// <version> with the files of the one before it, and every site would
	// install that.
	const staged = (await fs.readFile(pluginFile, 'utf8')).match(/^\s*\*\s*Version:\s*(.+)$/m)?.[1].trim();

	if (staged !== version) {
		throw new Error(
			`The staged package is ${staged}, not ${version}. Run "npm run build" again.`,
		);
	}

	// WordPress.org serves the tag this names, not the one being created here.
	// A mismatch publishes nothing, or publishes the previous release.
	const stable = (await fs.readFile(readmeFile, 'utf8')).match(/^Stable tag:\s*(.+)$/m)?.[1].trim();

	if (stable !== version) {
		throw new Error(`The staged readme.txt has "Stable tag: ${stable}", not ${version}.`);
	}

	ok(`Staged package is ${version}, with a matching stable tag.`);
}

/**
 * Check out the directory's repository, or bring the existing copy up to date.
 *
 * Only trunk and assets are fetched in full; tags come in by name alone, which
 * is all this needs and spares a download of every release ever made.
 *
 * @returns {void}
 */
function checkoutOrUpdate() {
	if (existsSync(path.join(opts.workingCopy, '.svn'))) {
		log('Updating the existing working copy…');
		svn(['update', '--quiet']);
	} else {
		log(`Checking out ${repoUrl}…`);

		try {
			svn(['checkout', '--depth', 'immediates', repoUrl, opts.workingCopy], { cwd: root });
		} catch (error) {
			throw new Error(
				`${error.message}\n\n` +
					`If the plugin is not in the directory yet, there is no repository to ` +
					`deploy to: it is created when the review team approves the submission.`,
			);
		}
	}

	svn(['update', '--set-depth', 'infinity', 'trunk'], { quiet: true });
	svn(['update', '--set-depth', 'immediates', 'tags'], { quiet: true });

	if (opts.assets) {
		svn(['update', '--set-depth', 'infinity', 'assets'], { quiet: true });
	}
}

/**
 * Put the staged files in trunk and the assets beside them.
 *
 * @returns {Promise<void>}
 */
async function syncFiles() {
	log('Mirroring the staged package into trunk…');
	await mirror(stagingDir, path.join(opts.workingCopy, 'trunk'));

	if (!opts.assets) {
		log('Leaving the assets alone (--skip-assets).');

		return;
	}

	// The banner, icon and screenshots the directory listing shows. They live
	// outside the package on purpose: they are not shipped to any site.
	if (!existsSync(assetsDir) || (await listFiles(assetsDir)).length === 0) {
		warn(`No listing assets in ${path.relative(root, assetsDir)}; the listing keeps what it has.`);

		return;
	}

	log('Mirroring the listing assets…');
	await mirror(assetsDir, path.join(opts.workingCopy, 'assets'));
}

/**
 * Whether the directory already serves a tag for this version.
 *
 * The question is about the repository, not the working copy: a dry run copies
 * the tag locally and commits nothing, and a local directory that was never
 * committed says nothing about what sites have installed. `^/` is the root of
 * whatever repository this working copy came from.
 *
 * @param {string} version The release version.
 * @returns {boolean} True when the tag is in the repository.
 */
function tagIsPublished(version) {
	const result = spawnSync('svn', ['ls', '--depth', 'empty', `^/tags/${version}`], {
		cwd: opts.workingCopy,
		encoding: 'utf8',
	});

	return result.status === 0;
}

/**
 * Drop a tag an earlier dry run copied but never committed.
 *
 * A dry run does every local step a real one does, so it leaves the copied tag
 * behind. Reading that back as a published tag would make every rehearsal block
 * the release it was rehearsing.
 *
 * @param {string} version The release version.
 * @returns {Promise<void>}
 */
async function clearStaleTag(version) {
	const tagPath = path.join(opts.workingCopy, 'tags', version);

	if (!existsSync(tagPath) || tagIsPublished(version)) {
		return;
	}

	warn(`Clearing tags/${version}, left behind by an earlier dry run.`);
	svn(['revert', '--recursive', `tags/${version}`], { quiet: true });
	await fs.rm(tagPath, { recursive: true, force: true });
}

/**
 * Create the tag for this release.
 *
 * @param {string} version The release version.
 * @returns {void}
 */
function tag(version) {
	if (tagIsPublished(version)) {
		if (!opts.force) {
			throw new Error(
				`tags/${version} is already published. That tag is what sites have ` +
					`installed; bump the version instead. Pass --force only to repair a ` +
					`tag the directory never served.`,
			);
		}

		warn(`Replacing the published tags/${version} (--force).`);
		svn(['delete', '--force', `tags/${version}`], { quiet: true });
	}

	log(`Copying trunk to tags/${version}…`);
	svn(['copy', 'trunk', `tags/${version}`], { quiet: true });
}

/* -------------------------------------------------------------------- main */

async function main() {
	const version = await resolveVersion(root, slug, warn);

	console.log(`\n\x1b[1mDeploying ${slug} v${version} to WordPress.org\x1b[0m\n`);

	if (spawnSync('svn', ['--version', '--quiet'], { encoding: 'utf8' }).status !== 0) {
		throw new Error('svn is not on PATH. Install a Subversion client and try again.');
	}

	await checkStaging(version);
	checkoutOrUpdate();
	await clearStaleTag(version);
	await syncFiles();

	const { added, removed } = reconcile();

	tag(version);

	// Counted after the tag, so the summary covers the whole commit.
	const pending = svn(['status'], { quiet: true })
		.split(/\r?\n/)
		.filter((line) => line.trim().length > 0);

	console.log(`\n${pending.length} path(s) to commit — ${added} added, ${removed} removed.\n`);

	if (!opts.commit) {
		console.log(svn(['status'], { quiet: true }).trim() || '(nothing to commit)');
		console.log(
			`\n\x1b[33mDry run.\x1b[0m Nothing was committed. The working copy is at ` +
				`${path.relative(root, opts.workingCopy)}; inspect it, then run ` +
				`"npm run deploy:commit".\n`,
		);

		return;
	}

	if (pending.length === 0) {
		warn('Nothing to commit: the directory already has these files.');

		return;
	}

	log(`Committing ${pending.length} path(s)…`);
	svn(['commit', '-m', `Release ${version}`, ...credentials()], { quiet: true });

	ok(`Committed. WordPress.org will serve ${version} once it rebuilds the listing.`);
}

main().catch((err) => {
	console.error(`\n\x1b[31m✗ Deploy failed:\x1b[0m ${err.message}\n`);
	process.exit(1);
});
