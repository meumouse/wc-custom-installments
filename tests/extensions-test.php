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
 * @since 6.0.0
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/src/Admin/Menu.php';
require_once __DIR__ . '/../admin/src/Admin/Product_Settings.php';
require_once __DIR__ . '/../admin/src/Core/Init.php';
require_once __DIR__ . '/../admin/src/Core/Functions.php';

use MeuMouse\WC_Custom_Installments\Admin\Default_Options;
use MeuMouse\WC_Custom_Installments\Admin\Menu;
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

/**
 * Print a product field the way WooCommerce's woocommerce_wp_* helpers do.
 *
 * Only the parts the panel decides: the wrapper classes, the label and the
 * custom attributes.
 *
 * @param array<string,mixed> $field | Field arguments
 * @return void
 */
function print_test_product_field( $field ) {
	$attributes = '';

	foreach ( (array) ( $field['custom_attributes'] ?? array() ) as $name => $value ) {
		$attributes .= sprintf( ' %s="%s"', $name, $value );
	}

	printf(
		'<p class="form-field %1$s_field %2$s"><label for="%1$s">%3$s</label><input id="%1$s"%4$s /></p>' . "\n",
		$field['id'],
		$field['wrapper_class'] ?? '',
		wp_kses_post( $field['label'] ?? '' ),
		$attributes
	);
}

if ( ! function_exists('woocommerce_wp_checkbox') ) {
	/**
	 * Print a checkbox field.
	 *
	 * @param array<string,mixed> $field | Field arguments
	 * @return void
	 */
	function woocommerce_wp_checkbox( $field ) {
		print_test_product_field( $field );
	}
}

if ( ! function_exists('woocommerce_wp_select') ) {
	/**
	 * Print a select field.
	 *
	 * @param array<string,mixed> $field | Field arguments
	 * @return void
	 */
	function woocommerce_wp_select( $field ) {
		print_test_product_field( $field );
	}
}

if ( ! function_exists('woocommerce_wp_text_input') ) {
	/**
	 * Print a text field.
	 *
	 * @param array<string,mixed> $field | Field arguments
	 * @return void
	 */
	function woocommerce_wp_text_input( $field ) {
		print_test_product_field( $field );
	}
}

if ( ! function_exists('wp_admin_notice') ) {
	/**
	 * Print an admin notice.
	 *
	 * @param string $message | Notice markup
	 * @param array<string,mixed> $args | Notice arguments
	 * @return void
	 */
	function wp_admin_notice( $message, $args = array() ) {
		printf(
			'<div class="notice notice-%s %s"><p>%s</p></div>' . "\n",
			$args['type'] ?? 'info',
			implode( ' ', (array) ( $args['additional_classes'] ?? array() ) ),
			$message
		);
	}
}

/**
 * Render the plugin's product data panel and return its markup.
 *
 * @param Product_Settings $settings | Product screen handler
 * @return string
 */
function render_product_panel( $settings ) {
	ob_start();
	$settings->render_panel();

	return (string) ob_get_clean();
}

/**
 * Render the plugin's fields for one variation and return their markup.
 *
 * @param Product_Settings $settings | Product screen handler
 * @param int $loop | Position of the variation on the page
 * @param int $variation_id | Variation ID
 * @param int $parent_id | Parent product ID
 * @return string
 */
function render_variation_panel( $settings, $loop, $variation_id, $parent_id ) {
	ob_start();
	$settings->render_variation_fields( $loop, array(), (object) array( 'ID' => $variation_id, 'post_parent' => $parent_id ) );

	return (string) ob_get_clean();
}

if ( ! function_exists('delete_post_meta') ) {
	/**
	 * Delete a meta value.
	 *
	 * @param int $id | Post ID
	 * @param string $key | Meta key
	 * @return bool
	 */
	function delete_post_meta( $id, $key ) {
		unset( $GLOBALS['wp_test_meta'][ $id ][ $key ] );

		return true;
	}
}

/**
 * Whether a field's printed paragraph contains a string.
 *
 * @param string $html | Panel markup
 * @param string $key | Field key
 * @param string $needle | What to look for
 * @return bool
 */
function field_markup_has( $html, $key, $needle ) {
	if ( ! preg_match( '/<p class="form-field ' . preg_quote( $key, '/' ) . '_field[^\n]*<\/p>/', $html, $match ) ) {
		return false;
	}

	return str_contains( $match[0], $needle );
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

echo PHP_EOL, 'The Pro fields the panel declares', PHP_EOL;

// Declared here, gated, so a store without the add-on sees them locked on the
// product screen. Every one lives on products in the wild under its 5.x name.
$pro_keys = array(
	'enable_discount_per_unit',
	'discount_per_unit_method',
	'unit_discount_amount',
	'discount_gateway',
	'enable_discount_per_quantity',
	'minimum_quantity_discount',
	'discount_per_quantity_method',
	'quantity_discount_amount',
);

$by_key = array_column( $fields, null, 'key' );

foreach ( $pro_keys as $key ) {
	check( sprintf( '%s is declared under its 5.x name', $key ), true, isset( $by_key[ $key ] ) );
}

$ungated = array_values( array_filter( $pro_keys, static function( $key ) use ( $by_key ) {
	return empty( $by_key[ $key ]['feature'] ) || ! Features::is_pro( $by_key[ $key ]['feature'] );
} ) );

check( 'and every one is gated behind a Pro feature', array(), $ungated );

echo PHP_EOL, 'The product data tab', PHP_EOL;

$settings = new Product_Settings();
$tabs = $settings->register_tab( array( 'general' => array( 'label' => 'General', 'target' => 'general_product_data' ) ) );

check( 'the tab is added', true, isset( $tabs[ Product_Settings::TAB ] ) );
check( 'under the plugin name', 'Parcelas Customizadas', $tabs[ Product_Settings::TAB ]['label'] ?? '' );
check( 'and opens the plugin panel', Product_Settings::PANEL, $tabs[ Product_Settings::TAB ]['target'] ?? '' );
check( 'WooCommerce\'s own tabs are left alone', true, isset( $tabs['general'] ) );

$grouped = Product_Settings::group_fields( array(
	array( 'key' => 'a', 'group' => 'discounts' ),
	array( 'key' => 'b' ),
	array( 'key' => 'c', 'group' => 'not_a_group' ),
	array( 'key' => 'd', 'group' => 'installments' ),
) );

check( 'groups come out in their own order, empty ones left out', array( 'installments', 'discounts', 'other' ), array_keys( $grouped ) );
check( 'a field with no group, or an unknown one, lands in the last', array( 'b', 'c' ), array_column( $grouped['other'], 'key' ) );

echo PHP_EOL, 'The panel without the add-on', PHP_EOL;

$GLOBALS['post'] = (object) array( 'ID' => 101 );

$html = render_product_panel( $settings );

check( 'the panel carries the ID the tab targets', true, str_contains( $html, 'id="' . Product_Settings::PANEL . '"' ) );
check( 'and the class the panel script looks for', true, str_contains( $html, 'wc-custom-installments-options' ) );
check( 'it says the locked fields are Pro', true, str_contains( $html, 'wc-custom-installments-pro-notice' ) );
check( 'and links to the add-on', true, str_contains( $html, 'href="' . WC_CUSTOM_INSTALLMENTS_STORE_URL . '"' ) );
check( 'a Pro field is marked', true, field_markup_has( $html, 'enable_discount_per_unit', 'wc-custom-installments-pro-badge' ) );
check( 'and disabled', true, field_markup_has( $html, 'enable_discount_per_unit', 'disabled' ) );
check( 'a free field is neither', false, field_markup_has( $html, '__disable_installments', 'wc-custom-installments-pro-badge' ) || field_markup_has( $html, '__disable_installments', 'disabled' ) );

// With the rule store-wide the quantity fields would have nothing to hold, but
// while locked they are there to show what the add-on would give the product.
check( 'the quantity group shows while it is locked', true, str_contains( $html, 'wc-custom-installments-group" data-group="quantity_discounts"' ) );

$variation_html = render_variation_panel( $settings, 2, 31, 30 );

check( 'a variation\'s fields are marked Pro too', true, field_markup_has( $variation_html, 'wcci_variation_enable_discount_per_unit_2', 'wc-custom-installments-pro-badge' ) );
check( 'and disabled', true, field_markup_has( $variation_html, 'wcci_variation_enable_discount_per_unit_2', 'disabled' ) );

$GLOBALS['wp_test_meta'] = array();
$_POST = array( Product_Settings::VARIATION_FIELDS => array( 2 => array( 'enable_discount_per_unit' => 'yes', 'unit_discount_amount' => '5' ) ) );

$settings->save_variation_fields( 31, 2 );

check( 'and a locked variation field is refused', '', get_post_meta( 31, 'enable_discount_per_unit', true ) );

echo PHP_EOL, 'Saving the product', PHP_EOL;

// Saving is what enforces the gate: the disabled input submits nothing, but
// "nothing was submitted" and "this may not be written" are different claims,
// and only the second one survives a crafted request.
$GLOBALS['wp_test_meta'] = array();
$_POST = array( 'unit_discount_amount' => '25', '__disable_installments' => 'yes' );

$settings->save_fields( 101 );

check( 'a free field is written', 'yes', get_post_meta( 101, '__disable_installments', true ) );
check( 'a gated field is refused', '', get_post_meta( 101, 'unit_discount_amount', true ) );

add_filter( 'WC_Custom_Installments/Features/Is_Available', '__return_true' );
Features::flush();

$settings->save_fields( 101 );

check( 'and is written once the gate opens', '25', get_post_meta( 101, 'unit_discount_amount', true ) );

echo PHP_EOL, 'The panel once the gate opens', PHP_EOL;

$html = render_product_panel( $settings );

check( 'no Pro notice', false, str_contains( $html, 'wc-custom-installments-pro-notice' ) );
check( 'no field marked Pro', false, str_contains( $html, 'wc-custom-installments-pro-badge' ) );
check( 'and the store-wide quantity rule hides its group again', true, str_contains( $html, 'wc-custom-installments-group hidden" data-group="quantity_discounts"' ) );

echo PHP_EOL, 'A variation\'s own discount', PHP_EOL;

$keys = array_column( Product_Settings::get_variation_fields(), 'key' );

// The engine reads these on the variation before its parent, so they have to
// be the product's own 5.x keys.
check(
	'a variation stores the product\'s meta keys',
	array( 'enable_discount_per_unit', 'discount_per_unit_method', 'unit_discount_amount', 'discount_gateway' ),
	$keys
);

$GLOBALS['wp_test_meta'] = array();
$variation_html = render_variation_panel( $settings, 3, 31, 30 );

check( 'its fields carry the variation\'s position', true, str_contains( $variation_html, 'id="wcci_variation_unit_discount_amount_3"' ) );
check( 'which the panel script reads', true, str_contains( $variation_html, 'data-wcci-suffix="_3"' ) );
check( 'no field is marked Pro once the gate opens', false, str_contains( $variation_html, 'wc-custom-installments-pro-badge' ) );
check( 'and the details wait for "its own discount"', true, field_markup_has( $variation_html, 'wcci_variation_unit_discount_amount_3', 'hidden' ) );

$_POST = array(
	Product_Settings::VARIATION_FIELDS => array(
		3 => array(
			'enable_discount_per_unit' => 'yes',
			'discount_per_unit_method' => 'fixed',
			'unit_discount_amount' => '15',
			'discount_gateway' => '',
		),
	),
);

$settings->save_variation_fields( 31, 3 );

check( 'its own discount is written to the variation', 'yes', get_post_meta( 31, 'enable_discount_per_unit', true ) );
check( 'with its details', array( 'fixed', '15' ), array( get_post_meta( 31, 'discount_per_unit_method', true ), get_post_meta( 31, 'unit_discount_amount', true ) ) );

// The product's own checkbox shares the key. Posted under a name of their own,
// the variation's fields cannot tick it.
$settings->save_fields( 30 );
check( 'and never reaches the product\'s checkbox', 'no', get_post_meta( 30, 'enable_discount_per_unit', true ) );

$_POST = array( Product_Settings::VARIATION_FIELDS => array( 3 => array( 'discount_per_unit_method' => 'something-else' ) ) );
$settings->save_variation_fields( 31, 3 );
check( 'a choice the list does not offer is not written', 'fixed', get_post_meta( 31, 'discount_per_unit_method', true ) );

$_POST = array();
$settings->save_variation_fields( 31, 3 );
check( 'a variation missing from the request keeps what it has', 'yes', get_post_meta( 31, 'enable_discount_per_unit', true ) );

// "Same as the product" stores nothing, which is what makes the variation
// follow its product again; a stored `no` would mean the store's discount.
$_POST = array( Product_Settings::VARIATION_FIELDS => array( 3 => array( 'enable_discount_per_unit' => '' ) ) );
$settings->save_variation_fields( 31, 3 );
check( '"same as the product" removes the setting', false, array_key_exists( 'enable_discount_per_unit', $GLOBALS['wp_test_meta'][31] ?? array() ) );

$_POST = array( Product_Settings::VARIATION_FIELDS => array( 3 => array( 'enable_discount_per_unit' => 'no' ) ) );
$settings->save_variation_fields( 31, 3 );
check( 'and "the store\'s discount" stores no', 'no', get_post_meta( 31, 'enable_discount_per_unit', true ) );

$_POST = array();
$GLOBALS['wp_test_meta'] = array();

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

echo PHP_EOL, 'Screens add-ons place in the menu', PHP_EOL;

// The Pro add-on's licence screen goes through this filter; DEVELOPERS.md
// offers it to third parties too. A malformed entry must be dropped rather
// than registered as a page that renders nothing.
check( 'no add-on, no screens', array(), Menu::get_pages() );
check( 'and no licence address', '', Menu::license_url() );

add_filter( 'WC_Custom_Installments/Admin/Pages', static function( $pages ) {
	$pages['wc-custom-installments-last'] = array( 'title' => 'Last', 'component' => 'last', 'position' => 100 );
	$pages['wc-custom-installments-first'] = array( 'title' => 'First', 'component' => 'first', 'position' => 5, 'menu_title' => 'Primeiro' );
	$pages['wc-custom-installments-no-component'] = array( 'title' => 'Broken' );
	$pages[ Menu::SETTINGS_PAGE ] = array( 'title' => 'Hijack', 'component' => 'hijack' );

	return $pages;
} );

$pages = Menu::get_pages();

check( 'they come out ordered by position', array( 'wc-custom-installments-first', 'wc-custom-installments-last' ), array_keys( $pages ) );
check( 'a page with no renderer is dropped', false, isset( $pages['wc-custom-installments-no-component'] ) );
check( 'the settings screen cannot be taken over', false, isset( $pages[ Menu::SETTINGS_PAGE ] ) );
check( 'the menu title falls back to the title', 'Last', $pages['wc-custom-installments-last']['menu_title'] );
check( 'and an explicit one is kept', 'Primeiro', $pages['wc-custom-installments-first']['menu_title'] );
check(
	'a screen\'s address',
	'https://example.test/wp-admin/admin.php?page=wc-custom-installments-first',
	Menu::page_url('wc-custom-installments-first')
);
check(
	'the settings screen\'s, with a tab',
	'https://example.test/wp-admin/admin.php?page=wc-custom-installments&section=discounts',
	Menu::page_url( '', array( 'section' => 'discounts' ) )
);

$GLOBALS['wp_test_filters'] = array();
Features::flush();

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
