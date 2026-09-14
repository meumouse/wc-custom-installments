<?php

/**
 * Write the settings bootstrap payload to a file, without WordPress.
 *
 * The preview harness (`preview.html`) renders the real admin bundle against
 * this fixture, so the screen can be exercised - tabs, dependencies, locked
 * fields, saving - with no database and no running site. Regenerate it after
 * changing the schema:
 *
 *   php tests/dump-bootstrap.php
 *
 * Then serve the plugin directory and open preview.html:
 *
 *   python -m http.server 8791 --bind 127.0.0.1
 *
 * @since 6.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Features;

// WooCommerce is not loaded here, so the gateway list arrives empty. Stand a few
// in, or the per-gateway controls would render as an empty state and the one
// thing worth looking at would be invisible.
add_filter( 'WC_Custom_Installments/Admin/Settings_Schema', function( $schema ) {
	$gateways = array(
		array( 'id' => 'pix', 'label' => 'Pix' ),
		array( 'id' => 'bacs', 'label' => 'Direct bank transfer' ),
		array( 'id' => 'cod', 'label' => 'Cash on delivery' ),
	);

	foreach ( $schema as $section_index => $section ) {
		foreach ( (array) ( $section['cards'] ?? array() ) as $card_index => $card ) {
			foreach ( (array) ( $card['fields'] ?? array() ) as $field_index => $field ) {
				if ( 'gateway-rates' === ( $field['type'] ?? '' ) ) {
					$schema[ $section_index ]['cards'][ $card_index ]['fields'][ $field_index ]['gateways'] = $gateways;
				}
			}
		}
	}

	return $schema;
} );

// `WCCI_PREVIEW_PRO=1 php tests/dump-bootstrap.php` renders the screen as a
// licensed store sees it, so the Pro controls can be exercised too.
if ( getenv('WCCI_PREVIEW_PRO') ) {
	add_filter( 'WC_Custom_Installments/Features/Is_Available', '__return_true' );
	Features::flush();
}

$payload = array(
	'version' => WC_CUSTOM_INSTALLMENTS_VERSION,
	'page' => 'settings',
	'schema' => Registry::get_schema(),
	'settings' => Repository::normalize_settings( Repository::get_settings() ),
	'features' => Features::all(),
	// Settings_Bootstrap::system_status() reads the live server; a fixed
	// sample keeps the fixture the same on every machine.
	'system_status' => array(
		array( 'group' => 'WordPress', 'label' => 'Versão do WordPress', 'value' => '6.9', 'ok' => true ),
		array( 'group' => 'WooCommerce', 'label' => 'Versão do WooCommerce', 'value' => '10.2.0', 'ok' => true ),
		array( 'group' => 'Servidor', 'label' => 'Versão do PHP', 'value' => '8.2.29', 'ok' => true ),
		array( 'group' => 'Servidor', 'label' => 'Limite de memória do PHP', 'value' => '64M', 'ok' => false, 'hint' => 'O valor mínimo recomendado é 128M.' ),
	),
	'pro' => array(
		'active' => false,
		'store_url' => 'https://meumouse.com/plugins/wc-custom-installments/',
		'docs_url' => 'https://ajuda.meumouse.com/docs/wc-custom-installments/overview',
	),
	'environment' => array(
		'currency_symbol' => 'R$',
		'price_format' => '%s %v',
		'decimal_separator' => ',',
		'thousand_separator' => '.',
	),
	'rest' => array(
		'root' => '/preview-api',
		'nonce' => 'preview',
	),
);

$target = dirname( __DIR__ ) . '/preview-bootstrap.json';
$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

file_put_contents( $target, $json );

// The wizard reads a payload of its own, assembled by its registry.
$onboarding = \MeuMouse\WC_Custom_Installments\Admin\Onboarding\Registry::get_bootstrap_data();
$onboarding['rest'] = array( 'root' => '/preview-api', 'nonce' => 'preview' );

file_put_contents(
	dirname( __DIR__ ) . '/preview-onboarding.json',
	json_encode( $onboarding, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
);

printf(
	'Wrote %s (%d fields, %d locked).%s',
	basename( $target ),
	count( Registry::get_field_definitions() ),
	count( array_filter( Features::all(), static function( $feature ) {
		return empty( $feature['available'] );
	} ) ),
	PHP_EOL
);
