<?php

/**
 * Migration harness.
 *
 * A store upgrading from the 5.x plugin has years of configuration in the old
 * options, and losing any of it means a wrong price on a live shop. This proves
 * the three things that matter: everything transfers, nothing legacy is
 * deleted, and running it twice changes nothing.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/migration-test.php
 *
 * @since 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Transfer;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Compatibility\Conflict_Guard;
use MeuMouse\WC_Custom_Installments\Core\Migrator;

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
 * Seed the options a 5.x installation would have left behind.
 *
 * @return void
 */
function seed_legacy_install() {
	update_option( 'woo-custom-installments-setting', array(
		// Values the store owner changed away from the defaults.
		'max_qtd_installments' => '10',
		'fee_installments_global' => '3.49',
		'discount_main_price' => '7',
		'text_before_price' => 'À vista',
		'text_after_price' => 'no Pix',
		'enable_pix_method_payment_form' => 'yes',
		'enable_visa_flag_credit' => 'yes',
		'center_group_elements_loop' => 'yes',

		// The 5.x tabs spelled the same placement three different ways.
		'hook_display_best_installments' => 'only_loop_products',
		'display_discount_price_hook' => 'hide',
		'display_economy_pix_hook' => 'global',

		// Pro settings the store had licensed.
		'set_fee_per_installment' => 'yes',
		'enable_sale_badge' => 'yes',

		// A setting the new plugin does not carry.
		'enable_auto_updates' => 'yes',
	) );

	update_option( 'woo_custom_installments_discounts_setting', serialize( array(
		'pix' => array( 'amount' => '5', 'type' => 'percentage' ),
		'cod' => array( 'amount' => '2.5', 'type' => 'fixed' ),
	) ) );

	update_option( 'woo_custom_installments_interests_setting', serialize( array(
		'stripe' => array( 'amount' => '1.99', 'type' => 'percentage' ),
	) ) );

	update_option( 'woo_custom_installments_custom_fee_installments', serialize( array(
		2 => array( 'amount' => '1.99' ),
		3 => array( 'amount' => '2.49' ),
	) ) );
}

echo PHP_EOL, 'Migrating a 5.x installation', PHP_EOL;

seed_legacy_install();

$report = Migrator::migrate();

check( 'the store\'s own values transfer', '10', Repository::get('max_qtd_installments') );
check( 'a decimal rate transfers verbatim', '3.49', Repository::get('fee_installments_global') );
check( 'pt_BR copy transfers verbatim', 'À vista', Repository::get('text_before_price') );
check( 'a payment method toggle transfers', 'yes', Repository::get('enable_pix_method_payment_form') );
check( 'a card brand toggle transfers', 'yes', Repository::get('enable_visa_flag_credit') );
check( 'a Pro toggle transfers even while unlicensed', 'yes', Repository::get('set_fee_per_installment') );

echo PHP_EOL, 'Placement spellings', PHP_EOL;

// Left untranslated these would survive migration but not be options the new
// schema offers, so the first save from the settings screen would silently
// reset each one to its default.
check( 'only_loop_products becomes only_loop', 'only_loop', Repository::get('hook_display_best_installments') );
check( 'hide becomes none', 'none', Repository::get('display_discount_price_hook') );
check( 'global becomes the both-places value', 'display_loop_and_single_product', Repository::get('display_economy_pix_hook') );

$definitions = Registry::get_field_definitions();
$allowed = array_column( $definitions['hook_display_best_installments']['options'], 'value' );
check( 'and the migrated value is one the schema offers', true, in_array( Repository::get('hook_display_best_installments'), $allowed, true ) );

echo PHP_EOL, 'Sidecar options', PHP_EOL;

check(
	'gateway discounts transfer with their shape',
	array( 'amount' => '5', 'type' => 'percentage' ),
	Repository::get('gateway_discounts')['pix'] ?? null
);

check(
	'gateway interests transfer',
	array( 'amount' => '1.99', 'type' => 'percentage' ),
	Repository::get('gateway_interests')['stripe'] ?? null
);

check(
	'the interest table keeps the 5.x shape',
	array( 'amount' => '2.49' ),
	Repository::get('custom_fee_installments')[3] ?? null
);

echo PHP_EOL, 'What is left behind', PHP_EOL;

check( 'a dropped setting is reported', true, in_array( 'enable_auto_updates', $report['skipped'], true ) );
check( 'a dropped setting is not stored', false, array_key_exists( 'enable_auto_updates', Repository::get_settings() ) );

echo PHP_EOL, 'Nothing legacy is destroyed', PHP_EOL;

check( 'the 5.x option survives', true, is_array( get_option('woo-custom-installments-setting') ) );
check( 'the discounts sidecar survives', true, false !== get_option('woo_custom_installments_discounts_setting') );
check( 'the interests sidecar survives', true, false !== get_option('woo_custom_installments_interests_setting') );
check( 'the fee table sidecar survives', true, false !== get_option('woo_custom_installments_custom_fee_installments') );

echo PHP_EOL, 'Running it again', PHP_EOL;

$before = Repository::get_settings();
Migrator::migrate();

check( 'a second run changes nothing', $before, Repository::get_settings() );

echo PHP_EOL, 'Detecting a legacy install', PHP_EOL;

check( 'a 5.x install is recognised', true, Migrator::has_legacy_data() );

echo PHP_EOL, 'A Pro value survives an unlicensed save', PHP_EOL;

// The migrated store had the sale badge on. Saving from an unlicensed screen
// must not be what turns it off: the gate stops the feature from running, and
// re-licensing has to bring the configuration back exactly as it was.
Repository::save_settings( array( 'max_qtd_installments' => '6' ) );

check( 'the free field is written', '6', Repository::get('max_qtd_installments') );
check( 'the migrated Pro value is untouched', 'yes', Repository::get('enable_sale_badge') );
check( 'the gated feature still does not run', false, Repository::is_active('enable_sale_badge') );

echo PHP_EOL, 'Export and import', PHP_EOL;

$document = Transfer::export();

check( 'the export is signed', 'wc-custom-installments-settings', $document['signature'] );
check( 'the export carries the settings', '6', $document['settings']['max_qtd_installments'] ?? null );

$foreign = Transfer::import( array( 'signature' => 'something-else', 'settings' => array() ) );
check( 'a foreign file is refused', 'wcci_foreign_import', is_wp_error( $foreign ) ? $foreign->get_error_code() : '' );

$empty = Transfer::import( array( 'signature' => 'wc-custom-installments-settings' ) );
check( 'a file with no settings is refused', 'wcci_empty_import', is_wp_error( $empty ) ? $empty->get_error_code() : '' );

$document['settings']['max_qtd_installments'] = '24';
Transfer::import( $document );

check( 'a valid file is applied', '24', Repository::get('max_qtd_installments') );

echo PHP_EOL, 'Numeric limits', PHP_EOL;

Repository::save_settings( array(
	'max_qtd_installments' => '999',
	'fee_installments_global' => '-4',
	'min_value_installments' => '20,50',
) );

check( 'a value over the maximum is clamped', '48', Repository::get('max_qtd_installments') );
check( 'a negative rate is clamped to zero', '0', Repository::get('fee_installments_global') );
check( 'a comma decimal is accepted', '20.5', Repository::get('min_value_installments') );

echo PHP_EOL, 'Living beside the 5.x plugin', PHP_EOL;

// Two plugins on woocommerce_get_price_html means the shopper sees the price
// group twice, and - since each reads its own copy of the settings - sometimes
// with two different discounts.
check( 'no conflict on a clean install', false, Conflict_Guard::has_conflict() );

$all = array(
	'MeuMouse\WC_Custom_Installments\Frontend\Render_Elements',
	'MeuMouse\WC_Custom_Installments\Frontend\Shortcodes',
	'MeuMouse\WC_Custom_Installments\Assets\Storefront_Assets',
	'MeuMouse\WC_Custom_Installments\Admin\Menu',
	'MeuMouse\WC_Custom_Installments\Core\Migrator',
);

$kept = Conflict_Guard::withhold_storefront( $all );

check( 'nothing that renders a price survives the guard', array(), array_values( array_intersect( $kept, Conflict_Guard::STOREFRONT_CLASSES ) ) );

// The admin has to keep working: this is where the store owner reads the
// notice and presses the button that resolves it.
check( 'the settings screen still loads', true, in_array( 'MeuMouse\WC_Custom_Installments\Admin\Menu', $kept, true ) );
check( 'and the importer still runs', true, in_array( 'MeuMouse\WC_Custom_Installments\Core\Migrator', $kept, true ) );

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
