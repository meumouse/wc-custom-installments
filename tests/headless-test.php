<?php

/**
 * Headless mode and Store API harness.
 *
 * A headless store gets its numbers from the Store API and nothing from the
 * storefront. So this proves both halves: with the mode on, no class that
 * prints markup is built while the admin, the routes and the pricing logic
 * are; and the Store API data matches what the storefront would have printed.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/headless-test.php
 *
 * @since 6.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Frontend\Headless_Mode;
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
 * Amounts the way the Store API returns them: cents, as a string.
 *
 * @param float $value | Amount
 * @return string
 */
function cents( $value ) {
	return (string) (int) round( (float) $value * 100 );
}

/**
 * A cart with one line.
 */
class Test_Cart {

	/**
	 * Cart lines.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $items;

	/**
	 * Shipping total.
	 *
	 * @var float
	 */
	private $shipping;


	/**
	 * Build a cart.
	 *
	 * @param WC_Product $product | Product in the cart
	 * @param int $quantity | Quantity
	 * @param float $shipping | Shipping total
	 */
	public function __construct( $product, $quantity, $shipping ) {
		$this->items = array( array( 'data' => $product, 'quantity' => $quantity ) );
		$this->shipping = $shipping;
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
	 * The lines' total.
	 *
	 * @return float
	 */
	public function get_cart_contents_total() {
		$total = 0;

		foreach ( $this->items as $item ) {
			$total += $item['data']->get_price() * $item['quantity'];
		}

		return $total;
	}


	/**
	 * The shipping total.
	 *
	 * @return float
	 */
	public function get_shipping_total() {
		return $this->shipping;
	}
}

$money = 'cents';

echo PHP_EOL, 'The setting', PHP_EOL;

$definitions = Registry::get_field_definitions();

check( 'is a toggle', 'toggle', $definitions['headless_mode']['type'] ?? '' );
check( 'is off on a new store', false, Headless_Mode::is_enabled() );

$settings = Repository::get_settings();
$settings['headless_mode'] = 'yes';
Repository::save_settings( $settings );

check( 'is saved through the settings endpoint', 'yes', Repository::get('headless_mode') );

set_settings( array( 'headless_mode' => 'no' ) );

echo PHP_EOL, 'What gets built', PHP_EOL;

$classes = array(
	'MeuMouse\WC_Custom_Installments\Frontend\Render_Elements',
	'MeuMouse\WC_Custom_Installments\Frontend\Shortcodes',
	'MeuMouse\WC_Custom_Installments\Frontend\Styles',
	'MeuMouse\WC_Custom_Installments\Frontend\Templates',
	'MeuMouse\WC_Custom_Installments\Assets\Storefront_Assets',
	'MeuMouse\WC_Custom_Installments\Integrations\Elementor',
	'MeuMouse\WC_Custom_Installments\Integrations\Elementor\Inject_Controllers',
	'MeuMouse\WC_Custom_Installments\Integrations\Woodmart',
	'MeuMouse\WC_Custom_Installments\Admin\Menu',
	'MeuMouse\WC_Custom_Installments\Rest\Settings_Save',
	'MeuMouse\WC_Custom_Installments\Integrations\Store_Api',
	'MeuMouse\WC_Custom_Installments\Integrations\Tiered_Pricing_Table',
	'MeuMouse\WC_Custom_Installments\Pro\Engine\Gateway_Rates',
);

check( 'everything, while the mode is off', $classes, Headless_Mode::maybe_withhold_storefront( $classes ) );

set_settings( array( 'headless_mode' => 'yes' ) );

$kept = Headless_Mode::maybe_withhold_storefront( $classes );

check( 'no markup, once it is on', array(), array_values( array_intersect( $kept, Headless_Mode::STOREFRONT_CLASSES ) ) );

// The routes are the point of a headless store, and the cart fees and tier
// prices are what WooCommerce charges: none of it is markup.
check( 'the settings screen stays', true, in_array( 'MeuMouse\WC_Custom_Installments\Admin\Menu', $kept, true ) );
check( 'the REST routes stay', true, in_array( 'MeuMouse\WC_Custom_Installments\Rest\Settings_Save', $kept, true ) );
check( 'the Store API data stays', true, in_array( 'MeuMouse\WC_Custom_Installments\Integrations\Store_Api', $kept, true ) );
check( 'the tier prices stay', true, in_array( 'MeuMouse\WC_Custom_Installments\Integrations\Tiered_Pricing_Table', $kept, true ) );
check( 'the Pro cart fees stay', true, in_array( 'MeuMouse\WC_Custom_Installments\Pro\Engine\Gateway_Rates', $kept, true ) );

add_filter( 'WC_Custom_Installments/Storefront/Classes', function( $list ) {
	$list[] = 'MeuMouse\WC_Custom_Installments\Pro\Engine\Gateway_Rates';

	return $list;
} );

check( 'an add-on can put its classes on the list', false, in_array( 'MeuMouse\WC_Custom_Installments\Pro\Engine\Gateway_Rates', Headless_Mode::maybe_withhold_storefront( $classes ), true ) );

$GLOBALS['wp_test_filters']['WC_Custom_Installments/Storefront/Classes'] = array();

set_settings( array( 'headless_mode' => 'no' ) );
add_filter( 'WC_Custom_Installments/Headless/Is_Enabled', '__return_true' );

check( 'code can force the mode on', true, Headless_Mode::is_enabled() );

$GLOBALS['wp_test_filters']['WC_Custom_Installments/Headless/Is_Enabled'] = array();

echo PHP_EOL, 'A product in the Store API', PHP_EOL;

// The defaults: 12 instalments at most, 3 of them interest-free, 2% a month
// after that, 20 minimum, 10% off upfront.
$product = wc_test_product( 300 );
$data = Store_Api::build_product_data( $product, $money );

check( 'every instalment down to the minimum', 12, count( $data['installments'] ) );
check( 'the first is the price itself', array( 'count' => 1, 'amount' => '30000', 'total' => '30000', 'has_interest' => false ), $data['installments'][0] );
check( 'the fourth carries interest', true, $data['installments'][3]['has_interest'] );
check( 'the highlight is the largest interest-free one', array( 'count' => 3, 'amount' => '10000', 'total' => '30000', 'has_interest' => false ), $data['best_installments']['without_interest'] );
check( 'and only that one, as the store chose', null, $data['best_installments']['with_interest'] );
check( 'the upfront price', '27000', $data['discounts']['main']['price'] ?? null );
check( 'what paying upfront saves', '3000', $data['discounts']['main']['savings'] ?? null );
check( 'the store\'s copy goes along', 'no Pix', $data['discounts']['main']['text_after'] ?? null );
check( 'no bank slip price without Pro', null, $data['discounts']['ticket'] );
check( 'the interest-free label', 'sem juros', $data['labels']['without_interest'] );

set_settings( array( 'get_type_best_installments' => 'both' ) );
$data = Store_Api::build_product_data( $product, $money );

check( 'both highlights when the store shows both', 12, $data['best_installments']['with_interest']['count'] ?? null );

set_settings( array( 'get_type_best_installments' => 'hide' ) );
$data = Store_Api::build_product_data( $product, $money );

check( 'no highlight when the store hides it', array( 'without_interest' => null, 'with_interest' => null ), $data['best_installments'] );
check( 'the table is still there', 12, count( $data['installments'] ) );

set_settings( array( 'get_type_best_installments' => 'best_installment_without_fee' ) );

echo PHP_EOL, 'A variable product', PHP_EOL;

$variable = wc_test_product( 200, array(
	'id' => 2,
	'type' => 'variable',
	'variation_prices' => array(
		'price' => array( 21 => 200, 22 => 400 ),
		'regular_price' => array( 21 => 200, 22 => 400 ),
		'sale_price' => array( 21 => '', 22 => '' ),
	),
) );

$data = Store_Api::build_product_data( $variable, $money );

// As on the storefront: the table from the highest price, the highlight from
// the lowest, because it reads as "starting from".
check( 'the table starts from the highest price', '40000', $data['installments'][0]['amount'] );
check( 'the highlight from the lowest', '6667', $data['best_installments']['without_interest']['amount'] ?? null );
check( 'the upfront price from the lowest', '18000', $data['discounts']['main']['price'] ?? null );

echo PHP_EOL, 'What the store switched off', PHP_EOL;

$GLOBALS['wp_test_meta'][1]['__disable_installments'] = 'yes';
$data = Store_Api::build_product_data( $product, $money );

check( 'a product opted out of instalments has none', array(), $data['installments'] );
check( 'and no highlight', null, $data['best_installments']['without_interest'] );
check( 'but keeps its upfront price', '27000', $data['discounts']['main']['price'] ?? null );

$GLOBALS['wp_test_meta'][1]['__disable_discount_main_price'] = 'yes';

check( 'a product opted out of the discount has no upfront price', null, Store_Api::build_product_data( $product, $money )['discounts']['main'] );

$GLOBALS['wp_test_meta'] = array();

set_settings( array( 'enable_installments_all_products' => 'no', 'enable_all_discount_options' => 'no' ) );
$data = Store_Api::build_product_data( $product, $money );

check( 'no instalments when the store shows none', array(), $data['installments'] );
check( 'no upfront price when the store gives no discount', null, $data['discounts']['main'] );

set_settings( array( 'enable_installments_all_products' => 'yes', 'enable_all_discount_options' => 'yes' ) );

check( 'no data for something that is not a product', array(), Store_Api::build_product_data( null, $money )['installments'] );

echo PHP_EOL, 'The bank slip, with Pro', PHP_EOL;

add_filter( 'WC_Custom_Installments/Features/Is_Available', '__return_true' );
Features::flush();

set_settings( array(
	'enable_ticket_method_payment_form' => 'yes',
	'enable_ticket_discount_main_price' => 'yes',
	'discount_method_ticket' => 'percentage',
	'discount_ticket' => '5',
) );

check( '5% off by bank slip', '28500', Store_Api::build_product_data( $product, $money )['discounts']['ticket']['price'] ?? null );

$GLOBALS['wp_test_filters']['WC_Custom_Installments/Features/Is_Available'] = array();
Features::flush();

echo PHP_EOL, 'The cart in the Store API', PHP_EOL;

// Two at 100 and 20 of shipping, which the defaults include: 10% off 220.
$cart = new Test_Cart( wc_test_product( 100 ), 2, 20 );
$data = Store_Api::build_cart_data( $cart, $money );

check( 'the upfront total', '19800', $data['discounts']['main']['total'] ?? null );
check( 'the discount', '2200', $data['discounts']['main']['discount'] ?? null );

set_settings( array( 'include_shipping_value_in_discounts' => 'no' ) );

check( 'shipping left out when the store says so', '2000', Store_Api::build_cart_data( $cart, $money )['discounts']['main']['discount'] ?? null );
check( 'no cart, no data', null, Store_Api::build_cart_data( null, $money )['discounts']['main'] );

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
