<?php

/**
 * Instalment and discount maths harness.
 *
 * These are the numbers a customer reads before deciding to buy, and the ones a
 * store owner reconciles against their acquirer's statement. A rounding change
 * here does not throw an error anywhere — it just quietly charges a different
 * amount on every product in the shop, which is why the expected values below
 * are worked out by hand rather than captured from the code.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/engine-test.php
 *
 * @since 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/src/Frontend/Styles.php';

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Installments;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Values;
use MeuMouse\WC_Custom_Installments\Frontend\Styles;

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
 * Assert that a number matches to the cent.
 *
 * @param string $label | What is being checked
 * @param float $expected | Expected value
 * @param float $actual | Actual value
 * @return void
 */
function check_money( $label, $expected, $actual ) {
	check( $label, round( (float) $expected, 2 ), round( (float) $actual, 2 ) );
}

echo PHP_EOL, 'Instalments without interest', PHP_EOL;

check_money( '900 over 3 is 300 each', 300.00, Calculate_Values::calculate_installment_no_fee( 900, 3 ) );
check_money( '100 over 3 leaves a repeating decimal', 33.333333, Calculate_Values::calculate_installment_no_fee( 100, 3 ) );
check_money( 'a single instalment is the price itself', 97.00, Calculate_Values::calculate_installment_no_fee( 97, 1 ) );
check_money( 'zero instalments cannot be divided by', 0.0, Calculate_Values::calculate_installment_no_fee( 97, 0 ) );

echo PHP_EOL, 'Instalments with compound interest', PHP_EOL;

// PMT for 1000 over 12 at 2% a month:
//   1000 * 0.02 * 1.02^12 / (1.02^12 - 1) = 94.5596...
check_money( '1000 over 12 at 2% a month', 94.56, Calculate_Values::calculate_installment_with_fee( 1000, 2, 12 ) );

// 500 over 6 at 1.99%:
//   1.0199^6 = 1.125499, so 500 * 0.0199 * 1.125499 / 0.125499 = 89.2334...
check_money( '500 over 6 at 1.99% a month', 89.23, Calculate_Values::calculate_installment_with_fee( 500, 1.99, 6 ) );

check_money( 'a zero rate falls back to a plain division', 250.00, Calculate_Values::calculate_installment_with_fee( 1000, 0, 4 ) );
check_money( 'zero instalments cannot be divided by', 0.0, Calculate_Values::calculate_installment_with_fee( 1000, 2, 0 ) );

echo PHP_EOL, 'Instalments with a flat surcharge', PHP_EOL;

// With a per-instalment table the rate is a surcharge on the whole price, the
// way an acquirer quotes it: (1000 * 0.03 + 1000) / 10 = 103.
Repository::save_settings( array( 'set_fee_per_installment' => 'yes' ) );
add_filter( 'WC_Custom_Installments/Features/Is_Available', '__return_true' );
Features::flush();
Repository::save_settings( array( 'set_fee_per_installment' => 'yes' ) );

check_money( 'a per-instalment rate is a flat surcharge', 103.00, Calculate_Values::calculate_installment_with_fee( 1000, 3, 10 ) );

echo PHP_EOL, 'The total paid', PHP_EOL;

// The instalment is rounded before being multiplied, because that is what the
// customer is actually billed: 33.333... becomes 33.33, and 3 x 33.33 is 99.99.
check_money( 'the instalment is rounded before multiplying', 99.99, Calculate_Values::calculate_final_price( 33.333333, 3 ) );
check_money( 'a whole instalment multiplies cleanly', 900.00, Calculate_Values::calculate_final_price( 300, 3 ) );

echo PHP_EOL, 'Discounts', PHP_EOL;

check_money( '10% off 97', 87.30, Calculate_Values::calculate_discounted_price( 97, 10 ) );
check_money( 'no discount leaves the price alone', 97.00, Calculate_Values::calculate_discounted_price( 97, 0 ) );
check_money( 'a percentage discount', 87.30, Calculate_Values::calculate_price_with_discount( 97, 'percentage', 10 ) );
check_money( 'a fixed discount', 87.00, Calculate_Values::calculate_price_with_discount( 97, 'fixed', 10 ) );

echo PHP_EOL, 'Building the plan', PHP_EOL;

// Back to the free tier for the plan tests.
$GLOBALS['wp_test_filters'] = array();
Features::flush();

Repository::reset_settings();
Repository::save_settings( array(
	'max_qtd_installments' => '12',
	'max_qtd_installments_without_fee' => '3',
	'fee_installments_global' => '2.0',
	'min_value_installments' => '20',
) );

$plan = Calculate_Installments::installments_list( array(), 300.00, wc_test_product( 300.00 ) );

check( 'the plan stops where the instalment gets too small', 12, count( $plan ) );
check( 'the first row is the single payment', 1, $plan[0]['installments_total'] );
check_money( 'the single payment is the price', 300.00, $plan[0]['installment_price'] );
check( 'the interest-free rows are marked', 'no-fee', $plan[2]['class'] );
check( 'the fourth row starts charging interest', 'fee-included', $plan[3]['class'] );
check_money( '3 interest-free instalments of 100', 100.00, $plan[2]['installment_price'] );

echo PHP_EOL, 'The minimum instalment', PHP_EOL;

Repository::save_settings( array( 'min_value_installments' => '50' ) );

$plan = Calculate_Installments::installments_list( array(), 300.00, wc_test_product( 300.00 ) );
$counts = array_column( $plan, 'installments_total' );

check( 'the single payment is always offered', true, in_array( 1, $counts, true ) );
check( 'nothing below the minimum is offered', true, min( array_column( $plan, 'installment_price' ) ) >= 50 );
check( 'a 300 product stops at 6 instalments of 50', 6, max( $counts ) );

echo PHP_EOL, 'A product nobody can buy', PHP_EOL;

check( 'a product with no price has no plan', array(), Calculate_Installments::installments_list( array(), null, wc_test_product( 0 ) ) );

echo PHP_EOL, 'The stylesheet across a licence change', PHP_EOL;

/**
 * Read one element's desktop declarations out of the generated stylesheet.
 *
 * @param string $css | The generated stylesheet
 * @return string
 */
function desktop_rule( $css ) {
	preg_match( '/min-width.*?\.woo-custom-installments-offer\{([^}]*)\}/s', $css, $matches );

	return $matches[1] ?? '';
}

$styles = new Styles();

add_filter( 'WC_Custom_Installments/Features/Is_Available', '__return_true' );
Features::flush();

$design = Repository::get('elements_design');
$design['discount_pix']['styles']['desktop']['background_color'] = '#ff0000';
Repository::save_settings( array( 'elements_design' => $design ) );

check( 'a licensed store gets its own colour', true, str_contains( desktop_rule( $styles->build_css() ), 'background-color:#ff0000' ) );

// The licence lapses.
$GLOBALS['wp_test_filters'] = array();
Features::flush();

$lapsed = desktop_rule( $styles->build_css() );

// The badge is a green pill by design. Dropping its background on an
// unlicensed store would leave the storefront looking broken rather than
// un-upgraded, which is not what the gate is for.
check( 'a lapsed store falls back to the shipped colour', true, str_contains( $lapsed, 'background-color:rgba(34, 197, 94, 0.15)' ) );
check( 'and keeps its spacing', true, str_contains( $lapsed, 'padding:0.65rem 0.85rem 0.65rem 0.85rem' ) );
check( 'while the customisation stays stored', '#ff0000', Repository::get('elements_design')['discount_pix']['styles']['desktop']['background_color'] );

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
