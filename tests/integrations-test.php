<?php

/**
 * The gate that keeps a hidden price hidden.
 *
 * Everything the plugin prints is derived from the price, and a store can be
 * told by another plugin to show no prices at all. There is one gate for that,
 * `Helpers::is_price_visible()`, and what matters is its reach: a gate the
 * instalment list honours while the payment methods block, the Store API and
 * the storefront runtime parameters ignore it would leak the price it was
 * meant to withhold, and nothing would report it.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/integrations-test.php
 *
 * @since 6.0.0
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/src/Integrations/Role_Based_Pricing.php';

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Installments;
use MeuMouse\WC_Custom_Installments\Frontend\Components;
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

$product = wc_test_product( 300 );

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
