<?php

/**
 * Campaigns harness.
 *
 * A campaign moves prices on products nobody opened: the wrong category
 * matched, a period off by the timezone, or a campaign beating a product's own
 * discount would each change what a whole section of the shop charges, with
 * nothing reporting it. The expected prices below are worked out by hand.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/campaigns-test.php
 *
 * @since 6.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository as Settings;
use MeuMouse\WC_Custom_Installments\Campaigns\Repository;
use MeuMouse\WC_Custom_Installments\Campaigns\Resolver;
use MeuMouse\WC_Custom_Installments\Campaigns\Rules;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Installments;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Values;
use MeuMouse\WC_Custom_Installments\Engine\Product_Config;

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

/**
 * A campaign's rules, from the defaults.
 *
 * @param array<string,mixed> $overrides | Parts to change, merged one level deep
 * @return array<string,mixed>
 */
function campaign_rules( $overrides ) {
	$rules = Rules::defaults();

	foreach ( $overrides as $key => $value ) {
		$rules[ $key ] = is_array( $value ) && is_array( $rules[ $key ] ?? null ) ? array_merge( $rules[ $key ], $value ) : $value;
	}

	return Rules::sanitize( $rules );
}

echo PHP_EOL, 'Cleaning what the editor sends', PHP_EOL;

$result = campaign_rules( array(
	'priority' => '15',
	'starts_at' => '2026-11-27T00:00',
	'ends_at' => '2026-11-30T23:59:30',
	'target' => array( 'scope' => 'categories', 'ids' => array( '10', 10, 0, -3, 'x' ), 'on_sale' => 'sometimes' ),
	'discount' => array( 'enabled' => 'yes', 'amount' => '12,5' ),
) );

check( 'no errors on a working campaign', array(), $result['errors'] );
check( 'the priority is a number', 15, $result['rules']['priority'] );
check( 'the list keeps positive, unique IDs', array( 10 ), $result['rules']['target']['ids'] );
check( 'an unknown sale filter means any', 'any', $result['rules']['target']['on_sale'] );
check( 'a comma is a decimal point', '12.5', $result['rules']['discount']['amount'] );
check( 'a date keeps its minutes and drops the seconds', '2026-11-30T23:59', $result['rules']['ends_at'] );

$all = campaign_rules( array(
	'target' => array( 'scope' => 'all', 'ids' => array( 10 ) ),
	'discount' => array( 'enabled' => true, 'amount' => '5' ),
) );

check( 'the whole store needs no list, and keeps none', array(), $all['rules']['target']['ids'] );
check( 'a date that is not one is dropped', '', campaign_rules( array( 'starts_at' => '2026-02-31T10:00' ) )['rules']['starts_at'] );

echo PHP_EOL, 'What cannot work', PHP_EOL;

$errors = campaign_rules( array(
	'starts_at' => '2026-12-01T00:00',
	'ends_at' => '2026-11-30T23:59',
	'target' => array( 'scope' => 'tags', 'ids' => array() ),
) )['errors'];

check( 'an end before the start', true, isset( $errors['ends_at'] ) );
check( 'a list scope with nothing chosen', true, isset( $errors['target'] ) );
check( 'a campaign that changes nothing', true, isset( $errors['effects'] ) );

$errors = campaign_rules( array(
	'target' => array( 'scope' => 'all' ),
	'discount' => array( 'enabled' => true, 'amount' => '0' ),
) )['errors'];

check( 'a discount of nothing', true, isset( $errors['discount'] ) );

$errors = campaign_rules( array(
	'target' => array( 'scope' => 'all' ),
	'discount' => array( 'enabled' => true, 'method' => 'percentage', 'amount' => '120' ),
) )['errors'];

check( 'more than 100% off', true, isset( $errors['discount'] ) );

$fixed = campaign_rules( array(
	'target' => array( 'scope' => 'all' ),
	'discount' => array( 'enabled' => true, 'method' => 'fixed', 'amount' => '120' ),
) )['errors'];

check( 'but 120.00 off is a fixed amount like any other', array(), $fixed );

$errors = campaign_rules( array(
	'target' => array( 'scope' => 'all' ),
	'installments' => array( 'enabled' => true ),
) )['errors'];

check( 'a plan that changes nothing', true, isset( $errors['installments'] ) );

$errors = campaign_rules( array(
	'target' => array( 'scope' => 'all' ),
	'installments' => array( 'enabled' => true, 'max' => '6', 'without_fee' => '10' ),
) )['errors'];

check( 'more interest-free instalments than instalments', true, isset( $errors['installments'] ) );

$errors = campaign_rules( array(
	'target' => array( 'scope' => 'all' ),
	'installments' => array( 'enabled' => true, 'interest' => 'single' ),
) )['errors'];

check( 'a single rate with no rate', true, isset( $errors['installments'] ) );

echo PHP_EOL, 'The period, in the store\'s timezone', PHP_EOL;

// São Paulo is three hours behind UTC in November (no daylight saving since
// 2019): midnight there is 03:00 UTC.
check( 'a start is the minute itself', gmmktime( 3, 0, 0, 11, 27, 2026 ), Rules::to_timestamp('2026-11-27T00:00') );
check( 'an end runs to the last second of its minute', gmmktime( 2, 59, 59, 12, 1, 2026 ), Rules::to_timestamp( '2026-11-30T23:59', true ) );
check( 'no date is no limit', 0, Rules::to_timestamp('') );

$entry = array( 'starts' => 1000, 'ends' => 2000 );

check( 'before its start a campaign is scheduled', 'scheduled', Rules::phase( $entry, 999 ) );
check( 'on its first second it runs', 'running', Rules::phase( $entry, 1000 ) );
check( 'on its last second it still runs', 'running', Rules::phase( $entry, 2000 ) );
check( 'and a second later it has ended', 'ended', Rules::phase( $entry, 2001 ) );
check( 'with no dates it always runs', 'running', Rules::phase( array( 'starts' => 0, 'ends' => 0 ), 5 ) );

echo PHP_EOL, 'Compiling', PHP_EOL;

// Category 10 has 11 beneath it, and 12 beneath 11.
$GLOBALS['wp_test_term_children'] = array( 10 => array( 11, 12 ), 11 => array( 12 ) );

$compiled = Rules::compile( 7, 'Roupas', campaign_rules( array(
	'target' => array( 'scope' => 'categories', 'ids' => array( 10 ), 'exclude_categories' => array( 11 ) ),
	'discount' => array( 'enabled' => true, 'amount' => '20' ),
) )['rules'] );

check( 'a category brings its subcategories', array( 10, 11, 12 ), $compiled['target']['ids'] );
check( 'and so does an excluded one', array( 11, 12 ), $compiled['target']['exclude_categories'] );
check( 'a disabled effect compiles to nothing', null, $compiled['installments'] );

$sorted = Rules::sort( array(
	array( 'id' => 1, 'priority' => 10, 'starts' => 500 ),
	array( 'id' => 2, 'priority' => 20, 'starts' => 100 ),
	array( 'id' => 3, 'priority' => 10, 'starts' => 900 ),
	array( 'id' => 4, 'priority' => 10, 'starts' => 900 ),
) );

check( 'higher priority first, then the latest start, then the newest', array( 2, 4, 3, 1 ), array_column( $sorted, 'id' ) );

echo PHP_EOL, 'Which products a campaign reaches', PHP_EOL;

// The whole store: 10% off upfront, 12 instalments, 3 interest-free, 2% a month.
add_filter( 'WC_Custom_Installments/Features/Is_Available', '__return_true' );
Features::flush();

Settings::save_settings( array(
	'enable_all_discount_options' => 'yes',
	'discount_main_price' => '10',
	'product_price_discount_method' => 'percentage',
	'max_qtd_installments' => '12',
	'max_qtd_installments_without_fee' => '3',
	'fee_installments_global' => '2.0',
	'min_value_installments' => '0',
) );

new Resolver();

$GLOBALS['wp_test_product_terms'] = array(
	100 => array( 'product_cat' => array( 12 ) ),
	101 => array( 'product_cat' => array( 30 ) ),
	102 => array( 'product_cat' => array( 12 ), 'product_tag' => array( 50 ) ),
	103 => array( 'product_cat' => array( 12 ) ),
	104 => array( 'product_tag' => array( 50 ) ),
	107 => array( 'product_cat' => array( 40 ) ),
	108 => array( 'product_cat' => array( 40 ) ),
);

// Product 103 has a discount of its own: 5.00 off.
$GLOBALS['wp_test_meta'] = array(
	103 => array(
		'enable_discount_per_unit' => 'yes',
		'discount_per_unit_method' => 'fixed',
		'unit_discount_amount' => '5',
	),
);

$november = Rules::to_timestamp('2026-11-28T12:00');

/**
 * Put campaigns in the index, compiled as the repository would.
 *
 * @param array<int,array<string,mixed>> $campaigns | ID => rules overrides
 * @return void
 */
function index_campaigns( $campaigns ) {
	$entries = array();

	foreach ( $campaigns as $id => $overrides ) {
		$result = campaign_rules( $overrides );

		if ( ! empty( $result['errors'] ) ) {
			throw new RuntimeException( 'Campaign ' . $id . ' does not validate: ' . implode( ' ', $result['errors'] ) );
		}

		$entries[] = Rules::compile( $id, 'Campaign ' . $id, $result['rules'] );
	}

	update_option( Repository::INDEX_OPTION, Rules::sort( $entries ) );
	Resolver::flush();
}

/**
 * Judge the campaigns at a given moment.
 *
 * @param int $moment | Unix timestamp
 * @return void
 */
function at_moment( $moment ) {
	$GLOBALS['wp_test_filters']['WC_Custom_Installments/Campaigns/Now'] = array();
	add_filter( 'WC_Custom_Installments/Campaigns/Now', static function() use ( $moment ) {
		return $moment;
	} );
	Resolver::flush();
}

$black_friday = array(
	'starts_at' => '2026-11-27T00:00',
	'ends_at' => '2026-11-30T23:59',
	'target' => array( 'scope' => 'categories', 'ids' => array( 10 ), 'exclude_products' => array( 102 ) ),
	'discount' => array( 'enabled' => true, 'method' => 'percentage', 'amount' => '20' ),
);

index_campaigns( array( 1 => $black_friday ) );
at_moment( $november );

check_money( 'a product in a subcategory: 20% off 100', 80.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 100 ) ) ) );
check_money( 'one in another category keeps the store\'s 10%', 90.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 101 ) ) ) );
check_money( 'an excluded product keeps the store\'s', 90.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 102 ) ) ) );
check_money( 'a product\'s own discount wins: 5.00 off', 95.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 103 ) ) ) );
check_money( 'a variation follows its product\'s category', 80.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 900, 'parent_id' => 100, 'type' => 'variation' ) ) ) );
check_money( 'the bank slip takes the campaign\'s discount too', 80.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 100 ) ), 'ticket' ) );
check( 'the rule says where it came from', array( 'campaign', 1 ), array_values( array_intersect_key( (array) Product_Config::discount_rule( 100 ), array_flip( array( 'source', 'source_id' ) ) ) ) );

at_moment( Rules::to_timestamp('2026-11-26T23:59') );
check_money( 'a minute before it starts, the store\'s price', 90.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 100 ) ) ) );

at_moment( Rules::to_timestamp( '2026-11-30T23:59', true ) + 1 );
check_money( 'a second after it ends, the store\'s price', 90.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 100 ) ) ) );

at_moment( $november );

echo PHP_EOL, 'When campaigns overlap', PHP_EOL;

index_campaigns( array(
	1 => $black_friday,
	2 => array(
		'priority' => 20,
		'target' => array( 'scope' => 'products', 'ids' => array( 100 ) ),
		'discount' => array( 'enabled' => true, 'method' => 'fixed', 'amount' => '30' ),
	),
	3 => array(
		'target' => array( 'scope' => 'tags', 'ids' => array( 50 ) ),
		'installments' => array( 'enabled' => true, 'max' => '6', 'without_fee' => '6' ),
	),
) );

check_money( 'the higher priority wins: 30.00 off', 70.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 100 ) ) ) );
check_money( 'choosing a product chooses its variations', 70.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 900, 'parent_id' => 100, 'type' => 'variation' ) ) ) );

// 102 is left out of the Black Friday discount but carries tag 50: it gets
// campaign 3's plan and keeps the store's discount. Effects are decided apart.
$plan = Calculate_Installments::installments_list( array(), 600.00, wc_test_product( 600, array( 'id' => 102 ) ) );

check( 'an instalment campaign sets the count', 6, count( $plan ) );
check( 'and makes every one interest-free', array( 'no-fee' ), array_values( array_unique( array_column( $plan, 'class' ) ) ) );
check_money( 'while the discount stays the store\'s', 540.00, Calculate_Values::get_discounted_price( wc_test_product( 600, array( 'id' => 102 ) ) ) );
check( 'a product outside it keeps the store\'s 12', 12, count( Calculate_Installments::installments_list( array(), 600.00, wc_test_product( 600, array( 'id' => 101 ) ) ) ) );

echo PHP_EOL, 'Products on sale', PHP_EOL;

$GLOBALS['wp_test_products'] = array(
	107 => wc_test_product( 100, array( 'id' => 107, 'on_sale' => true ) ),
	108 => wc_test_product( 100, array( 'id' => 108 ) ),
);

index_campaigns( array(
	4 => array(
		'target' => array( 'scope' => 'categories', 'ids' => array( 40 ), 'on_sale' => 'only' ),
		'discount' => array( 'enabled' => true, 'amount' => '15' ),
	),
) );

check_money( 'a product on sale gets it: 15% off', 85.00, Calculate_Values::get_discounted_price( $GLOBALS['wp_test_products'][107] ) );
check_money( 'one at full price does not', 90.00, Calculate_Values::get_discounted_price( $GLOBALS['wp_test_products'][108] ) );

$GLOBALS['wp_test_products'] = array();

echo PHP_EOL, 'A campaign\'s own interest', PHP_EOL;

index_campaigns( array(
	5 => array(
		'target' => array( 'scope' => 'products', 'ids' => array( 109 ) ),
		'installments' => array(
			'enabled' => true,
			'without_fee' => '1',
			'interest' => 'table',
			'fees' => array( 4 => array( 'amount' => '3', 'fixed' => '2.99' ) ),
		),
	),
	6 => array(
		'target' => array( 'scope' => 'products', 'ids' => array( 110 ) ),
		'installments' => array( 'enabled' => true, 'interest' => 'single', 'fee' => '1.99' ),
	),
) );

$plan = array_column( Calculate_Installments::installments_list( array(), 100.00, wc_test_product( 100, array( 'id' => 109 ) ) ), null, 'installments_total' );

// (100 + 3.00 + 2.99) / 4 = 26.4975, billed as 26.50 x 4.
check_money( 'its table charges R$ 2,99 + 3% in 4x', 106.00, $plan[4]['final_price'] );
check( 'and a count it leaves out is interest-free', 'no-fee', $plan[5]['class'] );

// 500 over 6 at 1.99% a month, compounding: 89.2334...
$plan = array_column( Calculate_Installments::installments_list( array(), 500.00, wc_test_product( 500, array( 'id' => 110 ) ) ), null, 'installments_total' );

check_money( 'its single rate compounds: 500 in 6x at 1.99%', 89.23, $plan[6]['installment_price'] );
check( 'and the interest-free count stays the store\'s', 'no-fee', $plan[3]['class'] );

echo PHP_EOL, 'Without the licence', PHP_EOL;

index_campaigns( array( 1 => $black_friday ) );

$GLOBALS['wp_test_filters'] = array();
Features::flush();
at_moment( $november );

check_money( 'a stored campaign stops applying', 90.00, Calculate_Values::get_discounted_price( wc_test_product( 100, array( 'id' => 100 ) ) ) );
check( 'and no rule reaches the engine', null, Product_Config::discount_rule( 100 ) );

$GLOBALS['wp_test_meta'] = array();
$GLOBALS['wp_test_product_terms'] = array();
$GLOBALS['wp_test_term_children'] = array();

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
