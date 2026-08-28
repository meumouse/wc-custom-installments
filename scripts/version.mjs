/**
 * Single source of truth for "what version is this release?".
 *
 * Four files declare the version and all four have to agree. WordPress compares
 * the plugin header to decide an update exists; WordPress.org reads "Stable tag"
 * in readme.txt to decide which SVN tag to serve. When those drift, the directory
 * ships one version while the installed plugin reports another, and the update
 * either never surfaces or surfaces and installs the wrong files.
 *
 * Both the build and the SVN deploy gate on this, so neither can publish a
 * mismatch the other would have caught.
 */

import fs from 'node:fs/promises';
import path from 'node:path';

/**
 * Read the version from every file that declares it.
 *
 * @param {string} root Plugin root directory.
 * @param {string} slug Plugin slug (the main PHP file is <slug>.php).
 * @returns {Promise<Record<string, string|undefined>>} Source label => version.
 */
export async function readVersionSources(root, slug) {
	const pluginFile = await fs.readFile(path.join(root, `${slug}.php`), 'utf8');
	const readmeFile = await fs.readFile(path.join(root, 'readme.txt'), 'utf8');
	const packageFile = await fs.readFile(path.join(root, 'package.json'), 'utf8');

	return {
		[`${slug}.php (header)`]: pluginFile.match(/^\s*\*\s*Version:\s*(.+)$/m)?.[1].trim(),
		[`${slug}.php ($wc_custom_installments_plugin_version)`]: pluginFile.match(/\$wc_custom_installments_plugin_version\s*=\s*'([^']+)'/)?.[1],
		'readme.txt (Stable tag)': readmeFile.match(/^Stable tag:\s*(.+)$/m)?.[1].trim(),
		'package.json': JSON.parse(packageFile).version,
	};
}

/**
 * Resolve the release version, throwing when the declarations disagree.
 *
 * @param {string} root Plugin root directory.
 * @param {string} slug Plugin slug.
 * @param {(msg: string) => void} [onWarning] Called for non-fatal problems.
 * @returns {Promise<string>} The agreed version.
 */
export async function resolveVersion(root, slug, onWarning = () => {}) {
	const sources = await readVersionSources(root, slug);
	const missing = Object.entries(sources).filter(([, value]) => !value);

	if (missing.length > 0) {
		throw new Error(`Could not read a version from: ${missing.map(([key]) => key).join(', ')}.`);
	}

	if (new Set(Object.values(sources)).size > 1) {
		const detail = Object.entries(sources)
			.map(([key, value]) => `    ${key.padEnd(30)} ${value}`)
			.join('\n');

		throw new Error(`Version mismatch — align these before releasing:\n${detail}`);
	}

	const version = sources['package.json'];
	const readmeFile = await fs.readFile(path.join(root, 'readme.txt'), 'utf8');

	// A release whose changelog stops at the previous version tells users nothing
	// about what changed. Worth flagging, but the package still installs.
	if (!new RegExp(`^=\\s*${version.replace(/\./g, '\\.')}\\s*=$`, 'm').test(readmeFile)) {
		onWarning(`readme.txt has no "= ${version} =" changelog entry.`);
	}

	return version;
}
