<?php

/**
 * Settings schema harness.
 *
 * Building the schema lists the store's payment gateways, and the Pro add-on
 * reads settings inside `woocommerce_gateway_title` to annotate the checkout
 * label. Reading a setting builds the schema, so asking each gateway for its
 * filtered title while building it re-entered the build until memory ran out:
 * a blank HTTP 500 on any AJAX request with a cart that asked for a gateway's
 * title first, such as an order import. Nothing reported it, so it gets a test.
 *
 * The list itself is the other half: it comes from the registered gateways
 * filtered by their "enabled" setting, because asking WooCommerce for the
 * available ones runs every gateway's is_available(), and a gateway that reads
 * the cart there loads it from the session while the schema is still being
 * built on `init` — a 502 on the wp-admin AJAX requests.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/settings-schema-test.php
 *
 * @since 6.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;

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
 * A payment gateway as WooCommerce exposes it: an "enabled" setting, a stored
 * title, get_title() running it through `woocommerce_gateway_title`, and an
 * is_available() that stands for the ones reading the cart.
 *
 * @since 6.0.0
 */
class Test_Gateway {

	/**
	 * @var string
	 */
	public $id;

	/**
	 * @var string
	 */
	public $title;

	/**
	 * @var string
	 */
	public $method_title;


	/**
	 * @var string
	 */
	public $enabled;


	/**
	 * @var bool
	 */
	public $available;


	/**
	 * Build a gateway.
	 *
	 * @since 6.0.0
	 * @param string $id | Gateway id
	 * @param string $title | Title the store owner set
	 * @param string $method_title | Title the gateway ships with
	 * @param string $enabled | yes when the gateway is switched on
	 * @param bool $available | Whether it takes the current cart
	 * @return void
	 */
	public function __construct( $id, $title, $method_title, $enabled = 'yes', $available = true ) {
		$this->id = $id;
		$this->title = $title;
		$this->method_title = $method_title;
		$this->enabled = $enabled;
		$this->available = $available;
	}


	/**
	 * The title as the checkout shows it.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function get_title() {
		return apply_filters( 'woocommerce_gateway_title', $this->title, $this->id );
	}


	/**
	 * Whether the gateway takes the current cart. This is where a gateway reads
	 * the cart, so every call is counted.
	 *
	 * @since 6.1.1
	 * @return bool
	 */
	public function is_available() {
		$GLOBALS['availability_checks']++;

		return 'yes' === $this->enabled && $this->available;
	}
}

$GLOBALS['availability_checks'] = 0;

$GLOBALS['wc_test_gateways'] = array(
	'pix' => new Test_Gateway( 'pix', 'Pix', 'Pix (Banco Inter)' ),
	'boleto' => new Test_Gateway( 'boleto', '<strong>Boleto</strong> parcelado', 'Boleto' ),
	'card' => new Test_Gateway( 'card', '', 'Cartão de crédito' ),
	'bank-slip' => new Test_Gateway( 'bank-slip', 'Boleto à vista', 'Boleto bancário', 'no' ),
	'boleto-installments' => new Test_Gateway( 'boleto-installments', 'Boleto em parcelas', 'Boleto parcelado', 'yes', false ),
);

/**
 * The slice of WooCommerce the schema reaches for.
 *
 * @return object
 */
function WC() {
	return new class() {
		public function payment_gateways() {
			return new class() {
				public function payment_gateways() {
					return $GLOBALS['wc_test_gateways'];
				}

				public function get_available_payment_gateways() {
					return array_filter(
						$GLOBALS['wc_test_gateways'],
						static function( $gateway ) {
							return $gateway->is_available();
						}
					);
				}
			};
		}
	};
}

// What the add-on does at checkout: read a setting, then annotate the label.
// A depth limit turns the old endless re-entry into a failure instead of a
// process that runs out of memory.
$GLOBALS['title_filter_depth'] = 0;
$GLOBALS['title_filter_calls'] = 0;

add_filter( 'woocommerce_gateway_title', static function( $title ) {
	$GLOBALS['title_filter_calls']++;

	if ( ++$GLOBALS['title_filter_depth'] > 3 ) {
		throw new RuntimeException( 'woocommerce_gateway_title re-entered while building the settings schema' );
	}

	try {
		if ( Repository::is_active('display_tag_discount_price_checkout') ) {
			$title .= ' <span class="badge-discount-checkout">10% de desconto</span>';
		}
	} finally {
		$GLOBALS['title_filter_depth']--;
	}

	return $title;
} );

echo PHP_EOL, 'A gateway title asked for before anything else reads a setting', PHP_EOL;

$error = '';

try {
	$annotated = WC()->payment_gateways()->get_available_payment_gateways()['pix']->get_title();
} catch ( Throwable $e ) {
	$annotated = '';
	$error = $e->getMessage();
}

check( 'the schema builds without re-entering the title filter', '', $error );
check( 'the filter ran once, for the title that was asked for', 1, $GLOBALS['title_filter_calls'] );
check( 'and the checkout label still carries its badge', 'Pix <span class="badge-discount-checkout">10% de desconto</span>', $annotated );
check( 'and only the request itself asked the gateways whether they take the cart', count( $GLOBALS['wc_test_gateways'] ), $GLOBALS['availability_checks'] );

echo PHP_EOL, 'The gateways the settings screen lists', PHP_EOL;

$labels = array();

foreach ( (array) ( Registry::get_field_definitions()['gateway_discounts']['gateways'] ?? array() ) as $gateway ) {
	$labels[ $gateway['id'] ] = $gateway['label'];
}

check( 'are named by the title the store owner set, with no checkout badge', 'Pix', $labels['pix'] ?? null );
check( 'without markup', 'Boleto parcelado', $labels['boleto'] ?? null );
check( 'or by the title the gateway ships with when that is empty', 'Cartão de crédito', $labels['card'] ?? null );
check( 'and the interest rates list the same ones', $labels, array_column( Registry::get_field_definitions()['gateway_interests']['gateways'] ?? array(), 'label', 'id' ) );
check( 'a gateway that is switched off is left out', false, array_key_exists( 'bank-slip', $labels ) );
check( 'one that is switched on is listed even when it turns the cart down', 'Boleto em parcelas', $labels['boleto-installments'] ?? null );

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
