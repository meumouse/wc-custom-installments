<?php

/**
 * Setup wizard harness.
 *
 * Two things here are worth pinning. The wizard writes settings, and it must
 * only ever write the ones it shows — a malformed step payload reaching an
 * unrelated setting would be a configuration change nobody made. And the trial
 * step is the only place the free plugin talks to anything outside the site,
 * so "it stays silent unless asked" has to be a fact rather than an intention.
 *
 * Run it with any PHP 8.1+ binary:
 *
 *   php tests/onboarding-test.php
 *
 * @since 6.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use MeuMouse\WC_Custom_Installments\Admin\Onboarding\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Onboarding\Trial;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Onboarding;

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

echo PHP_EOL, 'The steps', PHP_EOL;

$steps = Registry::get_steps();

check( 'there are seven', 7, count( $steps ) );
check(
	'in the order the copy assumes',
	array( 'welcome', 'installments', 'discount', 'payment-methods', 'display', 'trial', 'finish' ),
	array_column( $steps, 'id' )
);
check( 'the instalment step cannot be skipped', false, $steps[1]['optional'] );
check( 'the trial step can', true, $steps[5]['optional'] );

echo PHP_EOL, 'What the wizard may write', PHP_EOL;

$allowed = Registry::allowed_keys();

check( 'the instalment rules are in reach', true, in_array( 'max_qtd_installments', $allowed, true ) );
check( 'so are the card brands', true, in_array( 'enable_visa_flag_credit', $allowed, true ) );

// The wizard shows none of these, so a step payload naming one is either a bug
// or someone probing. Either way it must not land.
check( 'the CSS selectors are not', false, in_array( 'selectors_group_for_center_elements', $allowed, true ) );
check( 'nor is the element design', false, in_array( 'elements_design', $allowed, true ) );
check( 'nor are the copy templates', false, in_array( 'text_display_installments_loop', $allowed, true ) );

echo PHP_EOL, 'A step payload reaching past its allowance', PHP_EOL;

Repository::reset_settings();

$before = Repository::get('selectors_group_for_center_elements');

// What the REST route does with a payload, in one line.
$payload = array(
	'max_qtd_installments' => '6',
	'selectors_group_for_center_elements' => '.hacked',
);

Repository::patch_settings( array_intersect_key( $payload, array_flip( $allowed ) ) );

check( 'the allowed setting is written', '6', Repository::get('max_qtd_installments') );
check( 'the one out of reach is untouched', $before, Repository::get('selectors_group_for_center_elements') );

echo PHP_EOL, 'A step leaves every setting it does not carry alone', PHP_EOL;

// A step carries a handful of settings. Written as a full save, every toggle
// missing from it read as switched off, so finishing the instalment step turned
// off instalments on every product, the cart instalments and the Elementor
// widgets.
Repository::reset_settings();

// The store switched the cart instalments off before opening the wizard.
Repository::patch_settings( array( 'display_installments_cart' => 'no' ) );

Repository::patch_settings( array_intersect_key( array( 'max_qtd_installments' => '10' ), array_flip( $allowed ) ) );

check( 'the step writes what it carries', '10', Repository::get('max_qtd_installments') );
check( 'instalments on every product stay on', 'yes', Repository::get('enable_installments_all_products') );
check( 'the Elementor widgets stay on', 'yes', Repository::get('enable_elementor_widgets') );
check( 'a toggle the wizard shows but this step does not carry stays on', 'yes', Repository::get('enable_all_discount_options') );
check( 'a toggle the store switched off stays off', 'no', Repository::get('display_installments_cart') );

Repository::patch_settings( array_intersect_key( array(
	'enable_all_discount_options' => 'no',
	'enable_pix_method_payment_form' => 'yes',
), array_flip( $allowed ) ) );

check( 'a step can still switch a toggle off', 'no', Repository::get('enable_all_discount_options') );
check( 'and one on', 'yes', Repository::get('enable_pix_method_payment_form') );
check( 'the earlier step is kept', '10', Repository::get('max_qtd_installments') );
check( 'and so is everything else', 'yes', Repository::get('enable_installments_all_products') );

// There is no add-on in this harness, so the sale badge is locked.
Repository::patch_settings( array( 'enable_sale_badge' => 'yes' ) );

check( 'a partial write still refuses a locked field', 'no', Repository::get('enable_sale_badge') );

// The settings screen is the other kind of writer: it submits every setting,
// so a toggle missing from its payload was unticked.
Repository::save_settings( array( 'max_qtd_installments' => '10' ) );

check( 'a full save still reads an absent toggle as off', 'no', Repository::get('enable_installments_all_products') );

echo PHP_EOL, 'The trial stays silent unless asked', PHP_EOL;

// WC_CUSTOM_INSTALLMENTS_PRO_PRODUCT_ID is empty in the harness, which is the
// shipped default: nothing to request, so nothing is requested.
check( 'with no product configured, no trial is offered', false, Trial::is_available() );

$result = Trial::request( array(
	'first_name' => 'Ana',
	'last_name' => 'Souza',
	'email' => 'ana@example.com',
	'phone' => '5541999998888',
) );

check( 'and a request is refused before any network call', true, is_wp_error( $result ) );
check( 'with a reason the screen can show', 'wcci_trial_unavailable', $result->get_error_code() );

echo PHP_EOL, 'What the trial form would send', PHP_EOL;

// The disclosure on the form shows this exact array. If the two ever disagree,
// the consent notice becomes a description of something else.
$preview = Trial::preview( array(
	'first_name' => 'Ana',
	'last_name' => 'Souza',
	'email' => 'ana@example.com',
	'phone' => '5541999998888',
) );

check(
	'four fields and the product, and nothing else',
	array( 'productId', 'firstName', 'lastName', 'email', 'phone' ),
	array_keys( $preview )
);

check( 'no site address', false, in_array( 'https://example.test', $preview, true ) );
check( 'no admin e-mail beyond the one typed', 'ana@example.com', $preview['email'] );

echo PHP_EOL, 'Wizard state', PHP_EOL;

$state = Onboarding::get_state();

check( 'a fresh install has not completed it', false, $state['completed'] );
check( 'and has not dismissed it', false, $state['dismissed'] );

Onboarding::update_state( array( 'step' => 'discount' ) );
check( 'progress is remembered', 'discount', Onboarding::get_state()['step'] );

Onboarding::update_state( array( 'dismissed' => true ) );
check( 'skipping dismisses the reminder', true, Onboarding::is_dismissed() );

// Skipping is not finishing: the store owner may well come back, and the
// screen should not pretend they answered questions they did not.
check( 'but does not mark it complete', false, Onboarding::is_completed() );

Onboarding::complete();
check( 'finishing does', true, Onboarding::is_completed() );

printf( '%s%d passed, %d failed%s%s', PHP_EOL, $passed, $failed, PHP_EOL, PHP_EOL );

exit( $failed > 0 ? 1 : 0 );
