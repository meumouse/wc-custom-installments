<?php

/**
 * Feature gate and hook alias harness.
 *
 * Two guarantees are worth a test of their own, because breaking either one is
 * silent and expensive:
 *
 *   1. A Pro field cannot be written while its feature is unavailable. The
 *      browser is told the field is locked, but the browser is not what
 *      enforces it — this is.
 *   2. Every hook still fires under its 5.x name, so the snippets store owners
 *      wrote against the previous plugin keep working.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/feature-gate-test.php
 *
 * @since 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Core\Features;

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

echo PHP_EOL, 'Feature gate', PHP_EOL;

check( 'a free feature is available', true, Features::is_available('enable_installments_all_products') );
check( 'a Pro feature is not available without the add-on', false, Features::is_available('sale_badge') );
check( 'a Pro feature reports its tier', true, Features::is_pro('sale_badge') );
check( 'an unknown feature is not treated as Pro', false, Features::is_pro('something_nobody_registered') );

echo PHP_EOL, 'Settings schema', PHP_EOL;

$schema = Registry::get_schema();
$definitions = Registry::get_field_definitions();

check( 'the schema has sections', true, count( $schema ) > 0 );
check( 'a gated field is marked locked', true, ! empty( $definitions['enable_sale_badge']['locked'] ) );
check( 'an ungated field is not locked', false, ! empty( $definitions['max_qtd_installments']['locked'] ) );

echo PHP_EOL, 'Persistence without a licence', PHP_EOL;

Repository::save_settings( array(
	'max_qtd_installments' => '18',
	'enable_sale_badge'    => 'yes',
) );

check( 'a free field is written', '18', Repository::get('max_qtd_installments') );
check( 'a locked field is refused', 'no', Repository::get('enable_sale_badge') );
check( 'a locked toggle is never active', false, Repository::is_active('enable_sale_badge') );

echo PHP_EOL, 'Sanitization', PHP_EOL;

Repository::save_settings( array(
	'product_price_discount_method' => 'not-an-option',
	'enable_all_discount_options'   => 'whatever',
) );

check( 'an out-of-range select falls back to its default', 'percentage', Repository::get('product_price_discount_method') );
check( 'an unrecognised toggle value reads as off', 'no', Repository::get('enable_all_discount_options') );

echo PHP_EOL, 'Persistence with a licence', PHP_EOL;

// Stand in for the Pro add-on answering the gate.
add_filter( 'WC_Custom_Installments/Features/Is_Available', function() {
	return true;
} );

Features::flush();

check( 'the gate opens', true, Features::is_available('sale_badge') );

Repository::save_settings( array( 'enable_sale_badge' => 'yes' ) );

check( 'the field is now written', 'yes', Repository::get('enable_sale_badge') );
check( 'the toggle is now active', true, Repository::is_active('enable_sale_badge') );

echo PHP_EOL, 'Hook aliases', PHP_EOL;

check(
	'a 5.x name is derived from the current one',
	'Woo_Custom_Installments/Price/Group_Classes',
	Hook_Aliases::legacy_name('WC_Custom_Installments/Price/Group_Classes')
);

check(
	'a renamed hook keeps its own 5.x name',
	'Woo_Custom_Installments/Before_Init',
	Hook_Aliases::legacy_name('WC_Custom_Installments/Core/Before_Init')
);

add_filter( 'Woo_Custom_Installments/Price/Group_Classes', function( $classes ) {
	return $classes . ' from-legacy';
} );

add_filter( 'WC_Custom_Installments/Price/Group_Classes', function( $classes ) {
	return $classes . ' from-current';
} );

check(
	'both listeners run, current name first',
	'wci-group from-current from-legacy',
	Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Group_Classes', 'wci-group' )
);

$fired = array();

add_action( 'Woo_Custom_Installments/Product/After_Price', function( $product_id ) use ( &$fired ) {
	$fired[] = 'legacy:' . $product_id;
} );

Hook_Aliases::do_action( 'WC_Custom_Installments/Product/After_Price', 42 );

check( 'a 5.x action listener receives its arguments', array( 'legacy:42' ), $fired );

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
