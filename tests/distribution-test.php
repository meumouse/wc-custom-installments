<?php

/**
 * The update channel that carries the plugin until it reaches WordPress.org.
 *
 * Two things here fail without saying anything. A configuration that asked for
 * the SDK's `full` preset would start demanding a licence for a free plugin and
 * quietly stop offering updates to every store. And the details modal is
 * answered by two filters at once - the SDK's and ours - so a merge that drops
 * a field surfaces as an undefined property notice on the dependency card,
 * nowhere near either file.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/distribution-test.php
 *
 * @since 6.1.0
 */

/**
 * Whether this is an admin request.
 *
 * Defined before the bootstrap, whose own stub answers false: the class under
 * test registers no filter outside the admin, and the priority it registers at
 * is half of what is being checked here.
 *
 * @return bool
 */
function is_admin() {
	return true;
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/src/Distribution/MDS_Integration.php';
require_once __DIR__ . '/../admin/src/Compatibility/Dependency_Api.php';

use MeuMouse\WC_Custom_Installments\Compatibility\Dependency_Api;
use MeuMouse\WC_Custom_Installments\Distribution\MDS_Integration;

$passed = 0;
$failed = 0;

/**
 * Assert that two values match.
 *
 * @param string $label | What is being checked
 * @param mixed $expected | Expected value
 * @param mixed $actual | Actual value
 * @return void
 */
function check( $label, $expected, $actual ) {
	global $passed, $failed;

	if ( $expected === $actual ) {
		$passed++;
		printf( "  PASS  %s%s", $label, PHP_EOL );

		return;
	}

	$failed++;
	printf(
		"  FAIL  %s%s          expected: %s%s          actual:   %s%s",
		$label,
		PHP_EOL,
		var_export( $expected, true ),
		PHP_EOL,
		var_export( $actual, true ),
		PHP_EOL
	);
}

echo PHP_EOL, 'SDK configuration', PHP_EOL;

$config = MDS_Integration::config( WC_CUSTOM_INSTALLMENTS_FILE, '6.0.1' );

check( 'the product slug is the plugin slug', 'wc-custom-installments', $config['product_slug'] );
check( 'it registers as a plugin', 'plugin', $config['type'] );
// The SDK keys the core update transient by this value, so it has to be the
// basename WordPress knows the plugin by - never an absolute path. Checked by
// shape rather than against a literal: the directory is called something else
// in a staging copy, a release tree or a worktree, and a pinned string would
// fail there for no reason anyone could act on.
check( 'the file is a basename, not an absolute path', false, (bool) preg_match( '#^([A-Za-z]:|/)#', $config['file'] ) );
check( 'with one directory segment', 1, substr_count( $config['file'], '/' ) );
check( 'and it names the plugin file', 'wc-custom-installments.php', basename( $config['file'] ) );
check( 'the current version is the one passed in', '6.0.1', $config['current_version'] );
check( 'and the host is the one constant already points at', WC_CUSTOM_INSTALLMENTS_API_URL, $config['api_base_url'] );

// The whole point of the integration: this is a free plugin, so the licence
// module must be inert. `updates_only` is what makes the SDK send the update
// check with no licence key and schedule no heartbeat.
check( 'the preset is updates_only', 'updates_only', $config['mode'] );
check( 'no feature is overridden on top of it', false, isset( $config['features'] ) );
check( 'and nothing licence-shaped is configured', array(), array_intersect( array( 'license_key', 'license', 'settings_parent' ), array_keys( $config ) ) );

echo PHP_EOL, 'The channel stays shut until it is configured', PHP_EOL;

check( 'with no keys, the channel is closed', false, MDS_Integration::is_enabled() );

// The filter is the extension point an add-on or a site snippet would use, and
// it is the last word in is_enabled(). If it stopped being consulted, the only
// way to notice would be a store that cannot switch updates off.
add_filter( 'WC_Custom_Installments/MDS/Enabled', '__return_true' );
check( 'the Enabled filter is consulted', true, MDS_Integration::is_enabled() );

$GLOBALS['wp_test_filters'] = array();

add_filter( 'WC_Custom_Installments/MDS/Enabled', '__return_false' );
check( 'and it can refuse', false, MDS_Integration::is_enabled() );

$GLOBALS['wp_test_filters'] = array();

echo PHP_EOL, 'The details modal, answered by both filters', PHP_EOL;

$dependency_api = new Dependency_Api();

check( 'the handler runs after the SDK, at priority 30', true, isset( $GLOBALS['wp_test_filters']['plugins_api'][30] ) );

$args = (object) array( 'slug' => 'wc-custom-installments' );

// Nothing answered yet: every build ships with the channel shut, and this is
// the answer the Pro add-on's dependency lookup gets.
$alone = $dependency_api->answer_own_slug( false, 'plugin_information', $args );

// Read from the header rather than written down here: the release script
// rewrites the header, and a version pinned in a test would start failing on
// the next bump for no reason anyone could act on.
$header_version = get_file_data( WC_CUSTOM_INSTALLMENTS_FILE, array( 'version' => 'Version' ), 'plugin' )['version'];

check( 'on its own it answers from the plugin header', 'Parcelas Customizadas para WooCommerce', $alone->name );
check( 'with the running version', $header_version, $alone->version );
check( 'and no download link, because the plugin is already installed', '', $alone->download_link );
check( 'it is marked as hosted outside the directory', true, $alone->external );
check( 'and it describes the plugin', true, isset( $alone->sections['description'] ) && '' !== $alone->sections['description'] );

// What the SDK hands over: the release and the changelog, and none of the
// presentational fields core reads. See Updates\UpdateTransformer.
$from_sdk = (object) array(
	'name' => 'Parcelas Customizadas para WooCommerce',
	'slug' => 'wc-custom-installments',
	'version' => '6.2.0',
	'homepage' => 'https://meumouse.com/plugins/parcelas-customizadas-para-woocommerce/',
	'requires' => '6.5',
	'tested' => '7.1',
	'requires_php' => '8.1',
	'download_link' => 'https://cloud.meumouse.com/download/token',
	'banners' => array(),
	'sections' => array( 'changelog' => '<h4>6.2.0</h4>' ),
);

$merged = $dependency_api->answer_own_slug( $from_sdk, 'plugin_information', $args );

check( 'the release the SDK found wins', '6.2.0', $merged->version );
check( 'so does its package link', 'https://cloud.meumouse.com/download/token', $merged->download_link );
check( 'the author survives the merge', true, str_contains( (string) $merged->author, 'MeuMouse.com' ) );
check( 'so does the short description', true, '' !== $merged->short_description );
check( 'so does the icon', true, isset( $merged->icons['svg'] ) );
check( 'so do the rating counts core reads', 0, $merged->num_ratings );
check( 'and the outside-the-directory flag', true, $merged->external );
check( 'the description comes first in the modal', 'description', array_key_first( $merged->sections ) );
check( 'and the changelog is still there', '<h4>6.2.0</h4>', $merged->sections['changelog'] );

// An empty field in the earlier answer must not blank out the header's.
$thin = (object) array( 'version' => '6.2.0', 'homepage' => '', 'banners' => array() );
$completed = $dependency_api->answer_own_slug( $thin, 'plugin_information', $args );

check( 'an empty field does not overwrite the header', true, '' !== $completed->homepage );

echo PHP_EOL, 'Every other lookup is left alone', PHP_EOL;

check( 'another plugin\'s slug passes through', false, $dependency_api->answer_own_slug( false, 'plugin_information', (object) array( 'slug' => 'woocommerce' ) ) );
check( 'and so does another kind of request', false, $dependency_api->answer_own_slug( false, 'query_plugins', $args ) );

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
