<?php

/**
 * Shortcodes, product fields and class discovery.
 *
 * Three contracts that break silently. A shortcode tag lives inside post
 * content, where nothing can migrate it. A product meta key lives on thousands
 * of products. And a class discovered by the wrong rule gets built at the wrong
 * moment, which surfaces as an unrelated fatal somewhere else entirely.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/extensions-test.php
 *
 * @since 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/src/Admin/Product_Settings.php';
require_once __DIR__ . '/../admin/src/Core/Init.php';
require_once __DIR__ . '/../admin/src/Core/Functions.php';

use MeuMouse\WC_Custom_Installments\Admin\Default_Options;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Product_Settings;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Init;
use MeuMouse\WC_Custom_Installments\Frontend\Shortcodes;

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

echo PHP_EOL, 'Shortcode tags', PHP_EOL;

new Shortcodes();

$registered = array_keys( $GLOBALS['wp_test_shortcodes'] );
$legacy = array_filter( $registered, static fn( $tag ) => str_starts_with( $tag, 'woo_custom_installments' ) );
$current = array_filter( $registered, static fn( $tag ) => str_starts_with( $tag, 'wc_custom_installments' ) );

check( 'all fifteen 5.x tags are registered', 15, count( $legacy ) );
check( 'and fifteen under the current prefix', 15, count( $current ) );

// The exact 5.x tags, spelled out. A typo in the map would otherwise register
// something plausible that no existing page actually uses.
foreach ( array(
	'woo_custom_installments_modal',
	'woo_custom_installments_card_info',
	'woo_custom_installments_group',
	'woo_custom_installments_table_installments',
	'woo_custom_installments_pix_container',
	'woo_custom_installments_ticket_container',
	'woo_custom_installments_credit_card_container',
	'woo_custom_installments_debit_card_container',
	'woo_custom_installments_ticket_discount_badge',
	'woo_custom_installments_pix_info',
	'woo_custom_installments_economy_pix_badge',
	'woo_custom_installments_get_price_on_pix',
	'woo_custom_installments_get_price_on_ticket',
	'woo_custom_installments_get_economy_pix_price',
	'woo_custom_installments_sale_badge',
) as $tag ) {
	check( sprintf( '[%s] answers', $tag ), true, isset( $GLOBALS['wp_test_shortcodes'][ $tag ] ) );
}

echo PHP_EOL, 'Every tag resolves to a callable', PHP_EOL;

$broken = array();

foreach ( $GLOBALS['wp_test_shortcodes'] as $tag => $callback ) {
	if ( ! is_callable( $callback ) ) {
		$broken[] = $tag;
	}
}

check( 'no tag points at a method that does not exist', array(), $broken );

echo PHP_EOL, 'Product fields', PHP_EOL;

$fields = Product_Settings::get_fields();
$keys = array_column( $fields, 'key' );

// These live on thousands of products. Renaming one silently un-hides every
// product a store owner had hidden.
check( 'the installments opt-out keeps its 5.x meta key', true, in_array( '__disable_installments', $keys, true ) );
check( 'the discount opt-out keeps its 5.x meta key', true, in_array( '__disable_discount_main_price', $keys, true ) );

echo PHP_EOL, 'An add-on registering a gated field', PHP_EOL;

add_filter( 'WC_Custom_Installments/Admin/Product_Fields', static function( $fields ) {
	$fields[] = array(
		'key' => 'unit_discount_amount',
		'type' => 'number',
		'label' => 'Discount amount',
		'description' => '',
		'feature' => 'product_overrides',
		'quick_edit' => false,
	);

	return $fields;
} );

$fields = Product_Settings::get_fields();
$keys = array_column( $fields, 'key' );

check( 'the field is registered', true, in_array( 'unit_discount_amount', $keys, true ) );

// Saving is what enforces the gate: the disabled input submits nothing, but
// "nothing was submitted" and "this may not be written" are different claims,
// and only the second one survives a crafted request.
$GLOBALS['wp_test_meta'] = array();
$_POST = array( 'unit_discount_amount' => '25', '__disable_installments' => 'yes' );

$settings = new Product_Settings();
$settings->save_fields( 101 );

check( 'a free field is written', 'yes', get_post_meta( 101, '__disable_installments', true ) );
check( 'a gated field is refused', '', get_post_meta( 101, 'unit_discount_amount', true ) );

add_filter( 'WC_Custom_Installments/Features/Is_Available', '__return_true' );
Features::flush();

$settings->save_fields( 101 );

check( 'and is written once the gate opens', '25', get_post_meta( 101, 'unit_discount_amount', true ) );

echo PHP_EOL, 'The documented extension helpers', PHP_EOL;

// DEVELOPERS.md tells third parties to call these. A helper that quietly does
// nothing is worse than no helper: the developer follows the documentation and
// their code never runs.
wcci_register_feature( 'example_feature', array( 'label' => 'Example', 'tier' => 'pro' ) );
check( 'a registered feature lands in the registry as Pro', true, Features::is_pro('example_feature') );

wcci_register_settings_card( 'installments', array(
	'id' => 'example-card',
	'title' => 'Example',
	'description' => '',
	'fields' => array(),
) );

wcci_register_settings_field( 'example-card', array(
	'type' => 'toggle',
	'key' => 'example_setting',
	'label' => 'Example',
	'description' => '',
	'default' => 'no',
) );

Registry::flush();

$definitions = Registry::get_field_definitions();
check( 'a registered field reaches the schema', true, isset( $definitions['example_setting'] ) );

// Registering a field with a default has to register the default as well, or
// the setting reads as an empty string on a fresh install.
check(
	'and brings its default with it',
	'no',
	Default_Options::get_defaults()['example_setting'] ?? 'MISSING'
);

wcci_register_product_setting( array(
	'key' => 'example_product_note',
	'type' => 'text',
	'label' => 'Example',
	'description' => '',
	'feature' => '',
	'quick_edit' => false,
) );

check(
	'a registered product field reaches the panel',
	true,
	in_array( 'example_product_note', array_column( Product_Settings::get_fields(), 'key' ), true )
);

echo PHP_EOL, 'Class discovery', PHP_EOL;

// A page-builder widget is constructed by its own manager with its own
// arguments; building a second unregistered copy surfaces as a fatal in
// someone else's code, far from here.
$classmap = require __DIR__ . '/../admin/vendor/composer/autoload_classmap.php';
$widgets = array_values( array_filter( array_keys( $classmap ), static fn( $class ) => str_contains( $class, '\\Widgets\\' ) ) );

check( 'the widget classes are in the classmap', true, count( $widgets ) >= 2 );

// The real rule, called directly. Reimplementing it here would only prove the
// test agrees with itself.
$discoverable = array_values( array_filter( $widgets, static fn( $class ) => Init::is_discoverable( $class ) ) );

check( 'and the discovery rule rejects every one', array(), $discoverable );
check( 'an ordinary service is still discovered', true, Init::is_discoverable('MeuMouse\\WC_Custom_Installments\\Frontend\\Shortcodes') );
check( 'an abstract is not', false, Init::is_discoverable('MeuMouse\\WC_Custom_Installments\\Rest\\Abstract_Route') );
check( 'and the bootstrap does not boot itself', false, Init::is_discoverable( Init::class ) );

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
