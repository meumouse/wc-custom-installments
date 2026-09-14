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
 * @since 6.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use MeuMouse\WC_Custom_Installments\Admin\Default_Options;
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

echo PHP_EOL, 'Every setting can be reached', PHP_EOL;

// A default with no schema field can never be written: the repository only
// writes what the schema declares. The setting then sits at its default
// forever, and nothing reports it — this one surfaced only when the Pro
// engine tried to read it, three phases later.
$orphans = array_values( array_diff(
	array_keys( MeuMouse\WC_Custom_Installments\Admin\Default_Options::get_defaults() ),
	array_keys( Registry::get_field_definitions() )
) );

check( 'no setting is missing from the schema', array(), $orphans );

// The reverse is just as bad in its own way: a field with no default reads as
// an empty string on a fresh install, wherever it is used.
$undefaulted = array_values( array_diff(
	array_keys( Registry::get_field_definitions() ),
	array_keys( MeuMouse\WC_Custom_Installments\Admin\Default_Options::get_defaults() )
) );

check( 'and no schema field is missing a default', array(), $undefaulted );

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

echo PHP_EOL, 'Card brands registered while licensed, after the licence lapsed', PHP_EOL;

update_option( Repository::OPTION, array_merge( (array) get_option( Repository::OPTION, array() ), array(
	'custom_card_brands' => array( array( 'id' => 'custom_cabal', 'label' => 'Cabal', 'image' => 'https://example.com/cabal.png' ) ),
	'enable_custom_cabal_flag_credit' => 'yes',
) ) );

Registry::flush();

check( 'the brand is still listed', 'Cabal', Default_Options::get_card_brands()['custom_cabal'] ?? null );
check( 'its switch is locked', true, ! empty( Registry::get_field_definitions()['enable_custom_cabal_flag_credit']['locked'] ) );
check( 'so its badge leaves the storefront', false, Repository::is_active('enable_custom_cabal_flag_credit') );
check( 'while a brand the plugin ships is not gated', '', Registry::get_field_definitions()['enable_visa_flag_credit']['feature'] );

Repository::save_settings( array( 'custom_card_brands' => array() ) );

check( 'the brands cannot be changed', array( 'custom_cabal' ), array_column( Repository::get('custom_card_brands'), 'id' ) );
check( 'and a save leaves the switch as it was', 'yes', Repository::get('enable_custom_cabal_flag_credit') );

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
check( 'a registered brand is back on the storefront, still switched on', true, Repository::is_active('enable_custom_cabal_flag_credit') );

Repository::save_settings( array( 'enable_sale_badge' => 'yes' ) );

check( 'the field is now written', 'yes', Repository::get('enable_sale_badge') );
check( 'the toggle is now active', true, Repository::is_active('enable_sale_badge') );

echo PHP_EOL, 'Registering card brands', PHP_EOL;

Repository::save_settings( array(
	'custom_card_brands' => array(
		array( 'id' => 'custom_cabal', 'label' => 'Cabal', 'image' => 'https://example.com/cabal.png' ),
		array( 'label' => 'Cartão Sorocred', 'image' => 'https://example.com/sorocred.svg' ),
		array( 'label' => 'Cartão Sorocred', 'image' => 'javascript:alert(1)' ),
		array( 'label' => '   ', 'image' => 'https://example.com/nameless.svg' ),
		array( 'id' => 'visa', 'label' => 'Não é a Visa' ),
		array( 'id' => 'custom_cabal', 'label' => 'Cabal de novo' ),
		'not a row',
	),
	'enable_custom_cabal_flag_credit' => 'yes',
) );

$brands = Repository::get('custom_card_brands');

check( 'a row with no name is dropped', array( 'Cabal', 'Cartão Sorocred', 'Cartão Sorocred', 'Não é a Visa', 'Cabal de novo' ), array_column( $brands, 'label' ) );
check( 'a brand keeps its key', 'custom_cabal', $brands[0]['id'] );
check( 'a new brand gets its key from its name', 'custom_cartao_sorocred', $brands[1]['id'] );
check( 'a second brand of that name gets another', 'custom_cartao_sorocred_2', $brands[2]['id'] );
check( 'a script URL is not an image', '', $brands[2]['image'] );
check( 'a key the browser made up is replaced', 'custom_nao_e_a_visa', $brands[3]['id'] );
check( 'and so is one another row already has', 'custom_cabal_de_novo', $brands[4]['id'] );
check( 'a registered brand joins the list after the ones the plugin ships', array( 'cielo', 'custom_cabal' ), array_slice( array_keys( Default_Options::get_card_brands() ), 12, 2 ) );

$definitions = Registry::get_field_definitions();

check( 'it gets a switch on the credit card list', 'custom_card_brands', $definitions['enable_custom_cartao_sorocred_flag_credit']['feature'] ?? null );
check( 'and on the debit card list', 'custom_card_brands', $definitions['enable_custom_cartao_sorocred_flag_debit']['feature'] ?? null );
check( 'which starts off', 'no', Repository::get('enable_custom_cartao_sorocred_flag_credit') );

Repository::save_settings( array(
	'custom_card_brands' => array(
		array( 'label' => 'Cartão Sorocred' ),
		array( 'id' => 'custom_cartao_sorocred', 'label' => 'Sorocred', 'image' => 'https://example.com/sorocred.svg' ),
		array( 'id' => 'custom_cabal', 'label' => 'Cabal', 'image' => 'https://example.com/cabal.png' ),
	),
	'enable_custom_cartao_sorocred_flag_credit' => 'yes',
	'enable_custom_cabal_flag_credit' => 'yes',
	'enable_custom_nao_e_a_visa_flag_debit' => 'yes',
) );

$brands = Repository::get('custom_card_brands');

check( 'a renamed brand keeps its key', 'custom_cartao_sorocred', $brands[1]['id'] );
check( 'a new row under its old name, listed first, does not take it', 'custom_cartao_sorocred_2', $brands[0]['id'] );
check( 'its switch is written', true, Repository::is_active('enable_custom_cartao_sorocred_flag_credit') );
check( 'a removed brand leaves the list', false, isset( Default_Options::get_card_brands()['custom_nao_e_a_visa'] ) );
check( 'and takes its switches with it', false, array_key_exists( 'enable_custom_nao_e_a_visa_flag_debit', get_option( Repository::OPTION ) ) );

$flags = ( new MeuMouse\WC_Custom_Installments\Frontend\Components() )->get_card_flags( 'credit-card', 'credit' );

check( 'the storefront shows it with its own image', true, false !== strpos( $flags, 'src="https://example.com/sorocred.svg" alt="Sorocred"' ) );
check( 'and its own class', true, false !== strpos( $flags, 'custom_cartao_sorocred-flag' ) );

Repository::save_settings( array(
	'custom_card_brands' => array(
		array( 'id' => 'custom_cartao_sorocred', 'label' => 'Sorocred', 'image' => '' ),
		array( 'id' => 'custom_cabal', 'label' => 'Cabal', 'image' => 'https://example.com/cabal.png' ),
	),
	'enable_custom_cartao_sorocred_flag_credit' => 'yes',
	'enable_custom_cabal_flag_credit' => 'yes',
) );

check( 'a brand with no image yet is left out', false, strpos( ( new MeuMouse\WC_Custom_Installments\Frontend\Components() )->get_card_flags( 'credit-card', 'credit' ), 'custom_cartao_sorocred-flag' ) );

Repository::save_settings( array(
	'custom_card_brands' => array( array( 'id' => 'custom_elo_debito', 'label' => 'Elo Débito', 'image' => 'https://example.com/elo.svg' ) ),
	'enable_custom_elo_debito_flag_debit' => 'yes',
) );

check( 'a brand arriving with its switches, as an import brings it, keeps them', 'yes', Repository::get('enable_custom_elo_debito_flag_debit') );

echo PHP_EOL, 'The 5.x options and spellings', PHP_EOL;

// Each of these was a choice in 5.x. An option missing from the schema is not
// just absent from the screen: the first save rewrites the store's value to
// the default, silently.
foreach ( array(
	'get_type_best_installments' => array( 'hide', 'best_installment_without_fee', 'best_installment_with_fee', 'both' ),
	'display_installment_type' => array( 'popup', 'accordion', 'hide' ),
	'hook_payment_form_single_product' => array( 'before_cart', 'after_cart', 'custom_hook', 'shortcode', 'widget' ),
	'enable_discount_per_quantity_method' => array( 'global', 'product' ),
	'button_popup_size' => array( 'small', 'normal', 'large', 'link' ),
	'icon_format_elements' => array( 'class', 'upload' ),
) as $key => $values ) {
	$offered = array_column( (array) ( $definitions[ $key ]['options'] ?? array() ), 'value' );

	check( sprintf( '%s offers every 5.x value', $key ), array(), array_values( array_diff( $values, $offered ) ) );
}

Repository::save_settings( array(
	'display_installment_type' => 'none',
	'hook_payment_form_single_product' => 'custom',
	'enable_discount_per_quantity_method' => 'per_product',
	'display_discount_price_hook' => 'only_loop_products',
	'icon_format_elements' => 'svg',
) );

check( 'a renamed value is stored under its current name', 'hide', Repository::get('display_installment_type') );
check( 'the custom hook keeps its 5.x value', 'custom_hook', Repository::get('hook_payment_form_single_product') );
check( 'per-product mode keeps its 5.x value', 'product', Repository::get('enable_discount_per_quantity_method') );
check( 'a 5.x placement is translated on save', 'only_loop', Repository::get('display_discount_price_hook') );
check( 'the icon format lands on upload', 'upload', Repository::get('icon_format_elements') );

check(
	'the settings screen sees the current spelling of a stored alias',
	'custom_hook',
	Repository::normalize_settings( array( 'hook_payment_form_single_product' => 'custom' ) )['hook_payment_form_single_product']
);

echo PHP_EOL, 'Colours and copy the 5.x screen could store', PHP_EOL;

check( 'an rgb() colour survives', 'rgb(0, 138, 255)', Repository::sanitize_color( 'rgb(0, 138, 255)' ) );
check( 'an rgba() colour survives', 'rgba(34, 197, 94, 0.15)', Repository::sanitize_color( 'rgba(34, 197, 94, 0.15)' ) );
check( 'a hex colour survives', '#008aff', Repository::sanitize_color( '#008aff' ) );
check( 'anything else is not a colour', '', Repository::sanitize_color( 'red; background:url(x)' ) );

Repository::save_settings( array( 'button_popup_color' => 'rgb(0, 138, 255)' ) );
check( 'the popup colour keeps its rgb() value', 'rgb(0, 138, 255)', Repository::get('button_popup_color') );

Repository::save_settings( array( 'text_before_price' => '<strong>À vista</strong><script>alert(1)</script>' ) );
check( 'copy keeps simple markup and loses scripts', '<strong>À vista</strong>', Repository::get('text_before_price') );

echo PHP_EOL, 'Element design', PHP_EOL;

Repository::save_settings( array(
	'elements_design' => array(
		'price' => array(
			'order' => '3',
			'icon' => array( 'class' => 'fa-solid fa-tag"><script>', 'image' => 'javascript:alert(1)' ),
			'styles' => array(
				'desktop' => array(
					'font_size' => '1,5',
					'font_unit' => 'vh',
					'font_weight' => '700',
					'font_color' => 'rgba(38, 171, 91, 1)',
					'margin' => array( 'top' => '2', 'unit' => 'px' ),
				),
			),
		),
		'unknown_element' => array( 'order' => 1 ),
	),
) );

$design = Repository::get('elements_design');

check( 'an unknown element is dropped', false, isset( $design['unknown_element'] ) );
check( 'the order is kept', 3, $design['price']['order'] );
check( 'an icon class keeps only class characters', 'fa-solid fa-tagscript', $design['price']['icon']['class'] );
check( 'a script URL is not an icon', '', $design['price']['icon']['image'] );
check( 'a decimal comma is read', '1.5', $design['price']['styles']['desktop']['font_size'] );
check( 'an unknown unit falls back', 'rem', $design['price']['styles']['desktop']['font_unit'] );
check( 'the weight is kept', '700', $design['price']['styles']['desktop']['font_weight'] );
check( 'an rgba colour is kept', 'rgba(38, 171, 91, 1)', $design['price']['styles']['desktop']['font_color'] );
check( 'a partial box keeps its unit', 'px', $design['price']['styles']['desktop']['margin']['unit'] );
check( 'and the sides it did not send', '0', $design['price']['styles']['desktop']['margin']['left'] );
check( 'every other element is filled in', true, isset( $design['discount_pix']['styles']['mobile']['padding']['top'] ) );
check( 'a part left alone inherits its size', '', $design['sale_price']['styles']['desktop']['font_size'] );
check( 'and its colour', '', $design['sale_price']['styles']['desktop']['font_color'] );
check( 'and its spacing', '', $design['sale_price']['styles']['desktop']['margin']['top'] );

Repository::save_settings( array(
	'elements_design' => array(
		'price' => array(
			'styles' => array( 'desktop' => array( 'margin' => array( 'left' => '' ) ) ),
		),
		'sale_price' => array(
			'styles' => array(
				'desktop' => array(
					'font_size' => '1,4',
					'font_weight' => '',
					'font_color' => '#e11d48',
					'padding' => array( 'top' => '0.25', 'left' => '' ),
				),
			),
		),
	),
) );

$design = Repository::get('elements_design');

check( 'a part keeps the size it was given', '1.4', $design['sale_price']['styles']['desktop']['font_size'] );
check( 'and an emptied weight stays inherited', '', $design['sale_price']['styles']['desktop']['font_weight'] );
check( 'and its colour', '#e11d48', $design['sale_price']['styles']['desktop']['font_color'] );
check( 'and the side it set', '0.25', $design['sale_price']['styles']['desktop']['padding']['top'] );
check( 'while an empty side stays empty', '', $design['sale_price']['styles']['desktop']['padding']['left'] );
check( 'a top-level element still reads an empty side as zero', '0', $design['price']['styles']['desktop']['margin']['left'] );

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
