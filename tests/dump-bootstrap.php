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
 * @since 1.0.0
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

$payload = array(
	'version' => WC_CUSTOM_INSTALLMENTS_VERSION,
	'page' => 'settings',
	'schema' => Registry::get_schema(),
	'settings' => Repository::get_settings(),
	'features' => Features::all(),
	'pro' => array(
		'active' => false,
		'store_url' => 'https://meumouse.com/plugins/wc-custom-installments/',
		'docs_url' => 'https://ajuda.meumouse.com/docs/wc-custom-installments/overview',
	),
	'environment' => array(
		'currency_symbol' => 'R$',
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

printf(
	'Wrote %s (%d fields, %d locked).%s',
	basename( $target ),
	count( Registry::get_field_definitions() ),
	count( array_filter( Features::all(), static function( $feature ) {
		return empty( $feature['available'] );
	} ) ),
	PHP_EOL
);
