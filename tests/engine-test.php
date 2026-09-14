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
 * @since 6.0.0
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/src/Frontend/Styles.php';

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Installments;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Values;
use MeuMouse\WC_Custom_Installments\Engine\Product_Config;
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

echo PHP_EOL, 'A fixed amount on top of the rate', PHP_EOL;

// Some acquirers quote a row as "R$ 2,99 + 3%". The fixed amount is added once
// to the total, not to each instalment:
//   100 + 3.00 + 2.99 = 105.99, over 4 = 26.4975, billed as 26.50 x 4 = 106.00.
check_money( 'R$ 2,99 + 3% on 100 in 4x', 26.4975, Calculate_Values::calculate_installment_with_fee( 100, 3, 4, true, 2.99 ) );
check_money( 'and the total is the rounded instalment times four', 106.00, Calculate_Values::calculate_final_price( 26.4975, 4 ) );

// A row with only a fixed amount still charges: (100 + 2.99) / 4 = 25.7475.
check_money( 'R$ 2,99 + 0% still charges', 25.7475, Calculate_Values::calculate_installment_with_fee( 100, 0, 4, true, 2.99 ) );

// The single compounding rate has no fixed amount to add.
check_money( 'the compound formula ignores a fixed amount', 94.56, Calculate_Values::calculate_installment_with_fee( 1000, 2, 12, false, 50 ) );

Repository::save_settings( array(
	'set_fee_per_installment' => 'yes',
	'max_qtd_installments' => '6',
	'max_qtd_installments_without_fee' => '1',
	'min_value_installments' => '0',
	'custom_fee_installments' => array(
		2 => array( 'amount' => '' ),
		4 => array( 'amount' => '3,0', 'fixed' => '2,99' ),
		5 => array( 'amount' => '', 'fixed' => '' ),
		6 => array( 'fixed' => '2.99' ),
	),
) );

$table = Repository::get('custom_fee_installments');

check( 'the table keeps both halves of a row, commas read as points', array( 'amount' => '3', 'fixed' => '2.99' ), $table[4] ?? null );
check( 'a row with only a fixed amount is kept, at 0%', array( 'amount' => '0', 'fixed' => '2.99' ), $table[6] ?? null );
check( 'an empty row is dropped', array( 4, 6 ), array_keys( $table ) );

$plan = Calculate_Installments::installments_list( array(), 100.00, wc_test_product( 100.00 ) );
$by_count = array_column( $plan, null, 'installments_total' );

check( 'a count with no row is interest-free', 'no-fee', $by_count[5]['class'] );
check( 'a fixed-only row carries interest', 'fee-included', $by_count[6]['class'] );
check_money( 'the 4x row is billed 26.50 each', 106.00, $by_count[4]['final_price'] );
check( 'the storefront gets the fixed amount per count', 2.99, Helpers::get_fee_fixed( wc_test_product( 100.00 ), 4 ) );
check( 'and nothing where the row has none', 0.0, Helpers::get_fee_fixed( wc_test_product( 100.00 ), 3 ) );

Repository::reset_settings();
Repository::save_settings( array( 'set_fee_per_installment' => 'yes' ) );

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

echo PHP_EOL, 'The discounted price', PHP_EOL;

$sale_selector = '.woo-custom-installments-group .woo-custom-installments-group-main-price .woo-custom-installments-price.has-discount ~ .woo-custom-installments-price.sale-price';

check( 'is not ordered on its own', false, str_contains( $styles->build_css(), $sale_selector . '{order:' ) );
check( 'writes nothing while it inherits', false, str_contains( $styles->build_css(), $sale_selector ) );

// The design field is written only while the licence is valid.
add_filter( 'WC_Custom_Installments/Features/Is_Available', '__return_true' );
Features::flush();

$design = Repository::get('elements_design');
$design['sale_price']['styles']['desktop']['font_color'] = '#e11d48';
$design['sale_price']['styles']['desktop']['font_size'] = '1.5';
Repository::save_settings( array( 'elements_design' => $design ) );

$css = $styles->build_css();

check( 'writes only what was set on it', true, str_contains( $css, $sale_selector . '{font-size:1.5rem;color:#e11d48;}' ) );
check( 'down to the amount WooCommerce wraps it in', true, str_contains( $css, $sale_selector . ' .amount{font-size:1.5rem;color:#e11d48;}' ) );
// The order rules and the mobile block come before the desktop one.
check( 'and leaves the mobile rules inheriting', false, str_contains( (string) strstr( $css, '@media screen and (min-width:993px)', true ), 'has-discount ~' ) );

echo PHP_EOL, 'A variable product on sale', PHP_EOL;

$variable = wc_test_product( 0, array(
	'type' => 'variable',
	'variation_prices' => array(
		'price' => array( 11 => '150', 12 => '97', 13 => '110' ),
		'regular_price' => array( 11 => '150', 12 => '120', 13 => '100' ),
		'sale_price' => array( 11 => '', 12 => '97', 13 => '' ),
	),
) );

// The lowest regular price is 100, but it belongs to another variation:
// striking it out beside 97 would promise a discount nobody gets.
check( 'strikes out the cheapest variation\'s own regular price', 120.0, Helpers::cheapest_variation_regular_price( $variable ) );
check( 'and a simple product has none', 0.0, Helpers::cheapest_variation_regular_price( wc_test_product( 50 ) ) );

echo PHP_EOL, 'A variation\'s own discount', PHP_EOL;

add_filter( 'WC_Custom_Installments/Features/Is_Available', '__return_true' );
Features::flush();

Repository::save_settings( array(
	'enable_all_discount_options' => 'yes',
	'discount_main_price' => '10',
	'product_price_discount_method' => 'percentage',
) );

// The store takes 10% off and product 20 takes 20%. Variation 21 has no
// setting of its own, 22 takes 50.00 off itself, 23 chose the store's.
$GLOBALS['wp_test_meta'] = array(
	20 => array(
		'enable_discount_per_unit' => 'yes',
		'discount_per_unit_method' => 'percentage',
		'unit_discount_amount' => '20',
	),
	22 => array(
		'enable_discount_per_unit' => 'yes',
		'discount_per_unit_method' => 'fixed',
		'unit_discount_amount' => '50',
	),
	23 => array( 'enable_discount_per_unit' => 'no' ),
);

$follows = wc_test_product( 100, array( 'id' => 21, 'parent_id' => 20, 'type' => 'variation' ) );
$own = wc_test_product( 120, array( 'id' => 22, 'parent_id' => 20, 'type' => 'variation' ) );
$store = wc_test_product( 100, array( 'id' => 23, 'parent_id' => 20, 'type' => 'variation' ) );

check( 'a variation with no setting reads its product', 20, Calculate_Values::get_discount_source_id( 21, 20 ) );
check( 'one with a discount of its own reads itself', 22, Calculate_Values::get_discount_source_id( 22, 20 ) );
check( 'so does one set to the store\'s', 23, Calculate_Values::get_discount_source_id( 23, 20 ) );
check( 'and a simple product reads itself', 20, Calculate_Values::get_discount_source_id( 20, 0 ) );

check_money( 'following the product: 20% off 100', 80.00, Calculate_Values::get_discounted_price( $follows ) );
check_money( 'its own: 50 off 120', 70.00, Calculate_Values::get_discounted_price( $own ) );
check_money( 'the store\'s: 10% off 100', 90.00, Calculate_Values::get_discounted_price( $store ) );

$variable = wc_test_product( 0, array(
	'id' => 20,
	'type' => 'variable',
	'variation_prices' => array(
		'price' => array( 21 => '100', 22 => '120', 23 => '100' ),
	),
) );

// The cheapest variations cost 100, but the pricier 22 gets to 70 upfront.
check_money( 'the variable product shows its lowest upfront price', 70.00, Calculate_Values::get_discounted_price( $variable ) );
check_money( 'and saves what that variation saves: 120 - 70', 50.00, Calculate_Values::get_pix_economy( $variable ) );

$cart = new class( array(
	array( 'data' => $follows, 'quantity' => 1 ),
	array( 'data' => $own, 'quantity' => 2 ),
) ) {

	/**
	 * Cart lines.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $items;


	/**
	 * Build the cart.
	 *
	 * @param array<int,array<string,mixed>> $items | Cart lines
	 */
	public function __construct( $items ) {
		$this->items = $items;
	}


	/**
	 * The cart lines.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_cart() {
		return $this->items;
	}


	/**
	 * The shipping total.
	 *
	 * @return float
	 */
	public function get_shipping_total() {
		return 0.0;
	}
};

// 20% of 100 for 21, and 50.00 off each of two for 22: 20 + 100.
check_money( 'the cart takes each variation\'s discount', 120.00, Calculate_Values::calculate_total_discount( $cart ) );

$GLOBALS['wp_test_meta'][20]['__disable_discount_main_price'] = 'yes';
check_money( 'hiding the product\'s discount hides every variation\'s', 100.00, Calculate_Values::get_discounted_price( $variable ) );
unset( $GLOBALS['wp_test_meta'][20]['__disable_discount_main_price'] );

// Without per-product discounts every variation takes the store's 10%.
$GLOBALS['wp_test_filters'] = array();
Features::flush();

check_money( 'without the add-on the cheapest price takes the store\'s', 90.00, Calculate_Values::get_discounted_price( $variable ) );

$GLOBALS['wp_test_meta'] = array();

echo PHP_EOL, 'Rules from outside the product', PHP_EOL;

// A campaign joins through these two filters. Whatever answers them has to
// reach every price the engine produces, or the storefront shows one number
// and the cart charges another.
check( 'with nothing of its own a product follows the store', null, Product_Config::discount_rule( 30 ) );

add_filter( 'WC_Custom_Installments/Product/Discount_Rule', static function( $rule, $product_id ) {
	return null === $rule && 30 === $product_id
		? array( 'method' => 'percentage', 'amount' => '25', 'source' => 'test' )
		: $rule;
}, 10, 2 );

check_money( 'a rule from outside discounts the product: 25% off 80', 60.00, Calculate_Values::get_discounted_price( wc_test_product( 80, array( 'id' => 30 ) ) ) );
check_money( 'and the rest keep the store\'s 10%', 72.00, Calculate_Values::get_discounted_price( wc_test_product( 80, array( 'id' => 31 ) ) ) );

Repository::save_settings( array(
	'max_qtd_installments' => '12',
	'max_qtd_installments_without_fee' => '3',
	'fee_installments_global' => '2.0',
	'min_value_installments' => '20',
) );

add_filter( 'WC_Custom_Installments/Product/Installments_Config', static function( $config, $product ) {
	if ( $product && 40 === $product->get_id() ) {
		$config['max'] = 6;
		$config['without_fee'] = 6;
	}

	return $config;
}, 10, 2 );

$plan = Calculate_Installments::installments_list( array(), 600.00, wc_test_product( 600.00, array( 'id' => 40 ) ) );

check( 'a plan from outside sets the count', 6, count( $plan ) );
check( 'and every instalment is interest-free', array( 'no-fee' ), array_values( array_unique( array_column( $plan, 'class' ) ) ) );
check( 'another product keeps the store\'s plan', 12, count( Calculate_Installments::installments_list( array(), 600.00, wc_test_product( 600.00, array( 'id' => 41 ) ) ) ) );
check( 'and its fourth instalment pays interest', 2.0, Helpers::get_fee( wc_test_product( 600.00, array( 'id' => 41 ) ), 4 ) );

$GLOBALS['wp_test_filters'] = array();
Features::flush();

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
