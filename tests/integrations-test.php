<?php

/**
 * The integrations: the catalogue, the settings tab and the two gates.
 *
 * Both gates matter for their reach rather than their logic. Everything the
 * plugin prints is derived from the price, so a gate the instalment list
 * honours while the payment methods block, the Store API and the storefront
 * runtime parameters ignore would leak exactly what the store asked to
 * withhold, and nothing would report it.
 *
 * The catalogue is checked against the schema, because a field an integration
 * declares and the schema never carries can be neither written nor seen, and a
 * default with no field sits at that value forever.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/integrations-test.php
 *
 * @since 6.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use MeuMouse\WC_Custom_Installments\Admin\Default_Options;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Installments;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Values;
use MeuMouse\WC_Custom_Installments\Frontend\Components;
use MeuMouse\WC_Custom_Installments\Integrations\Catalog;
use MeuMouse\WC_Custom_Installments\Integrations\Role_Based_Pricing;
use MeuMouse\WC_Custom_Installments\Integrations\Store_Api;

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
 * Forget every callback on the gate, so each case starts from an open one.
 *
 * @return void
 */
function reset_gate() {
	$GLOBALS['wp_test_filters']['WC_Custom_Installments/Product/Price_Visible'] = array();
}

/**
 * Whether the store hides prices from visitors, as the other plugin stores it.
 *
 * @param bool $hide | Whether to hide
 * @return void
 */
function set_hide_prices( $hide ) {
	update_option( Role_Based_Pricing::HIDE_PRICES_OPTION, $hide ? 'yes' : 'no' );
}

/**
 * Overwrite some settings, leaving the rest as they are.
 *
 * Straight to the option: `save_settings()` reads an absent toggle as "off",
 * which would switch off everything the test did not mention.
 *
 * @param array<string,mixed> $values | Setting name => value
 * @return void
 */
function set_settings( $values ) {
	update_option( Repository::OPTION, array_merge( (array) get_option( Repository::OPTION, array() ), $values ) );
}

/**
 * One integration, by the ID it describes itself with.
 *
 * @param string $id | Integration ID
 * @return array<string,mixed>
 */
function integration( $id ) {
	foreach ( Catalog::all() as $one ) {
		if ( $id === ( $one['id'] ?? '' ) ) {
			return $one;
		}
	}

	return array();
}


/**
 * The one section the integrations tab is.
 *
 * @return array<string,mixed>
 */
function integrations_section() {
	foreach ( Registry::get_schema() as $section ) {
		if ( 'integrations' === ( $section['id'] ?? '' ) ) {
			return $section;
		}
	}

	return array();
}

/**
 * An add-on's integration, shipping the mark of its card with its own files.
 *
 * @since 6.1.0
 */
class Mark_Test_Integration {

	/**
	 * Describe the integration for the settings screen.
	 *
	 * @return array<string,mixed>
	 */
	public static function describe() {
		return array(
			'id' => 'mark_test',
			'title' => 'Add-on integration',
			'mark' => 'https://cdn.example.test/add-on/mark.png',
		);
	}
}


/**
 * An integration that ships no mark at all.
 *
 * @since 6.1.0
 */
class Mark_Test_Bare_Integration {

	/**
	 * Describe the integration for the settings screen.
	 *
	 * @return array<string,mixed>
	 */
	public static function describe() {
		return array(
			'id' => 'mark_test_bare',
			'title' => 'Bare integration',
		);
	}
}


$product = wc_test_product( 300 );

echo PHP_EOL, 'The catalogue', PHP_EOL;

$catalog = Catalog::all();
$ids = array_column( $catalog, 'id' );

check( 'lists every integration that has something to decide', array(
	'elementor',
	'rank_math',
	'tiered_pricing_table',
	'dynamic_pricing_discounts',
	'role_based_pricing',
), $ids );

check( 'each one names itself', true, '' === implode( '', array_filter( array_column( $catalog, 'title' ), static fn( $title ) => '' === $title ) ) );
check( 'each one declares at least one action', array(), array_values( array_filter( $catalog, static fn( $one ) => empty( $one['fields'] ) ) ) );
check( 'none is detected on a store with none of them installed', array(), array_values( array_filter( $ids, 'MeuMouse\\WC_Custom_Installments\\Integrations\\Catalog::is_detected' ) ) );

echo PHP_EOL, 'The integrations tab', PHP_EOL;

$section = integrations_section();
$cards = (array) ( $section['cards'] ?? array() );

check( 'is a section of its own', 'integrations', $section['id'] ?? '' );
check( 'with an icon the app maps', 'extension', $section['icon'] ?? '' );
check( 'drawn as the grid rather than stacked groups', 'integrations', $section['component'] ?? '' );
check( 'while still carrying its cards, which is where the fields are read from', true, count( $cards ) > 0 );
check( 'and one card per integration', count( $catalog ), count( $cards ) );
check( 'each card has its slug', array(
	'elementor',
	'rank-math',
	'tiered-pricing-table',
	'dynamic-pricing-discounts',
	'role-based-pricing',
), array_column( $cards, 'slug' ) );
check( 'and the mark it shows, as a URL and not a path the browser builds', array(
	'https://example.test/wp-content/plugins/wc-custom-installments/assets/integrations/elementor.svg',
	'https://example.test/wp-content/plugins/wc-custom-installments/assets/integrations/rank-math.svg',
	'https://example.test/wp-content/plugins/wc-custom-installments/assets/integrations/tiered-pricing-table.png',
	'https://example.test/wp-content/plugins/wc-custom-installments/assets/integrations/dynamic-pricing-discounts.webp',
	'https://example.test/wp-content/plugins/wc-custom-installments/assets/integrations/role-based-pricing.png',
), array_column( $cards, 'mark' ) );
check( 'each card names the switch it carries', array(
	'integration_elementor_enabled',
	'integration_rank_math_enabled',
	'integration_tiered_pricing_table_enabled',
	'integration_dynamic_pricing_discounts_enabled',
	'integration_role_based_pricing_enabled',
), array_column( $cards, 'primary_key' ) );
check( 'and that switch is the first of its fields', array_column( $cards, 'primary_key' ), array_map( static fn( $card ) => $card['fields'][0]['key'] ?? '', $cards ) );
check( 'every card can title its dialog', array(), array_values( array_filter( $cards, static fn( $card ) => empty( $card['modal']['title'] ) ) ) );
check( 'each card named after its integration', array_column( $catalog, 'title' ), array_column( $cards, 'title' ) );
check( 'no card was found installed on this store', array( false ), array_unique( array_column( $cards, 'active' ) ) );
check( 'so each one has something to say about it', array(), array_values( array_filter( $cards, static fn( $card ) => empty( $card['not_found_notice'] ) ) ) );

$definitions = Registry::get_field_definitions();
$declared = array_column( Catalog::fields(), 'key' );
$missing = array_values( array_diff( $declared, array_keys( $definitions ) ) );

check( 'every declared field reached the schema', array(), $missing );

$defaults = Default_Options::get_defaults();
$undefaulted = array_values( array_filter( $declared, static fn( $key ) => ! array_key_exists( $key, $defaults ) ) );

check( 'and every one has a default', array(), $undefaulted );
check( 'the compatibility behaviours are on by default', 'yes', $defaults['integration_tiered_pricing_use_tier_price'] ?? '' );
check( 'respecting hidden prices too', 'yes', $defaults['integration_role_pricing_respect_hidden_prices'] ?? '' );
check( 'while the two suspensions start off', array( 'no', 'no' ), array(
	$defaults['integration_role_pricing_hide_installments'] ?? '',
	$defaults['integration_role_pricing_hide_discount'] ?? '',
) );
check( 'a Pro field in the tab is still locked', true, ! empty( $definitions['display_discount_price_schema']['locked'] ) );
check( 'and a free one is not', false, ! empty( $definitions['enable_elementor_widgets']['locked'] ) );
check( 'the master switch of a Pro integration stays free', false, ! empty( $definitions['integration_rank_math_enabled']['locked'] ) );
check( 'every master switch is on for a new store', array( 'yes', 'yes', 'yes', 'yes', 'yes' ), array_map(
	static fn( $card ) => $defaults[ $card['primary_key'] ] ?? '',
	$cards
) );

echo PHP_EOL, 'The mark of an integration', PHP_EOL;

add_filter( 'WC_Custom_Installments/Integrations/Mark', function( $mark, $id, $file ) {
	return 'rank_math' === $id ? 'https://cdn.example.test/marks/' . $file : $mark;
}, 10, 3 );

check( 'can be replaced by code', 'https://cdn.example.test/marks/rank-math.svg', integration('rank_math')['mark'] ?? '' );
check( 'leaving every other one where it was', 'https://example.test/wp-content/plugins/wc-custom-installments/assets/integrations/elementor.svg', integration('elementor')['mark'] ?? '' );

$GLOBALS['wp_test_filters']['WC_Custom_Installments/Integrations/Mark'] = array();

// An add-on ships its mark with its own files, so it declares a full URL and
// the catalogue has nothing to build.
add_filter( 'WC_Custom_Installments/Integrations/Catalog', function( $classes ) {
	$classes[] = Mark_Test_Integration::class;
	$classes[] = Mark_Test_Bare_Integration::class;

	return $classes;
} );

check( 'and a full URL is passed through untouched', 'https://cdn.example.test/add-on/mark.png', integration('mark_test')['mark'] ?? '' );
check( 'while an integration with no mark leaves the card its initial', '', integration('mark_test_bare')['mark'] ?? '' );

$GLOBALS['wp_test_filters']['WC_Custom_Installments/Integrations/Catalog'] = array();

check( 'the catalogue is the five again once the add-on is gone', 5, count( Catalog::all() ) );

echo PHP_EOL, 'The master switch of an integration', PHP_EOL;

check( 'answers for the integration it names', true, Catalog::is_enabled('role_based_pricing') );
check( 'and is the key the catalogue builds', 'integration_role_based_pricing_enabled', Catalog::primary_key('role_based_pricing') );

set_settings( array( 'integration_role_based_pricing_enabled' => 'no' ) );

check( 'switched off, the catalogue says so', false, Catalog::is_enabled('role_based_pricing') );

set_settings( array( 'integration_role_based_pricing_enabled' => 'yes' ) );

echo PHP_EOL, 'The gate on its own', PHP_EOL;

check( 'is open on a store where nothing closes it', true, Helpers::is_price_visible( $product ) );
check( 'and the product is priced', true, Helpers::is_product_available( $product ) );
check( 'the instalment plan is built', 12, count( Calculate_Installments::installments_list( array(), null, $product ) ) );
check( 'and the Store API carries it', 12, count( Store_Api::build_product_data( $product, 'strval' )['installments'] ) );

add_filter( 'WC_Custom_Installments/Product/Price_Visible', '__return_false' );

check( 'code can close it', false, Helpers::is_price_visible( $product ) );
check( 'a closed gate makes the product unpriced', false, Helpers::is_product_available( $product ) );
check( 'no instalment is built', array(), Calculate_Installments::installments_list( array(), null, $product ) );
check( 'the Store API carries no instalments', array(), Store_Api::build_product_data( $product, 'strval' )['installments'] );
check( 'and no upfront discount', null, Store_Api::build_product_data( $product, 'strval' )['discounts']['main'] );
check( 'every element is silenced, wherever it was placed', false, Components::should_display('display_loop_and_single_product') );

reset_gate();

check( 'and the elements come back when it opens', true, Components::should_display('display_loop_and_single_product') );

echo PHP_EOL, 'Role Based Pricing for Woo, absent', PHP_EOL;

check( 'is not detected', false, Role_Based_Pricing::is_active() );

// The store has asked for hidden prices, but the plugin that would hide them
// is not installed: nothing may read that option and act on it.
set_hide_prices( true );
new Role_Based_Pricing();

check( 'registers nothing on the gate', false, has_filter('WC_Custom_Installments/Product/Price_Visible') );
check( 'and the gate stays open', true, Helpers::is_price_visible( $product ) );

echo PHP_EOL, 'Role Based Pricing for Woo, active', PHP_EOL;

require_once __DIR__ . '/stubs/role-based-pricing.php';

check( 'is detected', true, Role_Based_Pricing::is_active() );

$integration = new Role_Based_Pricing();

check( 'registers on the gate', true, has_filter('WC_Custom_Installments/Product/Price_Visible') );

$GLOBALS['wp_test_is_user_logged_in'] = false;

check( 'a visitor sees no price', false, Helpers::is_price_visible( $product ) );
check( 'so no instalment plan is built for them', array(), Calculate_Installments::installments_list( array(), null, $product ) );
check( 'and the Store API tells them nothing', array(), Store_Api::build_product_data( $product, 'strval' )['installments'] );

$GLOBALS['wp_test_is_user_logged_in'] = true;

check( 'a signed-in customer sees theirs', true, Helpers::is_price_visible( $product ) );
check( 'with the whole plan', 12, count( Calculate_Installments::installments_list( array(), null, $product ) ) );

$GLOBALS['wp_test_is_user_logged_in'] = false;
set_hide_prices( false );

check( 'and a store that hides nothing shows a visitor the plan', true, Helpers::is_price_visible( $product ) );

echo PHP_EOL, 'When the other plugin reorganises its internals', PHP_EOL;

// The container answers null rather than a settings service. The integration
// has to read the stored option instead of deciding the store hides nothing.
\MeowCrew\RoleAndCustomerBasedPricing\Core\ServiceContainer::$reachable = false;
set_hide_prices( true );

check( 'the stored option still answers', true, Role_Based_Pricing::hides_prices_from_visitors() );
check( 'so a visitor still sees no price', false, Helpers::is_price_visible( $product ) );

set_hide_prices( false );

check( 'and an unset option shows the plan', true, Helpers::is_price_visible( $product ) );

\MeowCrew\RoleAndCustomerBasedPricing\Core\ServiceContainer::$reachable = true;

echo PHP_EOL, 'Withholding the plan and the discount for a rule', PHP_EOL;

// The two suspensions ride on the opt-outs a store owner sets per product, so
// what matters is that answering for a rule reaches everything the stored
// opt-out reaches - every element and the Store API alike.
$priced = wc_test_product( 300, array( 'id' => 70 ) );
$plain = wc_test_product( 300, array( 'id' => 71 ) );
$variable = wc_test_product( 300, array(
	'id' => 72,
	'type' => 'variable',
	'children' => array( 73, 74 ),
	'variation_prices' => array( 'price' => array( 73 => 300.0, 74 => 400.0 ), 'regular_price' => array( 73 => 300.0, 74 => 400.0 ) ),
) );

$GLOBALS['rcbp_test_rules'] = array( 70, 74 );
Role_Based_Pricing::flush();

check( 'a rule is seen on the product that has one', true, Role_Based_Pricing::prices_product( $priced ) );
check( 'and not on the one that has none', false, Role_Based_Pricing::prices_product( $plain ) );
check( 'a variable product is priced when a variation is', true, Role_Based_Pricing::prices_product( $variable ) );

check( 'while the setting is off the plan still shows', false, Components::is_opted_out( $priced, '__disable_installments' ) );

set_settings( array( 'integration_role_pricing_hide_installments' => 'yes' ) );

check( 'switched on, the priced product reads as opted out', true, Components::is_opted_out( $priced, '__disable_installments' ) );
check( 'and the Store API carries no instalments for it', array(), Store_Api::build_product_data( $priced, 'strval' )['installments'] );
check( 'the product with no rule keeps its plan', false, Components::is_opted_out( $plain, '__disable_installments' ) );
check( 'and the Store API still carries it', 12, count( Store_Api::build_product_data( $plain, 'strval' )['installments'] ) );
check( 'the upfront discount is untouched by this one', false, Components::is_opted_out( $priced, '__disable_discount_main_price' ) );
// 10% off 300, through the `strval` formatter this harness passes in place of
// the Store API's own minor-unit one.
check( 'so the Store API still prices it upfront', '270', Store_Api::build_product_data( $priced, 'strval' )['discounts']['main']['price'] ?? null );

set_settings( array( 'integration_role_pricing_hide_discount' => 'yes' ) );

check( 'the second switch opts the same product out of the discount', true, Components::is_opted_out( $priced, '__disable_discount_main_price' ) );
check( 'the Store API drops the upfront price', null, Store_Api::build_product_data( $priced, 'strval' )['discounts']['main'] );
check( 'and the price engine stops discounting it', 300.0, Calculate_Values::get_discounted_price( $priced ) );
check( 'while the product with no rule is still discounted', 270.0, Calculate_Values::get_discounted_price( $plain ) );

set_settings( array(
	'integration_role_pricing_hide_installments' => 'no',
	'integration_role_pricing_hide_discount' => 'no',
) );
Role_Based_Pricing::flush();

check( 'both switched off, nothing is withheld', false, Components::is_opted_out( $priced, '__disable_discount_main_price' ) );

$GLOBALS['rcbp_test_rules'] = array();
Role_Based_Pricing::flush();

echo PHP_EOL, 'The storefront runtime parameters', PHP_EOL;

// What the runtime receives is printed into the page source, so the gate has
// to reach it too: a visitor must not find the price as a plain number there.
reset_gate();

$GLOBALS['wp_test_is_product'] = true;

// The same route a page builder takes: nothing resolves a product outside the
// loop, so the product is supplied through the filter meant for that.
add_filter( 'WC_Custom_Installments/Product/Set_Product', function() use ( $product ) {
	return $product;
} );

require_once __DIR__ . '/../admin/src/Core/Scripts.php';
require_once __DIR__ . '/../admin/src/Assets/Abstract_Assets.php';
require_once __DIR__ . '/../admin/src/Assets/Storefront_Assets.php';

$assets = new MeuMouse\WC_Custom_Installments\Assets\Storefront_Assets();
$params = $assets->frontend_params();

check( 'carry the price while the gate is open', 300.0, $params['product']['current_price'] );
check( 'and name the product', $product->get_id(), $params['product']['id'] );

add_filter( 'WC_Custom_Installments/Product/Price_Visible', '__return_false' );

$params = $assets->frontend_params();

check( 'carry no price once it closes', 0, $params['product']['current_price'] );
check( 'no regular price either', 0, $params['product']['regular_price'] );
check( 'and no product at all', 0, $params['product']['id'] );

reset_gate();

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
